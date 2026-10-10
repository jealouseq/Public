<?php
if (!defined('ABSPATH')) exit;

/** Durable fan-out: acknowledgments belong to recipients, never to an entire broadcast. */
final class JRTG_Notifications {
    private const META='_joyrent_telegram_broadcast';
    private const HOOK='joyrent_telegram_broadcast';
    private const WATCHDOG='joyrent_telegram_broadcast_recover';
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

    /** Never throw or perform HTTP in the booking acceptance callback. */
    public static function queue($order_id,$order=null): void {
        $id=(int)$order_id;$owner=false;
        try {
            if ($id<=0||!JRTG_Settings::ready()) return;
            $owner=JR_Lock::acquire(self::lock($id),self::LOCK_TTL);
            if (!$owner) return;
            $order=wc_get_order($id);$settings=JRTG_Settings::get();
            if (!self::eligible($order)||!self::recent($order,$settings)||self::state($order)!==[]) return;
            $recipients=[];
            foreach (JRTG_Subscriptions::all() as $chat=>$subscriber) {
                $recipients[(string)$chat]=['generation'=>$subscriber['generation'],'status'=>'queued','attempts'=>0,'updated_at'=>time(),'next_at'=>time()];
            }
            if (!$recipients) return;
            $state=['status'=>'queued','config'=>self::stamp($settings),'recipients'=>$recipients];
            self::save($order,$state);
            if (!self::schedule($id,time()+1)) self::fail_pending($order,$state,'queue_unavailable');
        } catch (Throwable $ignored) {
            // The core catches exceptions by deleting the order: keep all connector failures here.
        } finally {self::release($id,$owner);}
    }

    /** One HTTP request per worker keeps execution bounded even with many subscribers. */
    public static function deliver($order_id): void {
        $id=(int)$order_id;$owner=false;$order=null;$state=[];$chat=null;$attempting=false;
        try {
            if ($id<=0) return;
            $owner=JR_Lock::acquire(self::lock($id),self::LOCK_TTL);
            if (!$owner) {self::schedule($id,time()+30,self::WATCHDOG,false);return;}
            $order=wc_get_order($id);
            if (!$order instanceof WC_Order) return;
            $state=self::state($order);
            if (empty($state['recipients'])) return;
            if (!self::eligible($order)) {self::fail_pending($order,$state,'order_unavailable');return;}
            $settings=JRTG_Settings::get();
            if (!JRTG_Settings::ready()) {self::fail_pending($order,$state,'disabled');return;}
            if (!hash_equals((string)($state['config']??''),self::stamp($settings))) {self::fail_pending($order,$state,'config_changed');return;}
            $subscribers=JRTG_Subscriptions::all();
            foreach ($state['recipients'] as $recipient=>$entry) {
                if (($entry['status']??'')==='sending') {
                    if ((int)($entry['updated_at']??0)<=time()-self::LOCK_TTL) {
                        $state['recipients'][$recipient]['status']='unknown';
                        $state['recipients'][$recipient]['error']='send_interrupted';
                    }
                    continue;
                }
                if (!in_array($entry['status']??'',['queued','retry'],true)) continue;
                if (!isset($subscribers[$recipient])||!hash_equals((string)$entry['generation'],(string)$subscribers[$recipient]['generation'])) {
                    $state['recipients'][$recipient]['status']='failed';$state['recipients'][$recipient]['error']='unsubscribed';continue;
                }
                if ((int)($entry['attempts']??0)>=self::MAX_ATTEMPTS) {
                    $state['recipients'][$recipient]['status']='failed';$state['recipients'][$recipient]['error']='retry_limit';continue;
                }
                if ($chat===null&&(int)($entry['next_at']??0)<=time()) $chat=(string)$recipient;
            }
            self::save($order,$state);
            if ($chat===null) {self::continue_queue($id,$order,$state);return;}
            $message=JRTG_Message::for_order($order);
            // Re-read configuration and membership immediately before starting external delivery.
            $current=JRTG_Subscriptions::all();
            if (!JRTG_Settings::ready()||!hash_equals(self::stamp($settings),self::stamp(JRTG_Settings::get()))) {
                self::fail_pending($order,$state,'config_changed');return;
            }
            if (!isset($current[$chat])||!hash_equals($state['recipients'][$chat]['generation'],$current[$chat]['generation'])) {
                $state['recipients'][$chat]['status']='failed';$state['recipients'][$chat]['error']='unsubscribed';
                self::save($order,$state);self::continue_queue($id,$order,$state);return;
            }
            $entry=&$state['recipients'][$chat];
            $entry['status']='sending';$entry['attempts']++;$entry['updated_at']=time();unset($entry['error'],$entry['next_at']);
            self::save($order,$state);
            if (!self::schedule($id,time()+self::LOCK_TTL+30,self::WATCHDOG,false)) {
                $entry['status']='failed';$entry['error']='queue_unavailable';self::save($order,$state);
                self::continue_queue($id,$order,$state);return;
            }
            $attempting=true;$settings['chat_id']=$chat;
            $result=JRTG_Api::send_message($message,$settings);
            if (($result['status']??'')==='sent'&&is_int($result['message_id']??null)&&$result['message_id']>0) {
                $entry['status']='sent';$entry['message_id']=$result['message_id'];unset($entry['error']);
            } elseif (($result['status']??'')==='retry'&&$entry['attempts']<self::MAX_ATTEMPTS) {
                $entry['status']='retry';$entry['error']=self::safe_error($result['error']??'invalid_response');
                $entry['next_at']=time()+max(1,min(3600,(int)($result['retry_after']??60)));
            } else {
                $entry['status']=in_array($result['status']??'',['failed','retry'],true)?'failed':'unknown';
                $entry['error']=($result['status']??'')==='retry'?'retry_limit':self::safe_error($result['error']??'invalid_response');
            }
            $entry['updated_at']=time();unset($entry);self::save($order,$state);
            self::continue_queue($id,$order,$state);
        } catch (Throwable $ignored) {
            if ($order instanceof WC_Order&&$state) {
                if ($chat!==null) {
                    $state['recipients'][$chat]['status']=$attempting?'unknown':'failed';
                    $state['recipients'][$chat]['error']=$attempting?'http_unknown':'queue_unavailable';
                }
                try {self::save($order,$state);self::continue_queue($id,$order,$state);} catch (Throwable $ignoredAgain) {}
            }
        } finally {self::release($id,$owner);}
    }

