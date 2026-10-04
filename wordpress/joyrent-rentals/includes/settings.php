<?php
if (!defined('ABSPATH')) exit;

final class JR_Settings {
    public static function defaults(): array {
        return ['city'=>'Одеса','city_ru'=>'Одесса','phone'=>'+380996669946','email'=>'','notification_email'=>'','search_indexing'=>false,'telegram'=>'https://t.me/joyrent_od','delivery_fee'=>'','delivery_green_fee'=>200,'delivery_yellow_fee'=>300,'deposit_ps5'=>25000,'deposit_ps4'=>7500,'base_controllers'=>2,'extra_controller_fee'=>0,'pickup'=>false,'free_delivery_from'=>7,'max_games'=>100,'delivery_text_ru'=>'Доставляем по Одессе. Привезём, подключим и заберём после аренды. Зону и время подтвердим по адресу.', 'delivery_text'=>'Доставляємо по Одесі. Привеземо, підключимо та заберемо після оренди. Зону й час підтвердимо за адресою.'];
    }
    public static function get(): array {
        $defaults=self::defaults(); $saved=(array)get_option('joyrent_settings',[]);
        if (isset($saved['city'])&&$saved['city']!==$defaults['city']&&empty($saved['city_ru'])) $defaults['city_ru']='';
        if (isset($saved['delivery_text'])&&$saved['delivery_text']!==$defaults['delivery_text']&&empty($saved['delivery_text_ru'])) $defaults['delivery_text_ru']='';
        return array_merge($defaults,$saved);
    }
    private static function amount(mixed $value): ?float { return $value === '' || $value === null ? null : max(0,(float)$value); }
    public static function search_indexing(): bool { return in_array(self::get()['search_indexing'],[true,1,'1'],true); }
    public static function public(): array {
        $s=self::get();
        return ['city'=>$s['city'],'cityRu'=>$s['city_ru'],'phone'=>$s['phone'],'email'=>$s['email'],'telegram'=>$s['telegram'],'deliveryFee'=>self::amount($s['delivery_fee']),'deliveryGreenFee'=>self::amount($s['delivery_green_fee']),'deliveryYellowFee'=>self::amount($s['delivery_yellow_fee']),'depositPs5'=>self::amount($s['deposit_ps5']),'depositPs4'=>self::amount($s['deposit_ps4']),'baseControllers'=>(int)$s['base_controllers'],'extraControllerFee'=>self::amount($s['extra_controller_fee']),'pickup'=>(bool)$s['pickup'],'freeDeliveryFrom'=>(int)$s['free_delivery_from'],'maxGames'=>max(1,min(100,(int)$s['max_games'])),'deliveryText'=>$s['delivery_text'],'deliveryTextRu'=>$s['delivery_text_ru']];
    }
    public static function register(): void { register_setting('joyrent','joyrent_settings',['type'=>'array','sanitize_callback'=>[self::class,'sanitize'],'default'=>self::defaults()]); }
    public static function sanitize(mixed $input): array {
        $input=is_array($input)?$input:[]; $result=self::defaults();
        foreach (['city','city_ru','phone','delivery_text','delivery_text_ru'] as $key) $result[$key]=sanitize_text_field(is_scalar($input[$key]??null)?(string)$input[$key]:'');
        $result['email']=sanitize_email(is_string($input['email']??null)?$input['email']:'');
        $result['notification_email']=sanitize_email(is_string($input['notification_email']??null)?$input['notification_email']:'');
        $result['telegram']=esc_url_raw(is_string($input['telegram']??null)?$input['telegram']:'',['https']);
        foreach (['delivery_fee','delivery_green_fee','delivery_yellow_fee','deposit_ps5','deposit_ps4','extra_controller_fee'] as $key) {
            $value=is_scalar($input[$key]??null)?trim((string)$input[$key]):'';
            $result[$key]=$value!==''&&preg_match('/^\d+(?:\.\d{1,2})?$/',$value)?min(100000,(float)$value):'';
        }
        $result['base_controllers']=((int)($input['base_controllers']??2)===2)?2:1;
        $result['free_delivery_from']=max(1,min(30,(int)($input['free_delivery_from']??7)));
        $result['max_games']=max(1,min(100,(int)($input['max_games']??100)));
        $result['pickup']=!empty($input['pickup']);
        $result['search_indexing']=in_array($input['search_indexing']??false,[true,1,'1'],true);
        return $result;
    }
    public static function menu(): void { add_submenu_page('woocommerce','JOYRENT','JOYRENT','manage_woocommerce','joyrent',[self::class,'page']); }
    public static function page(): void {
        if (!current_user_can('manage_woocommerce')) return;
        $s=self::get();
        $fields=['city'=>['Місто доставки','text'],'phone'=>['Телефон магазину','text'],'email'=>['Email магазину','email'],'telegram'=>['Посилання Telegram (https)','url'],'delivery_fee'=>['Доставка для коротких тарифів, грн','number'],'deposit_ps5'=>['Застава PS5, грн','number'],'deposit_ps4'=>['Застава PS4, грн','number'],'base_controllers'=>['Геймпадів у базовому комплекті (1 або 2)','number'],'extra_controller_fee'=>['Додатковий геймпад за весь термін, грн','number'],'free_delivery_from'=>['Безкоштовна доставка від, днів','number'],'delivery_text'=>['Опис доставки (українською)','text'],'delivery_text_ru'=>['Опис доставки (російською)','text']];
        $fields['max_games']=['Максимум бажаних ігор (1–100)','number'];
        $fields['notification_email']=['Одержувач сповіщень (лише для адміністратора)','email'];
        $fields['city_ru']=['Місто доставки (російською)','text'];
        $fields['delivery_green_fee']=['Зелена зона: доставка та повернення, грн','number'];
        $fields['delivery_yellow_fee']=['Жовта зона: доставка та повернення, грн','number'];
        echo '<div class="wrap"><h1>JOYRENT — налаштування оренди</h1><p>Порожні суми означають «узгодимо», а не нуль. Заявки очікують ручного підтвердження; автоматичного бронювання чи оплати немає.</p><p>Сповіщення заявок: '.esc_html(JR_Orders::notification_recipient() ?: 'одержувач не налаштований').'. Якщо Email магазину порожній, використовується одержувач «Нове замовлення» WooCommerce, потім Email адміністратора. Помилки та повторна спроба доступні в замовленні.</p><form method="post" action="options.php">';
        settings_fields('joyrent'); echo '<table class="form-table">';
        foreach ($fields as $key=>[$label,$type]) echo '<tr><th><label for="jr-'.esc_attr($key).'">'.esc_html($label).'</label></th><td><input class="regular-text" id="jr-'.esc_attr($key).'" name="joyrent_settings['.esc_attr($key).']" type="'.esc_attr($type).'" '.($type==='number'?'min="0" step="0.01" ':'').'value="'.esc_attr((string)$s[$key]).'"></td></tr>';
        echo '<tr><th>Самовивіз</th><td><label><input type="checkbox" name="joyrent_settings[pickup]" value="1" '.checked($s['pickup'],true,false).'> Дозволити вибір самовивозу</label></td></tr>';
        echo '<tr><th>Пошукові системи</th><td><label><input type="checkbox" name="joyrent_settings[search_indexing]" value="1" '.checked(self::search_indexing(),true,false).'> Дозволити індексацію сайту</label><p class="description">Увімкни після перенесення на основний домен.</p></td></tr></table>';
        submit_button('Зберегти умови'); echo '</form><hr><h2>Каталог</h2><p>Створити відсутні тарифи та початкову добірку ігор. Існуючі ціни й тексти не перезаписуються. Наявність ігор редагуйте в меню «Ігри JOYRENT».</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="joyrent_seed">';
        wp_nonce_field('joyrent_seed'); submit_button('Додати початковий каталог','secondary'); echo '</form></div>';
    }
    public static function seed(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('Недостатньо прав.');
        check_admin_referer('joyrent_seed');
        if (class_exists('WooCommerce')) JR_Store::seed();
        wp_safe_redirect(admin_url('admin.php?page=joyrent')); exit;
    }
}
