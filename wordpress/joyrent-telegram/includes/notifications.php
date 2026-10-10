<?php
if (!defined('ABSPATH')) exit;

/** Durable order-level outbox; queue arguments never contain a contact or a bot token. */
final class JRTG_Notifications {
    private const META='_joyrent_telegram_notification';
    private const HOOK='joyrent_telegram_deliver';
    private const WATCHDOG='joyrent_telegram_recover';
    private const GROUP='joyrent-telegram';
    private const LOCK_TTL=300;
    private const MAX_ATTEMPTS=4;
    private static array $admin_forms=[];

    public static function boot(): void {
        add_action('woocommerce_order_status_jr-request',[self::class,'queue'],20,2);
        add_action(self::HOOK,[self::class,'deliver']);
        add_action(self::WATCHDOG,[self::class,'deliver']);
        add_action('woocommerce_admin_order_data_after_order_details',[self::class,'admin_status'],20);
        add_action('admin_post_joyrent_telegram_retry',[self::class,'admin_retry']);
        add_action('admin_footer',[self::class,'admin_forms']);
    }

    /** A booking status callback must never throw, HTTP-call, or change booking acceptance. */
    public static function queue($order_id,$order=null): void {
        $id=(int)$order_id;$owner=false;
        try {
            if ($id<=0||!JRTG_Settings::ready()) return;
            $owner=JR_Lock::acquire(self::lock($id),self::LOCK_TTL);
            if (!$owner) return;
            $order=wc_get_order($id);
            $settings=JRTG_Settings::get();
            if (!self::eligible($order)||!self::recent($order,$settings)||self::state($order)!==[]) return;
            $state=self::queued($settings);
            self::save($order,$state);
            if (!self::schedule($id,time()+1)) self::fail($order,$state,'queue_unavailable');
        } catch (Throwable $e) {
            // Telegram must not let an exception delete/reject the core JOYRENT order.
        } finally { self::release($id,$owner); }
    }

    public static function deliver($order_id): void {
        $id=(int)$order_id;$owner=false;$order=null;$state=[];$attempting=false;
        try {
            if ($id<=0) return;
            $owner=JR_Lock::acquire(self::lock($id),self::LOCK_TTL);
            if (!$owner) {self::schedule($id,time()+30,self::WATCHDOG,false);return;}
            $order=wc_get_order($id);
            if (!$order instanceof WC_Order) return;
            $state=self::state($order);
            if (!$state||in_array($state['status']??'',['sent','failed','unknown'],true)) return;
            if (($state['status']??'')==='sending') {
                if ((int)($state['updated_at']??0)<=time()-self::LOCK_TTL) {
                    $state['status']='unknown';$state['error']='send_interrupted';self::save($order,$state);
                } else {
                    self::schedule($id,(int)$state['updated_at']+self::LOCK_TTL+30,self::WATCHDOG,false);
                }
                return;
            }
            if (!in_array($state['status']??'',['queued','retry'],true)) return;
            if ((int)($state['next_at']??0)>time()) return;
            if (!self::eligible($order)) {self::fail($order,$state,'order_unavailable');return;}
            $settings=JRTG_Settings::get();
            if (!JRTG_Settings::ready()) {self::fail($order,$state,'disabled');return;}
            if (!hash_equals((string)($state['config']??''),self::stamp($settings))) {self::fail($order,$state,'config_changed');return;}
            if ((int)($state['attempts']??0)>=self::MAX_ATTEMPTS) {self::fail($order,$state,'retry_limit');return;}
            $message=JRTG_Message::for_order($order);
            if (!hash_equals(self::stamp($settings),self::stamp(JRTG_Settings::get()))||!JRTG_Settings::ready()) {self::fail($order,$state,'config_changed');return;}
            $state['status']='sending';$state['attempts']=(int)($state['attempts']??0)+1;
            unset($state['error'],$state['next_at']);
            self::save($order,$state);
            // A separate hook survives a PHP process dying after the request was sent.
            self::schedule($id,time()+self::LOCK_TTL+30,self::WATCHDOG);
            $attempting=true;
            $result=JRTG_Api::send_message($message,$settings);
            $status=$result['status']??'unknown';
            if ($status==='sent'&&isset($result['message_id'])&&is_int($result['message_id'])&&$result['message_id']>0) {
                $state['status']='sent';$state['message_id']=$result['message_id'];unset($state['error']);
                self::save($order,$state);
                return;
            }
            $state['error']=self::safe_error($result['error']??'invalid_response');
            if ($status==='retry'&&$state['attempts']<self::MAX_ATTEMPTS) {
                $state['status']='retry';$state['next_at']=time()+max(1,min(3600,(int)($result['retry_after']??60)));
                self::save($order,$state);
                if (!self::schedule($id,$state['next_at'],self::HOOK,false)) self::fail($order,$state,'queue_unavailable');
            } else {
                $state['status']=$status==='failed'||$status==='retry'?'failed':'unknown';
                if ($status==='retry') $state['error']='retry_limit';
                self::save($order,$state);
            }
        } catch (Throwable $e) {
            if ($order instanceof WC_Order&&$state) {
                $state['status']=$attempting?'unknown':'failed';
                $state['error']=$attempting?'http_unknown':'queue_unavailable';
                try { self::save($order,$state); } catch (Throwable $ignored) {}
            }
        } finally { self::release($id,$owner); }
    }

