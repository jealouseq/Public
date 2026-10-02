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
        foreach ($data['tariffs'] as $console=>&$tariffs) foreach ($tariffs as &$tariff) {
            $product=self::product($console,$tariff['days']);
            if ($product&&$product->get_status()==='publish'&&$product->get_price()!==''&&(float)$product->get_price()>0) $tariff['price']=(float)$product->get_price();
            else $ready=false;
        }
        unset($tariff,$tariffs);
        return ['tariffs'=>$data['tariffs'],'games'=>JR_Games::records(),'settings'=>JR_Settings::public(),'currency'=>'UAH','acceptingRequests'=>$ready];
    }
    public static function seed(): void {
        if (!class_exists('WooCommerce')) return;
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
        foreach (JR_Domain::catalog()['games'] as $game) {
            $existing=get_posts(['post_type'=>'joyrent_game','post_status'=>'any','numberposts'=>1,'meta_key'=>'_jr_game_id','meta_value'=>$game['id'],'fields'=>'ids']);
            if ($existing) continue;
            $id=wp_insert_post(['post_type'=>'joyrent_game','post_status'=>'publish','post_title'=>$game['title'],'post_content'=>$game['description'],'post_name'=>$game['id']],true);
            if (!is_wp_error($id)) { update_post_meta($id,'_jr_game_id',$game['id']); update_post_meta($id,'_jr_game',$game); }
        }
        self::legal_pages(); update_option('joyrent_seeded',true,false);
    }
    public static function upgrade(): void {
        if (get_option('joyrent_version')==='1.1.0'||!class_exists('WooCommerce')) return;
        self::legal_pages(); // Add missing translations without rewriting owner pages or settings.
        update_option('joyrent_version','1.1.0',false);
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
