<?php
if (!defined('ABSPATH')) exit;

final class JR_Store {
    public static function sku(string $console, int $days): string { return 'joyrent-'.$console.'-'.$days; }
    public static function product(string $console, int $days): ?WC_Product {
        if (!function_exists('wc_get_product_id_by_sku')) return null;
        $id=wc_get_product_id_by_sku(self::sku($console,$days));
        return $id ? wc_get_product($id) : null;
    }
    public static function catalog(): array {
        $data=JR_Domain::catalog(); $ready=class_exists('WooCommerce')&&get_woocommerce_currency()==='UAH';
        $offers=0;
        foreach ($data['tariffs'] as $console=>&$tariffs) {
            $available=[];
            foreach ($tariffs as $tariff) {
                $product=self::product($console,$tariff['days']);
                if (!$product||$product->get_status()!=='publish'||$product->get_price()===''||(float)$product->get_price()<=0) continue;
                $tariff['price']=(float)$product->get_price(); $available[]=$tariff; $offers++;
            }
            $tariffs=$available;
        }
        unset($tariff,$tariffs);
        return ['tariffs'=>$data['tariffs'],'games'=>JR_Games::records(),'settings'=>JR_Settings::public(),'currency'=>'UAH','acceptingRequests'=>$ready&&$offers>0];
    }
    public static function seed(): void {
        if (!class_exists('WooCommerce')) return;
        $lock=JR_Lock::acquire('joyrent_catalog_lock');
        if (!$lock) return;
        try { self::seed_catalog(); } finally { JR_Lock::release('joyrent_catalog_lock',$lock); }
    }
    private static function seed_catalog(): void {
        $empty=wc_get_products(['limit'=>1,'return'=>'ids'])===[];
        if ($empty&&!get_option('joyrent_seeded')) update_option('woocommerce_currency','UAH');
        foreach (JR_Domain::catalog()['tariffs'] as $console=>$tariffs) foreach ($tariffs as $tariff) {
            if (self::product($console,$tariff['days'])) continue;
            $product=new WC_Product_Simple();
            $product->set_name(strtoupper($console).' — '.$tariff['name'].' ('.$tariff['days'].' дн.)');
            $product->set_sku(self::sku($console,$tariff['days']));
            $product->set_regular_price((string)$tariff['price']);
            $product->set_description($tariff['description'].' Дати, доставка, комплектація та застава узгоджуються перед підтвердженням оренди.');
            $product->set_status('publish'); $product->set_virtual(true); $product->set_catalog_visibility('hidden'); $product->set_sold_individually(true); $product->set_tax_status('none');
            $product->update_meta_data('_joyrent_console',$console); $product->update_meta_data('_joyrent_days',$tariff['days']); $product->save();
        }
        self::seed_games();
        self::reconcile_games();
        self::legal_pages(); self::faq_pages(); update_option('joyrent_seeded',true,false);
    }
    private static function seed_games(): void {
        foreach (JR_Domain::catalog()['games'] as $position=>$game) {
            $existing=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>1,'meta_key'=>'_jr_game_id','meta_value'=>$game['id'],'fields'=>'ids']);
            if ($existing) continue;
            $id=wp_insert_post(['post_type'=>'joyrent_game','post_status'=>'publish','post_title'=>$game['title'],'post_content'=>$game['description'],'post_name'=>$game['id'],'menu_order'=>($position+1)*10],true);
            if (!is_wp_error($id)) { update_post_meta($id,'_jr_game_id',$game['id']); update_post_meta($id,'_jr_game',$game); }
        }
    }
    public static function upgrade(): void {
        if (version_compare((string)get_option('joyrent_version','0'),'1.6.0','>=')||!class_exists('WooCommerce')) return;
        $lock=JR_Lock::acquire('joyrent_catalog_lock');
        if (!$lock) return;
        try {
            if (version_compare((string)get_option('joyrent_version','0'),'1.6.0','>=') ) return;
            $previous=(string)get_option('joyrent_version','0');
            self::seed_games(); // Add missing games without republishing drafts or replacing owner content.
            foreach (JR_Domain::catalog()['games'] as $position=>$game) {
                $posts=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'meta_key'=>'_jr_game_id','meta_value'=>$game['id']]);
                if (!$posts) continue;
                foreach ($posts as $managed) {
                    $facts=(array)get_post_meta($managed->ID,'_jr_game',true);
                    foreach (['playersByPlatform','requiresInternet'] as $key) if (isset($game[$key])) $facts[$key]=$game[$key];
                    update_post_meta($managed->ID,'_jr_game',$facts);
                }
                // Only factual managed metadata changes in 1.6; owner text/artwork remain authoritative.
                $meta=(array)get_post_meta($posts[0]->ID,'_jr_game',true);
                // Keep the latest Mortal Kombat supported by each console. Preserve owner platform edits.
                if ($game['id']==='mk11'&&version_compare($previous,'1.5.0','<')) {
                    if (($meta['platforms']??[])===['ps5','ps4']) { $meta['platforms']=['ps4']; update_post_meta($posts[0]->ID,'_jr_game',$meta); }
                }
            }
            if (version_compare($previous,'1.5.0','<')) {
                $legacy=array_column(json_decode((string)file_get_contents(__DIR__.'/../data/legacy-games.json'),true),null,'id');
                foreach ($legacy as $retired=>$source) {
                    $posts=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'meta_key'=>'_jr_game_id','meta_value'=>$retired]);
                    foreach ($posts as $post) {
                        if (self::untouched_legacy($post,$source)) wp_delete_post($post->ID,true);
                    }
                }
            }
            self::legal_pages(); self::faq_pages(); // Add missing translations without rewriting owner pages or settings.
            self::reconcile_games();
            self::upgrade_settings();
            update_option('joyrent_version','1.6.0',false);
        } finally { JR_Lock::release('joyrent_catalog_lock',$lock); }
    }
    private static function untouched_legacy(WP_Post $post, array $source): bool {
        // Archived pre-1.5 seed positions. Any uncertain author state stays in the store.
        $positions=['fc25'=>10,'ufc5'=>90,'cod-bo6'=>100];$id=$source['id'];
        if ($post->post_title!==$source['title']||$post->post_content!==$source['description']||$post->post_excerpt!==''||$post->post_status!=='publish'||$post->post_name!==$id||(int)$post->menu_order!==($positions[$id]??-1)||(int)$post->post_parent!==0||$post->post_password!==''||(int)$post->post_author!==0||$post->post_modified_gmt!==$post->post_date_gmt) return false;
        $all=get_post_meta($post->ID);unset($all['_edit_lock'],$all['_edit_last']);ksort($all);
        $expected=['_jr_game'=>[maybe_serialize($source)],'_jr_game_id'=>[$id]];ksort($expected);
        // Includes excerpt, priority and every owner meta field, even outside this plugin.
        return $all===$expected;
    }
    private static function upgrade_settings(): void {
        $saved=(array)get_option('joyrent_settings',[]);$defaults=JR_Settings::defaults();
        foreach (['city','phone','telegram','deposit_ps5','deposit_ps4','delivery_green_fee','delivery_yellow_fee'] as $key) if (!array_key_exists($key,$saved)||$saved[$key]==='') $saved[$key]=$defaults[$key];
        if ($saved['city']===$defaults['city']&&empty($saved['city_ru'])) $saved['city_ru']=$defaults['city_ru'];
        if (($saved['base_controllers']??1)==1&&(!isset($saved['extra_controller_fee'])||$saved['extra_controller_fee']==='')) { $saved['base_controllers']=2;$saved['extra_controller_fee']=0; }
        $old=['delivery_text'=>'Вкажи місто та адресу у заявці. Ми перевіримо можливість доставки й узгодимо час отримання та повернення.','delivery_text_ru'=>'Укажи город и адрес в заявке. Мы проверим возможность доставки и согласуем время получения и возврата.'];
        foreach ($old as $key=>$text) if (!isset($saved[$key])||$saved[$key]===''||$saved[$key]===$text) $saved[$key]=$defaults[$key];
        update_option('joyrent_settings',$saved,false);
    }
    private static function reconcile_games(): void {
        $posts=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']);
        usort($posts,function($left,$right): int {
            $a=(array)get_post_meta($left->ID,'_jr_game',true);$b=(array)get_post_meta($right->ID,'_jr_game',true);
            $a_managed=get_post_meta($left->ID,'_jr_game_id',true)===($a['id']??null);$b_managed=get_post_meta($right->ID,'_jr_game_id',true)===($b['id']??null);
            return ($a_managed===$b_managed)?$left->ID<=>$right->ID:($a_managed?-1:1);
        });
        $seen=[]; $bundled=array_column(JR_Domain::catalog()['games'],null,'id');
        foreach ($posts as $post) {
            $meta=get_post_meta($post->ID,'_jr_game',true);
            if (!is_array($meta)||empty($meta['id'])) continue;
            $id=(string)$meta['id'];
            // Include all author metadata in the equality check, even fields outside this plugin.
            $all=get_post_meta($post->ID); unset($all['_edit_lock'],$all['_edit_last'],$all['_wp_old_slug'],$all['_wp_trash_meta_status'],$all['_wp_trash_meta_time']); ksort($all);
            $signature=wp_json_encode([$post->post_title,$post->post_content,$post->post_excerpt,$post->post_status,(int)$post->menu_order,$all]);
            if (!isset($seen[$id])) { $seen[$id]=$signature; continue; }
            if (isset($bundled[$id])&&get_post_meta($post->ID,'_jr_game_id',true)===$id&&$seen[$id]===$signature) {
                wp_delete_post($post->ID,true); continue;
            }
            // A differing record stays editable and published under a distinct stable ID.
            $meta['id']=$id.'-owner-'.$post->ID;
            update_post_meta($post->ID,'_jr_game',$meta); update_post_meta($post->ID,'_jr_game_id',$meta['id']);
        }
    }
    private static function faq_pages(): void {
        $data=json_decode((string)file_get_contents(__DIR__.'/../data/faq.json'),true);
        foreach (['uk'=>['faq','Питання про оренду'], 'ru'=>['faq-ru','Вопросы об аренде']] as $language=>[$slug,$title]) {
            if (get_page_by_path($slug)) continue; // Preserve any owner-authored page.
            $content='';
            foreach ($data[$language] ?? [] as $entry) $content.='<details><summary>'.esc_html($entry['question']).'</summary><p>'.esc_html($entry['answer']).'</p></details>';
            wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$content]);
        }
    }
    private static function legal_pages(): void {
        $pages=[
            'usloviya-arendy'=>['Условия аренды','<p>Отправка заявки не подтверждает бронирование и не требует оплаты. JOYRENT проверяет доступность консоли и игр и связывается с клиентом.</p><p>До подтверждения стороны согласуют комплектацию, даты и время получения и возврата, доставку, залог и ответственность за оборудование. Все условия согласуются до передачи консоли.</p><p>Продление требует проверки доступности. Не разбирайте оборудование и сразу сообщайте о неисправностях или повреждениях.</p>'],
            'konfidentsialnost'=>['Конфиденциальность','<p>JOYRENT получает имя, телефон, город, адрес и параметры аренды для обработки заявки и согласования передачи оборудования. Эти данные хранятся в магазине, доступ имеют уполномоченные сотрудники.</p><p>Форма не собирает пароли PSN, документы или платёжные данные и не подписывает на рекламные рассылки. Для уточнения или удаления данных обратитесь через контактный канал магазина.</p>'],
            'umovy-orendy'=>['Умови оренди','<p>Надсилання заявки не є підтвердженням бронювання та не потребує оплати. Після заявки JOYRENT перевіряє доступність консолі та ігор і зв’язується з клієнтом.</p><p>До підтвердження сторони узгоджують комплектацію, дати й час отримання та повернення, вартість доставки, заставу та відповідальність за обладнання. Усі умови мають бути погоджені до передачі консолі.</p><p>Продовження оренди потребує перевірки доступності. Не розбирайте обладнання; повідомляйте про пошкодження або несправності одразу.</p>'],
            'konfidentsiinist'=>['Конфіденційність','<p>JOYRENT отримує ім’я, телефон, місто, адресу й параметри оренди для обробки заявки та узгодження передачі обладнання. Ці дані зберігаються у магазині, доступ до них мають уповноважені працівники.</p><p>Форма не збирає паролі PSN, документи або платіжні дані та не підписує на рекламні розсилки. Для уточнення або видалення даних зверніться через контактний канал магазину.</p>']
        ];
        foreach ($pages as $slug=>[$title,$content]) {
            if (get_page_by_path($slug)) continue;
            $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$content],true);
            if ($slug==='konfidentsiinist'&&!is_wp_error($id)&&!get_option('wp_page_for_privacy_policy')) update_option('wp_page_for_privacy_policy',$id);
        }
    }
}