    /** Explicit manager action only; an acknowledged sent notification can never be resent. */
    public static function resend($order_id): bool {
        $id=(int)$order_id;$owner=false;
        try {
            if ($id<=0||!JRTG_Settings::ready()) return false;
            $owner=JR_Lock::acquire(self::lock($id),self::LOCK_TTL);
            if (!$owner) return false;
            $order=wc_get_order($id);
            if (!self::eligible($order)) return false;
            $prior=self::state($order);
            $status=$prior['status']??'';
            if ($status==='sent'||(in_array($status,['queued','retry'],true)&&!self::abandoned($id,$prior))) return false;
            if ($status==='sending'&&(int)($prior['updated_at']??0)>time()-self::LOCK_TTL) return false;
            $state=self::queued(JRTG_Settings::get());
            $state['manual_retries']=(int)($prior['manual_retries']??0)+1;
            self::save($order,$state);
            if (!self::schedule($id,time()+1)) {self::fail($order,$state,'queue_unavailable');return false;}
            return true;
        } catch (Throwable $e) { return false; }
        finally { self::release($id,$owner); }
    }

    public static function admin_status(WC_Order $order): void {
        if (!self::eligible($order)||!current_user_can('manage_woocommerce')) return;
        $state=self::state($order);$status=$state['status']??'';
        $abandoned=self::abandoned($order->get_id(),$state);
        $labels=['queued'=>'В очереди','retry'=>'Ожидает повторной попытки','sending'=>'Отправляется; результат ещё не подтверждён','sent'=>'Telegram подтвердил отправку','failed'=>'Не отправлено','unknown'=>'Результат неизвестен — проверьте Telegram'];
        echo '<div class="form-field form-field-wide"><p><strong>JOYRENT Telegram:</strong> '.esc_html($abandoned?'Очередь остановлена — доступен ручной повтор':($labels[$status]??'Не отправлялось')).'</p>';
        if (!empty($state['error'])) echo '<p>'.esc_html(JRTG_Settings::error_label(self::safe_error($state['error']))).'</p>';
        if (($abandoned||!in_array($status,['sent','queued','retry'],true))&&JRTG_Settings::ready()) {
            $uncertain=in_array($status,['unknown','sending'],true);
            if ($status==='sending'&&(int)($state['updated_at']??0)>time()-self::LOCK_TTL) {echo '</div>';return;}
            $form='jrtg-retry-'.$order->get_id();
            self::$admin_forms[$order->get_id()]=$uncertain;
            if ($uncertain) {
                echo '<p>Telegram мог получить сообщение. Проверьте чат перед повтором: повтор может создать дубль.</p>';
                echo '<p><label><input type="checkbox" name="uncertain_confirmed" value="1" form="'.esc_html($form).'" required> Я проверил чат; понимаю, что повтор может создать дубль.</label></p>';
            }
            echo '<button type="submit" class="button" form="'.esc_html($form).'">'.esc_html($uncertain?'Проверено: повторить, возможен дубль':'Отправить уведомление в Telegram').'</button>';
        }
        echo '</div>';
    }

    /** Separate footer forms avoid invalid nested forms inside the WooCommerce editor. */
    public static function admin_forms(): void {
        if (!current_user_can('manage_woocommerce')) return;
        foreach (self::$admin_forms as $id=>$uncertain) {
            echo '<form id="jrtg-retry-'.(int)$id.'" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="joyrent_telegram_retry"><input type="hidden" name="order_id" value="'.(int)$id.'">';
            wp_nonce_field('joyrent_telegram_retry_'.$id);
            echo '</form>';
        }
        self::$admin_forms=[];
    }

