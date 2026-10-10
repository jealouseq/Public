<?php
declare(strict_types=1);
require getenv('JOYRENT_WP_LOAD')?:'/var/www/html/wp-load.php';
if (!get_option('joyrent_qa_bot_fixture_enabled',false)) throw new RuntimeException('Disposable QA required.');
$mode=$argv[1]??'';
if($mode==='fixture'){
    update_option('jrtg_qa_original_settings',get_option('joyrent_telegram_settings',null),false);
    update_option('jrtg_qa_original_subscribers',get_option(JRTG_Subscriptions::OPTION,null),false);
    $token='123456789:QAonlyTokenForTesting_NotReal987654321';
    update_option(JRTG_Settings::OPTION,['enabled'=>true,'token'=>$token,'password_hash'=>wp_hash_password('fixture-pass'),'webhook_secret'=>bin2hex(random_bytes(32)),'webhook_connected'=>true,'webhook_bot'=>hash('sha256',$token),'access_revision'=>wp_generate_uuid4(),'enabled_since'=>time()-10,'revision'=>wp_generate_uuid4()],false);
    update_option(JRTG_Subscriptions::OPTION,['bot'=>JRTG_Subscriptions::stamp(),'subscribers'=>['987654321'=>['generation'=>'fixture-one','label'=>'One'],'987654322'=>['generation'=>'fixture-two','label'=>'Two']],'pending'=>[],'seen'=>[]],false);
    update_option('jrtg_qa_http_count',0,false);
    $order=new WC_Order();$order->set_status('checkout-draft');$order->set_created_via('joyrent');$order->set_billing_first_name('QA concurrency');$order->set_billing_phone('+380990000000');
    foreach(['request_key'=>'concurrency-'.wp_generate_uuid4(),'completed'=>'yes','console'=>'ps5','days'=>3,'controllers'=>2,'start_date'=>'2026-10-20','return_date'=>'2026-10-23','rental_amount'=>1400,'method'=>'pickup','security_mode'=>'deposit','deposit'=>25000] as $key=>$value)$order->update_meta_data('_joyrent_'.$key,$value);
    $order->save();$order->set_status('jr-request');$order->save();update_option('jrtg_qa_order_id',$order->get_id(),false);echo "Fixture ready\n";exit;
}
if($mode==='worker'){
    add_filter('pre_http_request',function($pre,$args,$url){
        if(!str_starts_with($url,'https://api.telegram.org/'))return $pre;
        global $wpdb;$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=CAST(option_value AS UNSIGNED)+1 WHERE option_name=%s",'jrtg_qa_http_count'));wp_cache_delete('jrtg_qa_http_count','options');
        usleep(500000);
        return ['response'=>['code'=>200,'message'=>'Fixture'],'headers'=>[],'body'=>'{"ok":true,"result":{"message_id":991}}','cookies'=>[]];
    },50,3);
    JRTG_Notifications::deliver((int)get_option('jrtg_qa_order_id'));echo "Worker completed\n";exit;
}
if($mode==='assert'){
    $id=(int)get_option('jrtg_qa_order_id');$order=wc_get_order($id);$state=$order->get_meta('_joyrent_telegram_broadcast');$count=(int)get_option('jrtg_qa_http_count');
    $ok=$count===2&&$state['status']==='sent'&&count($state['recipients'])===2&&array_column($state['recipients'],'message_id')===[991,991];
    foreach(['joyrent_telegram_broadcast','joyrent_telegram_broadcast_recover'] as $hook){as_unschedule_all_actions($hook,[$id],'joyrent-telegram');wp_clear_scheduled_hook($hook,[$id]);}
    $order->delete(true);
    $old=get_option('jrtg_qa_original_settings',null);if($old===null)delete_option('joyrent_telegram_settings');else update_option('joyrent_telegram_settings',$old,false);
    $oldSubs=get_option('jrtg_qa_original_subscribers',null);if($oldSubs===null)delete_option(JRTG_Subscriptions::OPTION);else update_option(JRTG_Subscriptions::OPTION,$oldSubs,false);
    foreach(['jrtg_qa_original_subscribers','jrtg_qa_original_settings','jrtg_qa_http_count','jrtg_qa_order_id'] as $name)delete_option($name);
    if(!$ok){fwrite(STDERR,"FAIL: Concurrent durable worker count $count\n");exit(1);}
    echo "PASS: Concurrent PHP workers made one acknowledged HTTP call per recipient\n";exit;
}
fwrite(STDERR,"Specify fixture, worker or assert\n");exit(1);
