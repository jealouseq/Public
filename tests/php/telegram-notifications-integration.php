<?php
declare(strict_types=1);
require getenv('JOYRENT_WP_LOAD')?:'/var/www/html/wp-load.php';
$checks=0;$created=[];$calls=0;$response=['ok'=>true,'result'=>['message_id'=>77]];$http=200;
function tcheck($ok,string $label):void {global $checks;$checks++;if(!$ok)throw new RuntimeException("FAIL: $label");}
function tstate($id):array {$s=wc_get_order($id)->get_meta('_joyrent_telegram_notification');return is_array($s)?$s:[];}
function torder($via='joyrent',$completed='yes',$created=null):WC_Order {
    global $created_ids;
    $order=new WC_Order();$order->set_created_via($via);$order->set_status('checkout-draft');
    if($created!==null)$order->set_date_created($created);
    $order->set_billing_first_name('Тест <b>Клиент</b>');$order->set_billing_phone('+380990000000');$order->set_billing_address_1('Тестовый адрес');
    foreach(['request_key'=>'fixture-'.wp_generate_uuid4(),'completed'=>$completed,'console'=>'ps5','days'=>3,'controllers'=>2,'start_date'=>'2026-10-20','return_date'=>'2026-10-23','rental_amount'=>1400,'delivery'=>'pending','deposit'=>'pending','security_mode'=>'contract','method'=>'delivery','language'=>'ru','telegram'=>'@fixture_user','game_ids'=>['ea-sports-fc-27']] as $key=>$value)$order->update_meta_data('_joyrent_'.$key,$value);
    $order->save();$created_ids[]=$order->get_id();
    $order->set_status('jr-request');$order->save();return $order;
}
function tdue($id):void{$order=wc_get_order($id);$s=tstate($id);$s['next_at']=time()-1;$order->update_meta_data('_joyrent_telegram_notification',$s);$order->save();}
tcheck(class_exists('JRTG_Notifications'),'Connector active');
$old=get_option('joyrent_telegram_settings',null);$created_ids=[];
$settings=['enabled'=>true,'token'=>'123456:ABCDEFGHIJKLMNOPQRSTUVWX','chat_id'=>'987654321','enabled_since'=>time()-10,'revision'=>wp_generate_uuid4()];
update_option('joyrent_telegram_settings',$settings,false);
$callback=function($pre,$args,$url)use(&$calls,&$response,&$http){
    if(!str_starts_with($url,'https://api.telegram.org/'))return $pre;
    $calls++;
    if($response instanceof WP_Error)return $response;
    return ['response'=>['code'=>$http,'message'=>'Fixture'],'headers'=>[],'body'=>wp_json_encode($response),'cookies'=>[]];
};
add_filter('pre_http_request',$callback,50,3);
try {
    $order=torder();$id=$order->get_id();
    tcheck($calls===0,'Order status hook does not perform HTTP');
    tcheck(tstate($id)['status']==='queued','Complete order is durably queued');
    $actions=as_get_scheduled_actions(['hook'=>'joyrent_telegram_deliver','args'=>[$id],'group'=>'joyrent-telegram','status'=>ActionScheduler_Store::STATUS_PENDING],'ids');
    tcheck(count($actions)===1,'One unique Action Scheduler job contains order id');
    JRTG_Notifications::queue($id);tcheck(count(as_get_scheduled_actions(['hook'=>'joyrent_telegram_deliver','args'=>[$id],'group'=>'joyrent-telegram','status'=>ActionScheduler_Store::STATUS_PENDING],'ids'))===1,'Repeated status queues one job');
    JRTG_Notifications::deliver($id);tcheck(tstate($id)['status']==='sent'&&tstate($id)['message_id']===77,'Real Woo CRUD stores sent acknowledgment');
    $before=$calls;JRTG_Notifications::deliver($id);tcheck(!JRTG_Notifications::resend($id)&&$calls===$before,'Duplicate worker and manual action cannot resend acknowledged message');
    tcheck(!str_contains(wp_json_encode(tstate($id)),$settings['token']),'Token absent from order state');
    $order=torder('checkout');tcheck(tstate($order->get_id())===[],'Ordinary Woo order ignored');
    $order=torder('joyrent','');tcheck(tstate($order->get_id())===[],'Incomplete booking ignored');
    $order=torder('joyrent','yes',time()-86400);tcheck(tstate($order->get_id())===[],'Old booking does not backfill');
    tcheck(JRTG_Notifications::resend($order->get_id()),'Manager explicitly may enqueue old completed booking');
    $order=torder();$id=$order->get_id();$response=new WP_Error('timeout','Sensitive exception token detail');
    JRTG_Notifications::deliver($id);tcheck(tstate($id)['status']==='unknown','Transport ambiguity persists unknown');
    $before=$calls;JRTG_Notifications::deliver($id);tcheck($calls===$before,'Unknown result not retried automatically');
    tcheck(!str_contains(wp_json_encode(tstate($id)),'Sensitive'),'Raw error details are not persisted');
    $response=['ok'=>true,'result'=>['message_id'=>78]];tcheck(JRTG_Notifications::resend($id),'Reviewed unknown retry enqueues');JRTG_Notifications::deliver($id);
    tcheck(tstate($id)['status']==='sent'&&tstate($id)['message_id']===78,'Reviewed retry acknowledges new result');
    $order=torder();$id=$order->get_id();$response=['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>1]];$http=429;
    JRTG_Notifications::deliver($id);tcheck(tstate($id)['status']==='retry','429 has durable retry');
    $before=$calls;JRTG_Notifications::deliver($id);tcheck($calls===$before,'Retry does not send before due time');
    for($i=0;$i<4;$i++){tdue($id);JRTG_Notifications::deliver($id);}
    tcheck(tstate($id)['status']==='failed'&&tstate($id)['attempts']===4,'Retries bounded at four sends');
    $response=['ok'=>true,'result'=>['message_id'=>79]];$http=200;
    $order=torder();$id=$order->get_id();$s=tstate($id);$s['status']='sending';$s['updated_at']=time()-400;$order=wc_get_order($id);$order->update_meta_data('_joyrent_telegram_notification',$s);$order->save();
    $before=$calls;JRTG_Notifications::deliver($id);tcheck(tstate($id)['status']==='unknown'&&$calls===$before,'Sending crash watchdog cannot duplicate');
    $order=torder();$id=$order->get_id();$settings['chat_id']='111222333';$settings['revision']=wp_generate_uuid4();update_option('joyrent_telegram_settings',$settings,false);
    $before=$calls;JRTG_Notifications::deliver($id);tcheck(tstate($id)['status']==='failed'&&tstate($id)['error']==='config_changed'&&$calls===$before,'Recipient change blocks old queue');
    $order=torder();$id=$order->get_id();$settings['enabled']=false;update_option('joyrent_telegram_settings',$settings,false);
    $before=$calls;JRTG_Notifications::deliver($id);tcheck(tstate($id)['status']==='failed'&&$calls===$before,'Disabled settings do not send');
    $settings['enabled']=true;$settings['enabled_since']=time()-10;update_option('joyrent_telegram_settings',$settings,false);
    $order=torder();$id=$order->get_id();$owner=JR_Lock::acquire('jrtg_notification_'.$id,300);$before=$calls;
    JRTG_Notifications::deliver($id);tcheck($calls===$before,'Existing cross-process mutex blocks worker');JR_Lock::release('jrtg_notification_'.$id,$owner);
    $orphan=torder();$oid=$orphan->get_id();$state=tstate($oid);$state['updated_at']=time()-400;$orphan=wc_get_order($oid);$orphan->update_meta_data('_joyrent_telegram_notification',$state);$orphan->save_meta_data();
    tcheck(!JRTG_Notifications::resend($oid),'Pending scheduled action prevents manual orphan replay');
    wp_schedule_single_event(time()+600,'joyrent_telegram_deliver',[$oid]);wp_schedule_single_event(time()+620,'joyrent_telegram_recover',[$oid]);
    do_action('deactivate_'.plugin_basename(JRTG_PLUGIN_FILE),false);
    tcheck(wp_next_scheduled('joyrent_telegram_deliver',[$oid])===false&&wp_next_scheduled('joyrent_telegram_recover',[$oid])===false,'Deactivation removes cron jobs with order-id arguments');
    tcheck(JRTG_Notifications::resend($oid),'Old orphan queue is manually recoverable after deactivation');
    $message=JRTG_Message::for_order(wc_get_order($id));
    tcheck(str_contains($message,'@fixture_user')&&str_contains($message,'проверки документов')&&!str_contains($message,'<b>'),'Message contains optional contact and cautious plain-text contract wording');
    wp_set_current_user(0);ob_start();JRTG_Notifications::admin_status(wc_get_order($id));$markup=ob_get_clean();tcheck($markup==='','Order tools invisible without capability');
    $public=JR_Settings::public();tcheck(!str_contains(wp_json_encode($public),$settings['token'])&&!str_contains(wp_json_encode($public),'987654321'),'Core public settings have no bot configuration');
    echo 'PASS: '.$checks.' real WordPress Telegram checks; store '.wc_get_order($id)->get_data_store()->get_current_class_name()."\n";
} finally {
    remove_filter('pre_http_request',$callback,50);
    foreach($created_ids as $id){foreach(['joyrent_telegram_deliver','joyrent_telegram_recover'] as $hook){as_unschedule_all_actions($hook,[$id],'joyrent-telegram');wp_clear_scheduled_hook($hook,[$id]);}$order=wc_get_order($id);if($order)$order->delete(true);}
    if($old===null)delete_option('joyrent_telegram_settings');else update_option('joyrent_telegram_settings',$old,false);
}