    public static function admin_retry(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('Недостаточно прав.', '', ['response'=>403]);
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') wp_die('Используйте кнопку в карточке брони.', '', ['response'=>405]);
        $id=absint($_POST['order_id']??0);
        check_admin_referer('joyrent_telegram_retry_'.$id);
        $order=wc_get_order($id);
        if (!self::eligible($order)) wp_die('Бронь не найдена.');
        $state=self::state($order);
        if (in_array($state['status']??'',['sending','unknown'],true)&&($_POST['uncertain_confirmed']??'')!=='1') wp_die('Проверьте Telegram: повтор может создать дубль.');
        self::resend($id);
        wp_safe_redirect($order->get_edit_order_url());exit;
    }

    private static function eligible($order): bool {
        return $order instanceof WC_Order&&$order->get_created_via()==='joyrent'&&$order->get_status()==='jr-request'
            &&(string)$order->get_meta('_joyrent_request_key')!==''&&$order->get_meta('_joyrent_completed')==='yes';
    }
    private static function recent(WC_Order $order,array $settings): bool {
        $created=$order->get_date_created();$since=(int)($settings['enabled_since']??0);
        return $created&&$since>0&&$created->getTimestamp()>=$since;
    }
    private static function stamp(array $settings): string {
        return hash('sha256',(string)($settings['token']??'').'|'.(string)($settings['chat_id']??'').'|'.(int)($settings['enabled_since']??0).'|'.(string)($settings['revision']??''));
    }
    private static function queued(array $settings): array {
        return ['status'=>'queued','attempts'=>0,'updated_at'=>time(),'next_at'=>time(),'config'=>self::stamp($settings)];
    }
    private static function state(WC_Order $order): array {
        $state=$order->get_meta(self::META);return is_array($state)?$state:[];
    }
    private static function save(WC_Order $order,array $state): void {
        $state['updated_at']=time();$order->update_meta_data(self::META,$state);$order->save_meta_data();
    }
    private static function fail(WC_Order $order,array $state,string $error): void {
        $state['status']='failed';$state['error']=self::safe_error($error);unset($state['next_at']);
        self::save($order,$state);
    }
    private static function schedule(int $id,int $at,string $hook=self::HOOK,bool $unique=true): bool {
        if (function_exists('as_schedule_single_action')) {
            try {
                $action=as_schedule_single_action($at,$hook,[$id],self::GROUP,$unique);
                if ($action) return true;
                if ($unique&&function_exists('as_has_scheduled_action')&&as_has_scheduled_action($hook,[$id],self::GROUP)) return true;
            } catch (Throwable $ignored) {}
        }
        try {
            if (wp_next_scheduled($hook,[$id])) return true;
            $scheduled=wp_schedule_single_event($at,$hook,[$id],true);
            return $scheduled!==false&&!is_wp_error($scheduled);
        } catch (Throwable $ignored) { return false; }
    }
    private static function abandoned(int $id,array $state): bool {
        if (!in_array($state['status']??'',['queued','retry'],true)||(int)($state['updated_at']??0)>time()-self::LOCK_TTL) return false;
        try {
            foreach ([self::HOOK,self::WATCHDOG] as $hook) {
                if (function_exists('as_has_scheduled_action')&&as_has_scheduled_action($hook,[$id],self::GROUP)) return false;
                if (wp_next_scheduled($hook,[$id])) return false;
            }
            return true;
        } catch (Throwable $e) { return false; }
    }

    private static function lock(int $id): string { return 'jrtg_notification_'.$id; }
    private static function release(int $id,$owner): void {
        if (!$owner) return;
        try {JR_Lock::release(self::lock($id),$owner);} catch (Throwable $ignored) {}
    }
    private static function safe_error($error): string {
        $allowed=['not_configured','invalid_message','http_unknown','invalid_response','invalid_ack','rate_limited','telegram_server_error','bot_unauthorized','chat_forbidden','bad_request','bot_webhook_conflict','telegram_error','disabled','config_changed','queue_unavailable','send_interrupted','retry_limit','order_unavailable'];
        return is_string($error)&&in_array($error,$allowed,true)?$error:'invalid_response';
    }
}
