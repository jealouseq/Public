<?php
declare(strict_types=1);
require '/var/www/html/wp-load.php';
if (!get_option('joyrent_qa_bot_fixture_enabled',false)) throw new RuntimeException('Disposable QA environment required.');
$checks=0;$orderId=null;$resultKey=null;$calls=[];$settingsOld=get_option(JRTG_Settings::OPTION,null);$subsOld=get_option(JRTG_Subscriptions::OPTION,null);$mailBefore=(int)get_option('joyrent_qa_intercepted_mail',0);
$ipOld=$_SERVER['REMOTE_ADDR']??null;$_SERVER['REMOTE_ADDR']='127.0.0.77';
function ec($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException('FAIL: '.$label);}
function eqstate($id){$x=wc_get_order($id)->get_meta('_joyrent_telegram_broadcast');return is_array($x)?$x:[];}
$token='123456789:QAonlyTokenForTesting_NotReal987654321';
$settings=['enabled'=>true,'token'=>$token,'password_hash'=>wp_hash_password('fixture-pass'),'webhook_secret'=>bin2hex(random_bytes(32)),'webhook_connected'=>true,'webhook_bot'=>hash('sha256',$token),'access_revision'=>wp_generate_uuid4(),'enabled_since'=>time()-10,'revision'=>wp_generate_uuid4()];
update_option(JRTG_Settings::OPTION,$settings,false);delete_option(JRTG_Subscriptions::OPTION);
function botupdate($id,$chat,$text,$secret=true){
 global $settings;
 $r=new WP_REST_Request('POST','/joyrent-telegram/v1/update');$r->set_header('Content-Type','application/json');
 if($secret)$r->set_header('X-Telegram-Bot-Api-Secret-Token',$settings['webhook_secret']);
 $r->set_body(wp_json_encode(['update_id'=>$id,'message'=>['from'=>['id'=>$chat,'is_bot'=>false],'chat'=>['id'=>$chat,'type'=>'private','first_name'=>'Fixture '.$chat],'text'=>$text]]));
 return rest_do_request($r);
}
$mock=function($pre,$args,$url)use(&$calls){
 if(wp_parse_url($url,PHP_URL_HOST)!=='api.telegram.org')return $pre;
 $body=json_decode($args['body']??'',true);if(!str_contains((string)($body['text']??''),'REST Queue Fixture'))return $pre;$chat=(string)($body['chat_id']??'');$calls[]=$chat;
 ec(in_array($chat,['987654321','987654322'],true),'Worker uses authenticated subscriber');
 ec(str_contains((string)($body['text']??''),'REST Queue Fixture'),'Worker contains actual REST booking');
 return ['response'=>['code'=>200,'message'=>'OK'],'headers'=>[],'body'=>wp_json_encode(['ok'=>true,'result'=>['message_id'=>314159+count($calls)]]),'cookies'=>[]];
};
add_filter('pre_http_request',$mock,50,3);
try{
 ec(botupdate(1,987654321,'/start',false)->get_status()===403,'Forged webhook cannot subscribe');
 ec(str_contains(botupdate(2,987654321,'/start')->get_data()['text'],'пароль'),'Real REST start asks password');
 botupdate(3,987654321,'wrong');ec(count(JRTG_Subscriptions::all())===0,'Wrong password no subscription');
 botupdate(4,987654321,'fixture-pass');botupdate(5,987654322,'/start');botupdate(6,987654322,'fixture-pass');
 ec(count(JRTG_Subscriptions::all())===2,'Real webhook subscribes two recipients');
 botupdate(6,987654322,'fixture-pass');ec(count(JRTG_Subscriptions::all())===2,'Repeated update id is idempotent');
 $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->modify('+10 days')->format('Y-m-d');
 $payload=['language'=>'ru','console'=>'ps5','days'=>3,'startDate'=>$today,'controllers'=>2,'gameIds'=>[],'name'=>'REST Queue Fixture','phone'=>'+380500000077','telegram'=>'@rest_fixture','method'=>'delivery','address'=>'Фонтанская дорога, 10','securityMode'=>'deposit','consent'=>true,'website'=>'','requestId'=>wp_generate_uuid4()];
 $key=hash_hmac('sha256',$payload['requestId'],wp_salt('nonce'));$resultKey='jr_result_'.$key;
 $request=new WP_REST_Request('POST','/joyrent/v1/requests');$request->set_header('Content-Type','application/json');$request->set_body(wp_json_encode($payload));
 $response=rest_do_request($request);ec($response->get_status()===201,'Public booking accepted');
 $receipt=$response->get_data();ec(isset($receipt['reference'])&&!str_contains(wp_json_encode($receipt),'rest_fixture'),'Public receipt excludes private contact');
 $order=wc_get_order((int)substr($receipt['reference'],3));ec($order instanceof WC_Order,'Booking is durable');$orderId=$order->get_id();
 ec($order->get_meta('_joyrent_request_key')===$key&&$order->get_meta('_joyrent_completed')==='yes','Core completes booking');
 ec(count($calls)===0,'Booking acceptance makes zero Telegram HTTP requests');
 ec((int)get_option('joyrent_qa_intercepted_mail',0)===$mailBefore+1,'Normal email remains once');
 ec(count(eqstate($orderId)['recipients'])===2,'Two-member outbox snapshot');
 $repeat=rest_do_request($request);ec($repeat->get_status()===200&&$repeat->get_data()['reference']===$receipt['reference'],'Repeated browser request returns same booking');
 ec(count($calls)===0&&(int)get_option('joyrent_qa_intercepted_mail',0)===$mailBefore+1,'Repeat makes no email or Telegram duplicate');
 for($i=0;$i<3;$i++){sleep(2);do_action('action_scheduler_run_queue');}
 ec(eqstate($orderId)['status']==='sent'&&count($calls)===2,'Real Action Scheduler acknowledges both subscribers');
 ec(count(array_unique($calls))===2,'Every subscriber receives exactly once');
 JRTG_Notifications::deliver($orderId);ec(count($calls)===2,'Repeated worker cannot duplicate');
 botupdate(7,987654321,'/stop');ec(count(JRTG_Subscriptions::all())===1,'Stop affects only sender');
 ec(wc_get_order($orderId)->get_status()==='jr-request','Broadcast does not change booking status');
 echo wp_json_encode(['checks'=>$checks,'pass'=>true,'store'=>wc_get_order($orderId)->get_data_store()->get_current_class_name(),'telegramMockCalls'=>count($calls),'mailAttempts'=>(int)get_option('joyrent_qa_intercepted_mail',0)-$mailBefore],JSON_PRETTY_PRINT)."\n";
}finally{
 remove_filter('pre_http_request',$mock,50);
 if($orderId){foreach(['joyrent_telegram_broadcast','joyrent_telegram_broadcast_recover']as$hook){as_unschedule_all_actions($hook,[$orderId],'joyrent-telegram');wp_clear_scheduled_hook($hook,[$orderId]);}$order=wc_get_order($orderId);if($order)$order->delete(true);}
 if($resultKey)delete_option($resultKey);delete_transient('jr_rate_'.hash_hmac('sha256','127.0.0.77',wp_salt('auth')));
 if($settingsOld===null)delete_option(JRTG_Settings::OPTION);else update_option(JRTG_Settings::OPTION,$settingsOld,false);
 if($subsOld===null)delete_option(JRTG_Subscriptions::OPTION);else update_option(JRTG_Subscriptions::OPTION,$subsOld,false);
 if($ipOld===null)unset($_SERVER['REMOTE_ADDR']);else $_SERVER['REMOTE_ADDR']=$ipOld;
}