    /** Requeue only eligible failed recipients; preserve every acknowledged delivery. */
    public static function resend($order_id): bool {
        $id=(int)$order_id;$owner=false;
        try {
            if ($id<=0||!JRTG_Settings::ready()) return false;
            $owner=JR_Lock::acquire(self::lock($id),self::LOCK_TTL);
            if (!$owner) return false;
            $order=wc_get_order($id);
            if (!self::eligible($order)) return false;
            $state=self::state($order);$subs=JRTG_Subscriptions::all();$changed=false;
            if (!$state||empty($state['recipients'])) return false;
            $abandoned=self::abandoned($id,$state);
            foreach ($state['recipients'] as $chat=>&$entry) {
                if (($entry['status']??'')==='sent'||!isset($subs[$chat])||!hash_equals($entry['generation'],$subs[$chat]['generation'])) continue;
                if (in_array($entry['status']??'',['queued','retry'],true)&&!$abandoned) continue;
                if (($entry['status']??'')==='sending'&&(int)$entry['updated_at']>time()-self::LOCK_TTL) continue;
                $entry=['generation'=>$entry['generation'],'status'=>'queued','attempts'=>0,'updated_at'=>time(),'next_at'=>time()];
                $changed=true;
            }
            unset($entry);
            if (!$changed) return false;
            $state['config']=self::stamp(JRTG_Settings::get());self::save($order,$state);
            if (!self::schedule($id,time()+1,self::HOOK,false)) {self::fail_pending($order,$state,'queue_unavailable');return false;}
            return true;
        } catch (Throwable $ignored) {return false;}
        finally {self::release($id,$owner);}
    }

    public static function admin_status(WC_Order $order): void {
        if (!self::eligible($order)||!current_user_can('manage_woocommerce')) return;
        $state=self::state($order);$counts=[];$retry=false;$uncertain=false;
        foreach ($state['recipients']??[] as $entry) {
            $status=$entry['status']??'unknown';$counts[$status]=($counts[$status]??0)+1;
            if (in_array($status,['failed','unknown'],true)||($status==='sending'&&(int)$entry['updated_at']<=time()-self::LOCK_TTL)) $retry=true;
            if (in_array($status,['unknown','sending'],true)) $uncertain=true;
        }
        $retry=$retry||self::abandoned($order->get_id(),$state);
        $labels=['queued'=>'В очереди','retry'=>'Ожидает повтора','sending'=>'Отправляется','sent'=>'Telegram подтвердил отправку','failed'=>'Не отправлено','unknown'=>'Результат неизвестен'];
        echo '<div class="form-field form-field-wide"><p><strong>JOYRENT Telegram:</strong></p>';
        if (!$counts) echo '<p>Не отправлялось.</p>';
        foreach ($counts as $status=>$count) echo '<p>'.esc_html(($labels[$status]??'Неизвестно').': '.$count).'</p>';
        if ($retry&&JRTG_Settings::ready()) {
            $form='jrtg-retry-'.$order->get_id();self::$admin_forms[$order->get_id()]=$uncertain;
            echo '<p>Повтор затронет только получателей без подтверждённой доставки, которые всё ещё подписаны.</p>';
            if ($uncertain) echo '<p><label><input type="checkbox" name="uncertain_confirmed" value="1" form="'.esc_html($form).'" required> Я проверил Telegram; повтор с неизвестным результатом может создать дубль.</label></p>';
            echo '<button type="submit" class="button" form="'.esc_html($form).'">Повторить неполученные уведомления</button>';
        }
        echo '</div>';
    }

