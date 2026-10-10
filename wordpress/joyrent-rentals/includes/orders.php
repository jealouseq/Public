<?php
if (!defined('ABSPATH')) exit;

final class JR_Orders {
    public static function register_status(): void {
        register_post_status('wc-jr-request',['label'=>'Бронювання','public'=>true,'exclude_from_search'=>false,'show_in_admin_all_list'=>true,'show_in_admin_status_list'=>true,'label_count'=>_n_noop('Бронювання <span class="count">(%s)</span>','Бронювання <span class="count">(%s)</span>','joyrent-rentals')]);
    }
    public static function statuses(array $statuses): array { $statuses['wc-jr-request']='Бронювання'; return $statuses; }
    public static function existing(string $key, string $fingerprint, ?string $intent_fingerprint = null): ?array {
        $orders=wc_get_orders(['limit'=>1,'joyrent_request_key'=>$key,'meta_query'=>[['key'=>'_joyrent_request_key','value'=>$key]]]);
        if (!$orders) return null;
        $order=$orders[0];
        $matches=hash_equals((string)$order->get_meta('_joyrent_fingerprint'),$fingerprint);
        if (!$matches&&$intent_fingerprint!==null) {
            $stored=(string)$order->get_meta('_joyrent_intent_fingerprint');
            if ($stored==='') {
                // Recover pre-upgrade intent from the durable order, without current eligibility rules.
                $stored=JR_Domain::intent_fingerprint([
                    'console'=>(string)$order->get_meta('_joyrent_console'),'days'=>(int)$order->get_meta('_joyrent_days'),
                    'startDate'=>(string)$order->get_meta('_joyrent_start_date'),'controllers'=>(int)$order->get_meta('_joyrent_controllers'),
                    'gameIds'=>(array)$order->get_meta('_joyrent_game_ids'),'method'=>(string)$order->get_meta('_joyrent_method'),
                    'name'=>$order->get_billing_first_name(),'phone'=>$order->get_billing_phone(),'address'=>$order->get_billing_address_1(),
                    'securityMode'=>$order->get_meta('_joyrent_security_mode')?:'deposit','requestedGame'=>(string)$order->get_meta('_joyrent_requested_game'),
                    'telegram'=>(string)$order->get_meta('_joyrent_telegram'),
                ]);
            }
            $matches=hash_equals($stored,$intent_fingerprint);
        }
        if (!$matches) throw new InvalidArgumentException('Параметри бронювання змінилися. Онови сторінку та спробуй ще раз.');
        if ($order->get_meta('_joyrent_completed')!=='yes') throw new RuntimeException('Заявка потребує перевірки магазином.');
        return ['reference'=>'JR-'.$order->get_order_number(),'rentalAmount'=>(float)$order->get_meta('_joyrent_rental_amount'),'status'=>'awaiting_confirmation'];
    }
    public static function create(array $data, string $key, string $fingerprint): array {
        $product=JR_Store::product($data['console'],$data['days']);
        if (!JR_Store::requestable($product)) throw new RuntimeException('Цей комплект тимчасово недоступний.');
        $amount=(float)$product->get_price(); $settings=JR_Settings::public();
        $order=new WC_Order();
        $order->set_status('checkout-draft'); $order->set_created_via('joyrent');
        // A durable key is stored in the first save, before customer/order items.
        // An interrupted worker can never create a second order on retry.
        $order->update_meta_data('_joyrent_request_key',$key);
        $order->update_meta_data('_joyrent_fingerprint',$fingerprint);
        $order->update_meta_data('_joyrent_intent_fingerprint',JR_Domain::intent_fingerprint($data));
        $order->save();
        try {
            $order->set_currency('UAH'); $order->set_billing_first_name($data['name']); $order->set_billing_phone($data['phone']); $order->set_billing_address_1($data['address']); $order->set_billing_country('UA');
            $item_id=$order->add_product($product,1,['subtotal'=>$amount,'total'=>$amount]);
            $item=$order->get_item($item_id);
            foreach (['Консоль'=>strtoupper($data['console']),'Термін'=>$data['days'].' дн.','Отримання'=>$data['startDate'],'Повернення'=>$data['returnDate'],'Геймпади'=>$data['controllers']] as $key=>$value) $item->add_meta_data($key,$value,true);
            $item->save();
            $free_delivery_candidate=$data['method']==='delivery'&&$data['days']>=$settings['freeDeliveryFrom'];
            // An address has no confirmed zone at request time; client zone claims are not authoritative.
            $delivery=$data['method']==='pickup'?0:($free_delivery_candidate?null:$settings['deliveryFee']);
            $extra_count=max(0,$data['controllers']-$settings['baseControllers']);
            $extra=$extra_count?($settings['extraControllerFee']===null?null:$settings['extraControllerFee']*$extra_count):0;
            foreach (['Доставка'=>$delivery,'Додатковий геймпад'=>$extra] as $title=>$value) if ($value!==null&&$value>0) {
                $fee=new WC_Order_Item_Fee(); $fee->set_name($title); $fee->set_amount($value); $fee->set_total($value); $fee->set_tax_status('none'); $order->add_item($fee);
            }
            $security_mode=$data['securityMode']??'deposit';
            // A contract is a request for manual document verification, never an automatic zero deposit.
            $deposit=$security_mode==='contract'?null:($data['console']==='ps5'?$settings['depositPs5']:$settings['depositPs4']);
            foreach (['console'=>$data['console'],'days'=>$data['days'],'start_date'=>$data['startDate'],'return_date'=>$data['returnDate'],'controllers'=>$data['controllers'],'game_ids'=>$data['gameIds'],'method'=>$data['method'],'deposit'=>$deposit===null?'pending':$deposit,'delivery'=>$delivery===null?'pending':$delivery,'extra_controller'=>$extra===null?'pending':$extra,'consent'=>'yes','consent_version'=>'1.0','language'=>$data['language']??'uk'] as $key=>$value) $order->update_meta_data('_joyrent_'.$key,$value);
            $order->update_meta_data('_joyrent_security_mode',$security_mode);
            $order->update_meta_data('_joyrent_security_status',$security_mode==='contract'?'pending_document_verification':'pending_confirmation');
            if (($data['requestedGame']??'')!=='') $order->update_meta_data('_joyrent_requested_game',$data['requestedGame']);
            if (($data['telegram']??'')!=='') $order->update_meta_data('_joyrent_telegram',$data['telegram']);
            if ($free_delivery_candidate) {
                $order->update_meta_data('_joyrent_delivery_free_eligibility','pending_zone_confirmation');
                $order->add_order_note('Від '.$settings['freeDeliveryFrom'].' днів безкоштовна доставка можлива лише у зеленій або жовтій зоні після підтвердження адреси магазином. Червона зона — за тарифом таксі в обидва боки.');
            }
            $order->add_order_note('Мова клієнта: '.(($data['language']??'uk')==='ru'?'Російська':'Українська'));
            $order->add_order_note('Оформлення: '.self::security_label($security_mode).'.');
            if (($data['requestedGame']??'')!=='') $order->add_order_note('Запит гри поза каталогом: '.esc_html($data['requestedGame']));
            $order->add_order_note('Заявка JOYRENT: доступність консолі, ігор, адреса доставки та умови застави потребують підтвердження. Оплату не отримано. Бажані ігри: '.implode(', ',$data['gameIds']));
            $order->set_customer_note('Дата отримання: '.$data['startDate'].'. Повернення: '.$data['returnDate'].'. Геймпадів: '.$data['controllers'].'. Спосіб отримання: '.$data['method']);
            $order->update_meta_data('_joyrent_rental_amount',$amount);
            $order->update_meta_data('_joyrent_completed','yes');
            $order->set_status('jr-request');
            $order->calculate_totals(false); $order->save();
            return ['reference'=>'JR-'.$order->get_order_number(),'rentalAmount'=>$amount,'status'=>'awaiting_confirmation'];
        } catch (Throwable $exception) { $order->delete(true); throw $exception; }
    }
    public static function notification_recipient(): string {
        $settings=JR_Settings::get(); $woo=(array)get_option('woocommerce_new_order_settings',[]);
        foreach ([$settings['notification_email']??'', $settings['email']??'', $woo['recipient']??'', get_option('admin_email','')] as $candidate) {
            $emails=array_filter(array_map('trim',explode(',',(string)$candidate)),fn($email)=>is_email($email));
            if ($emails) return implode(', ',$emails);
        }
        return '';
    }
    private static function security_label(string $mode): string {
        return $mode==='contract'?'За договором — очікує перевірки документів і погодження':'Застава — повертається після перевірки комплекту';
    }
    private static function deposit_label(WC_Order $order): string {
        if ($order->get_meta('_joyrent_security_mode')==='contract') return 'Рішення після перевірки документів; без застави ще не погоджено';
        $deposit=$order->get_meta('_joyrent_deposit');
        return $deposit===''||$deposit==='pending'?'Узгодимо до оренди':number_format((float)$deposit,0,',',' ').' грн / повертається';
    }
    public static function notify(int $id, bool $retry_uncertain = false): bool {
        $lock_name='jr_notification_'.$id; $owner=JR_Lock::acquire($lock_name,300);
        if (!$owner) return false;
        try {
            $order=wc_get_order($id);
            if (!$order||$order->get_meta('_joyrent_completed')!=='yes') return false;
            $status=(string)$order->get_meta('_joyrent_notification_status');
            if ($status==='sent') return true;
            // An interrupted send has an uncertain result; only a reviewed admin retry may resend.
            if ($status==='sending'&&!$retry_uncertain) return false;
            $recipient=self::notification_recipient();
            $order->update_meta_data('_joyrent_notification_status','sending');
            $order->update_meta_data('_joyrent_notification_attempts',(int)$order->get_meta('_joyrent_notification_attempts')+1);
            $order->save();
            $subject='JOYRENT: нова заявка JR-'.$order->get_order_number();
            $body="Нова заявка очікує ручного підтвердження. Оплату не отримано.\n\n";
            foreach (['Ім’я'=>$order->get_billing_first_name(),'Телефон'=>$order->get_billing_phone(),'Адреса'=>$order->get_billing_address_1(),'Консоль'=>strtoupper((string)$order->get_meta('_joyrent_console')),'Термін'=>$order->get_meta('_joyrent_days').' дн.','Отримання'=>$order->get_meta('_joyrent_start_date'),'Повернення'=>$order->get_meta('_joyrent_return_date'),'Геймпади'=>$order->get_meta('_joyrent_controllers'),'Бажані ігри'=>implode(', ',(array)$order->get_meta('_joyrent_game_ids')),'Мова'=>$order->get_meta('_joyrent_language')] as $label=>$value) $body.=$label.': '.$value."\n";
            if ($order->get_meta('_joyrent_telegram')!=='') $body.='Telegram: '.sanitize_text_field((string)$order->get_meta('_joyrent_telegram'))."\n";
            $body.='Оформлення: '.self::security_label((string)$order->get_meta('_joyrent_security_mode'))."\n";
            $body.='Грошова застава: '.self::deposit_label($order)."\n";
            if ($order->get_meta('_joyrent_requested_game')!=='') $body.='Запит гри поза каталогом: '.wp_strip_all_tags((string)$order->get_meta('_joyrent_requested_game'))."\n";
            $body.="\n".$order->get_edit_order_url();
            try { $sent=$recipient!==''&&wp_mail($recipient,$subject,$body,['Content-Type: text/plain; charset=UTF-8']); }
            catch (Throwable $e) { $sent=false; }
            $order->update_meta_data('_joyrent_notification_status',$sent?'sent':'failed');
            $order->update_meta_data('_joyrent_notification_updated',gmdate('c'));
            $order->add_order_note($sent?'JOYRENT: сповіщення передано поштовій службі.':'JOYRENT: не вдалося передати сповіщення. Перевірте одержувача та пошту; повторіть спробу в блоці JOYRENT.');
            $order->save(); return $sent;
        } catch (Throwable $e) { return false; }
        finally { JR_Lock::release($lock_name,$owner); }
    }
    public static function notification_admin(WC_Order $order): void {
        if (!$order->get_meta('_joyrent_request_key')||!current_user_can('manage_woocommerce')) return;
        echo '<p class="form-field form-field-wide"><strong>JOYRENT оформлення:</strong> '.esc_html(self::security_label((string)$order->get_meta('_joyrent_security_mode'))).'<br><strong>Грошова застава:</strong> '.esc_html(self::deposit_label($order)).'</p>';
        if ($order->get_meta('_joyrent_requested_game')!=='') echo '<p class="form-field form-field-wide"><strong>Запит гри поза каталогом:</strong> '.esc_html((string)$order->get_meta('_joyrent_requested_game')).'</p>';
        $telegram=(string)$order->get_meta('_joyrent_telegram');
        if ($telegram!=='') {
            $contact=esc_html($telegram);
            try {
                $normalized=JR_Domain::telegram($telegram);
                if ($normalized!=='') $contact='<a href="'.esc_url('https://t.me/'.substr($normalized,1)).'" target="_blank" rel="noopener noreferrer">'.esc_html($normalized).'</a>';
            } catch (InvalidArgumentException $e) {}
            echo '<p class="form-field form-field-wide"><strong>Telegram клієнта:</strong> '.$contact.'</p>';
        }
        $status=(string)$order->get_meta('_joyrent_notification_status');
        $labels=['sent'=>'Передано поштовій службі','failed'=>'Помилка — перевірте поштові налаштування','sending'=>'Результат невідомий — перевірте доставку перед повтором'];
        echo '<p class="form-field form-field-wide"><strong>JOYRENT сповіщення:</strong> '.esc_html($labels[$status]??'Ще не надіслано');
        if ($status!=='sent') {
            $url=wp_nonce_url(add_query_arg(['action'=>'joyrent_notification_retry','order_id'=>$order->get_id()],admin_url('admin-post.php')),'joyrent_notification_retry_'.$order->get_id());
            echo '<br><a class="button" href="'.esc_url($url).'">Повторити сповіщення</a>';
        }
        echo '</p>';
    }
    public static function notification_retry(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('Недостатньо прав.');
        $id=absint($_GET['order_id']??0); check_admin_referer('joyrent_notification_retry_'.$id);
        $order=wc_get_order($id);
        if (!$order||!$order->get_meta('_joyrent_request_key')) wp_die('Заявку не знайдено.');
        self::notify($id,true); wp_safe_redirect($order->get_edit_order_url()); exit;
    }
}
