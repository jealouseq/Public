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
            $product->set_description($tariff['description'].' Дати, зона доставки, комплектація та оформлення із заставою або за договором узгоджуються до оренди.');
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
        if (version_compare((string)get_option('joyrent_version','0'),'1.8.1','>=')||!class_exists('WooCommerce')) return;
        $lock=JR_Lock::acquire('joyrent_catalog_lock');
        if (!$lock) return;
        try {
            if (version_compare((string)get_option('joyrent_version','0'),'1.8.1','>=') ) return;
            $previous=(string)get_option('joyrent_version','0');
            if (version_compare($previous,'1.6.0','<')) {
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
                self::reconcile_games(); self::upgrade_settings();
            }
            self::upgrade_copy_settings();
            self::legal_pages(); self::faq_pages(); // Migrate exact previous defaults while preserving owner pages/settings.
            update_option('joyrent_version','1.8.1',false);
        } finally { JR_Lock::release('joyrent_catalog_lock',$lock); }
    }
    private static function upgrade_copy_settings(): void {
        $saved=(array)get_option('joyrent_settings',[]);
        $old=[
            'delivery_text'=>[
                'Доставляємо Одесою. Привеземо, підключимо та заберемо після оренди. Зону й час підтвердимо за адресою.',
                'Доставляємо Одесою. Зелена зона — 200 грн, жовта — 300 грн за доставку та повернення. Червона — за тарифом таксі в обидва боки. Від 7 днів зелена й жовта зони безкоштовні. Зону підтвердимо за адресою.',
            ],
            'delivery_text_ru'=>[
                'Доставляем по Одессе. Зелёная зона — 200 грн, жёлтая — 300 грн за доставку и возврат. Красная — по тарифу такси в обе стороны. От 7 дней зелёная и жёлтая зоны бесплатны. Зону подтвердим по адресу.',
            ],
        ];
        $defaults=JR_Settings::defaults();$changed=false;
        foreach ($old as $key=>$texts) if (in_array($saved[$key]??null,$texts,true)) {
            $saved[$key]=$defaults[$key];$changed=true;
        }
        if ($changed) update_option('joyrent_settings',$saved,false);
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
        $data=json_decode((string)file_get_contents(__DIR__.'/../data/faq.json'),true,512,JSON_THROW_ON_ERROR);
        foreach (['uk'=>['faq','Питання про оренду'], 'ru'=>['faq-ru','Вопросы об аренде']] as $language=>[$slug,$title]) {
            $content='';
            foreach ($data[$language] ?? [] as $entry) $content.='<details><summary>'.esc_html($entry['question']).'</summary><p>'.esc_html($entry['answer']).'</p></details>';
            self::seed_page($slug,$title,$content);
        }
    }
    private static function legal_pages(): void {
        $pages=json_decode((string)file_get_contents(__DIR__.'/../data/legal-pages.json'),true,512,JSON_THROW_ON_ERROR);
        foreach ($pages as $slug=>$page) self::seed_page($slug,$page['title'],$page['content'],$slug==='konfidentsiinist');
    }
    private static function seed_page(string $slug,string $title,string $content,bool $privacy=false): void {
        static $previous=null,$previous_recent=null,$previous_current=null,$neutral=null;
        if ($previous===null) {
            $previous=json_decode((string)file_get_contents(__DIR__.'/../data/page-seeds-1.6.json'),true,512,JSON_THROW_ON_ERROR);
            $previous_recent=json_decode((string)file_get_contents(__DIR__.'/../data/page-seeds-1.7.json'),true,512,JSON_THROW_ON_ERROR);
            $previous_current=json_decode((string)file_get_contents(__DIR__.'/../data/page-seeds-1.8.json'),true,512,JSON_THROW_ON_ERROR);
            $neutral=json_decode((string)file_get_contents(__DIR__.'/../data/page-defaults-neutral.json'),true,512,JSON_THROW_ON_ERROR);
        }
        $seed=$previous[$slug]??null;
        if (in_array($slug,['faq','faq-ru','umovy-orendy','usloviya-arendy'],true)&&!self::approved_page_conditions()) {
            // Neutral booking copy does not invent prices or included equipment for a custom-configured shop.
            $neutral_seed=$neutral[$slug]??null;
            if (!$neutral_seed) return;
            $title=$neutral_seed['title'];$content=$neutral_seed['content'];
        }
        $page=get_page_by_path($slug);
        if (!$page) {
            $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$content],true);
            if ($privacy&&!is_wp_error($id)&&!get_option('wp_page_for_privacy_policy')) update_option('wp_page_for_privacy_policy',$id);
            return;
        }
        // Only exact, published previous defaults migrate. Author text, titles, excerpts and drafts stay intact.
        foreach (array_filter([$seed,$previous_recent[$slug]??null,$previous_current[$slug]??null,$neutral[$slug]??null]) as $known_seed) {
            if ($page->post_status==='publish'&&$page->post_excerpt===''&&$page->post_title===$known_seed['title']&&$page->post_content===$known_seed['content']&&$page->post_content!==$content) {
                wp_update_post(['ID'=>$page->ID,'post_title'=>$title,'post_content'=>$content]);
                break;
            }
        }
    }
    private static function approved_page_conditions(): bool {
        $settings=JR_Settings::public();
        $approved=['city'=>'Одеса','cityRu'=>'Одесса','depositPs4'=>7500.0,'depositPs5'=>25000.0,'baseControllers'=>2,'extraControllerFee'=>0.0,'deliveryFee'=>null,'deliveryGreenFee'=>200.0,'deliveryYellowFee'=>300.0,'freeDeliveryFrom'=>7];
        foreach ($approved as $key=>$value) if (($settings[$key]??null)!==$value) return false;
        return true;
    }
}