    /** Footer forms avoid nesting a form inside WooCommerce's order editor. */
    public static function admin_forms(): void {
        if (!current_user_can('manage_woocommerce')) return;
        foreach (self::$admin_forms as $id=>$uncertain) {
            echo '<form id="jrtg-retry-'.(int)$id.'" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="joyrent_telegram_retry"><input type="hidden" name="order_id" value="'.(int)$id.'">';
            wp_nonce_field('joyrent_telegram_retry_'.$id);echo '</form>';
        }
        self::$admin_forms=[];
    }

    public static function admin_retry(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('Недостаточно прав.','',['response'=>403]);
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') wp_die('Используйте кнопку в карточке брони.','',['response'=>405]);
        $id=absint($_POST['order_id']??0);check_admin_referer('joyrent_telegram_retry_'.$id);
        $order=wc_get_order($id);
        if (!self::eligible($order)) wp_die('Бронь не найдена.');
        foreach (self::state($order)['recipients']??[] as $entry) {
            if (in_array($entry['status']??'',['sending','unknown'],true)&&($_POST['uncertain_confirmed']??'')!=='1') wp_die('Проверьте Telegram: повтор может создать дубль.');
        }
        self::resend($id);wp_safe_redirect($order->get_edit_order_url());exit;
    }

    private static function continue_queue(int $id,WC_Order $order,array $state): void {
        $next=null;
        foreach ($state['recipients'] as $entry) {
            $status=$entry['status']??'';
            $at=in_array($status,['queued','retry'],true)?max(time()+1,(int)($entry['next_at']??0)):($status==='sending'?(int)$entry['updated_at']+self::LOCK_TTL+30:null);
            if ($at!==null) $next=$next===null?$at:min($next,$at);
        }
        if ($next!==null&&!self::schedule($id,$next,self::HOOK,false)) self::fail_pending($order,$state,'queue_unavailable');
    }
    private static function fail_pending(WC_Order $order,array $state,string $error): void {
        foreach ($state['recipients'] as &$entry) {
            if (in_array($entry['status']??'',['queued','retry'],true)) {$entry['status']='failed';$entry['error']=self::safe_error($error);}
            elseif (($entry['status']??'')==='sending'&&(int)($entry['updated_at']??0)<=time()-self::LOCK_TTL) {$entry['status']='unknown';$entry['error']='send_interrupted';}
        }
        unset($entry);self::save($order,$state);
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
        return hash('sha256',(string)($settings['token']??'').'|'.(string)($settings['revision']??'').'|'.(string)($settings['access_revision']??''));
    }
    private static function state(WC_Order $order): array {
        $state=$order->get_meta(self::META);return is_array($state)?$state:[];
    }
    private static function save(WC_Order $order,array $state): void {
        $statuses=array_column($state['recipients']??[],'status');
        $state['status']=in_array('sending',$statuses,true)?'sending':(in_array('queued',$statuses,true)||in_array('retry',$statuses,true)?'queued':(count(array_unique($statuses))===1?($statuses[0]??'failed'):'partial'));
        $state['updated_at']=time();$order->update_meta_data(self::META,$state);$order->save_meta_data();
    }
    private static function schedule(int $id,int $at,string $hook=self::HOOK,bool $unique=true): bool {
        if (function_exists('as_schedule_single_action')) {
            try {
                if (as_schedule_single_action($at,$hook,[$id],self::GROUP,$unique)) return true;
                if ($unique&&function_exists('as_has_scheduled_action')&&as_has_scheduled_action($hook,[$id],self::GROUP)) return true;
            } catch (Throwable $ignored) {}
        }
        try {
            if (wp_next_scheduled($hook,[$id])) return true;
            $scheduled=wp_schedule_single_event($at,$hook,[$id],true);return $scheduled!==false&&!is_wp_error($scheduled);
        } catch (Throwable $ignored) {return false;}
    }
    private static function abandoned(int $id,array $state): bool {
        if (!in_array($state['status']??'',['queued','sending'],true)||(int)($state['updated_at']??0)>time()-self::LOCK_TTL) return false;
        try {
            foreach ([self::HOOK,self::WATCHDOG] as $hook) {
                if (function_exists('as_has_scheduled_action')&&as_has_scheduled_action($hook,[$id],self::GROUP)) return false;
                if (wp_next_scheduled($hook,[$id])) return false;
            }
            return true;
        } catch (Throwable $ignored) {return false;}
    }
    private static function lock(int $id): string {return 'jrtg_broadcast_'.$id;}
    private static function release(int $id,$owner): void {
        if (!$owner) return;
        try {JR_Lock::release(self::lock($id),$owner);} catch (Throwable $ignored) {}
    }
    private static function safe_error($error): string {
        $allowed=['not_configured','invalid_message','http_unknown','invalid_response','invalid_ack','rate_limited','telegram_server_error','bot_unauthorized','chat_forbidden','bad_request','telegram_error','disabled','config_changed','queue_unavailable','send_interrupted','retry_limit','order_unavailable','unsubscribed'];
        return is_string($error)&&in_array($error,$allowed,true)?$error:'invalid_response';
    }
}
