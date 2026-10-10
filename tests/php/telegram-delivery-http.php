<?php
require '/var/www/html/wp-load.php';
if(!get_option('joyrent_qa_bot_fixture_enabled',false))throw new RuntimeException('Disposable QA required.');
$mode=$argv[1]??'';
if($mode==='init'){
 delete_option('joyrent_telegram_dispatch');
 global$wpdb;$keys=$wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_jr_rate_%' OR option_name LIKE '_transient_timeout_jr_rate_%'");foreach($keys as$key)delete_option($key);
 $token='123456789:QAonlyTokenForTesting_NotReal987654321';
 update_option(JRTG_Settings::OPTION,['enabled'=>true,'token'=>$token,'password_hash'=>wp_hash_password('fixture-pass'),'webhook_secret'=>bin2hex(random_bytes(32)),'webhook_connected'=>true,'webhook_bot'=>hash('sha256',$token),'access_revision'=>wp_generate_uuid4(),'enabled_since'=>time()-60,'revision'=>wp_generate_uuid4()],false);
 update_option(JRTG_Subscriptions::OPTION,['bot'=>JRTG_Subscriptions::stamp(),'subscribers'=>['987654321'=>['generation'=>wp_generate_uuid4(),'label'=>'Fixture one','since'=>time()],'987654322'=>['generation'=>wp_generate_uuid4(),'label'=>'Fixture two','since'=>time()]],'pending'=>[],'seen'=>[]],false);
 update_option('joyrent_qa_bot_sends',0,false);update_option('joyrent_qa_dispatch_requests',0,false);
 echo json_encode(['cronDisabled'=>defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON,'startDate'=>wp_date('Y-m-d',time()+864000)]);exit;
}
if($mode==='disable'){
 $settings=JRTG_Settings::get();$settings['enabled']=false;
 update_option(JRTG_Settings::OPTION,$settings,false);
 if(JRTG_Settings::get()['enabled'])throw new RuntimeException('Baseline could not disable Telegram.');
 echo json_encode(['telegramEnabled'=>false]);exit;
}

/** Resolve even an accepted HTTP response that never reached the test client. */
function jrtg_http_fixture_ids(array $requests):array {
 global $wpdb;
 $hpos=\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
 $table=$hpos?$wpdb->prefix.'wc_orders_meta':$wpdb->postmeta;
 $column=$hpos?'order_id':'post_id';$ids=[];
 foreach($requests as$request){
  if(!is_string($request)||!preg_match('/^[a-f0-9-]{32,40}$/i',$request))throw new RuntimeException('Invalid fixture request identity.');
  $key=hash_hmac('sha256',$request,wp_salt('nonce'));
  foreach($wpdb->get_col($wpdb->prepare("SELECT DISTINCT {$column} FROM {$table} WHERE meta_key=%s AND meta_value=%s",'_joyrent_request_key',$key)) as$id)$ids[]=(int)$id;
 }
 return array_values(array_unique($ids));
}
$requests=$mode==='cleanup-requests'?array_slice($argv,2):[];
$ids=$mode==='cleanup-requests'?jrtg_http_fixture_ids($requests):array_map('intval',array_slice($argv,2));
if($mode==='poll-many'){
 $orders=[];
 foreach($ids as$id){$order=wc_get_order($id);$state=$order?$order->get_meta('_joyrent_telegram_broadcast'):[];
 $orders[$id]=['status'=>$state['status']??null,'recipients'=>array_count_values(array_column($state['recipients']??[],'status')),'store'=>$order?$order->get_data_store()->get_current_class_name():null];}
 echo json_encode(['orders'=>$orders,'sends'=>(int)get_option('joyrent_qa_bot_sends',0),'dispatchRequests'=>(int)get_option('joyrent_qa_dispatch_requests',0),'pendingJobs'=>count(JRTG_Dispatcher::status()['jobs'])]);exit;
}
$id=$ids[0]??0;$order=wc_get_order($id);
if($mode==='poll'){
 $state=$order?$order->get_meta('_joyrent_telegram_broadcast'):[];
 echo json_encode(['status'=>$state['status']??null,'recipients'=>array_count_values(array_column($state['recipients']??[],'status')),'sends'=>(int)get_option('joyrent_qa_bot_sends',0),'store'=>$order?$order->get_data_store()->get_current_class_name():null]);exit;
}
if($mode==='cleanup'||$mode==='cleanup-requests'){
 $cleaned=0;
 foreach($ids as$id){
  JRTG_Dispatcher::forget($id);
  foreach(['joyrent_telegram_broadcast','joyrent_telegram_broadcast_recover']as$hook){as_unschedule_all_actions($hook,[$id],'joyrent-telegram');wp_clear_scheduled_hook($hook,[$id]);}
  $order=wc_get_order($id);if(!$order)continue;
  if($order->get_created_via()!=='joyrent')throw new RuntimeException('Cleanup identity did not belong to a JOYRENT fixture.');
  $order->delete(true);$cleaned++;
 }
 foreach($requests as$request)delete_option('jr_result_'.hash_hmac('sha256',$request,wp_salt('nonce')));
 echo json_encode(['cleaned'=>$cleaned,'remainingFixtures'=>$requests?count(jrtg_http_fixture_ids($requests)):0,'pendingFixtureJobs'=>count(array_intersect($ids,array_keys(JRTG_Dispatcher::status()['jobs'])))]);exit;
}
throw new RuntimeException('Unknown fixture operation.');
