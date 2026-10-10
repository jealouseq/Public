<?php
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
$checks=0;
function check($value,string $label):void { global $checks; $checks++; if (!$value) { fwrite(STDERR,"FAIL: $label\n"); exit(1); } }
$base=dirname(__DIR__,2).'/wordpress/joyrent-telegram/includes/';
check(is_file($base.'api.php')&&is_file($base.'notifications.php')&&is_file($base.'message.php'),'Telegram notification feature exists');
class WP_Error {}
function is_wp_error($x):bool{return $x instanceof WP_Error;}
$GLOBALS['response']=['response'=>['code'=>200],'body'=>'{"ok":true,"result":{"message_id":12}}'];
$GLOBALS['http_calls']=[];
function wp_remote_post($url,$args){$GLOBALS['http_calls'][]=[$url,$args];if($GLOBALS['response'] instanceof Throwable)throw $GLOBALS['response'];return $GLOBALS['response'];}
function wp_remote_retrieve_response_code($r){return $r['response']['code']??0;}
function wp_remote_retrieve_body($r){return $r['body']??'';}
function wp_json_encode($x){return json_encode($x);}
function wp_strip_all_tags($x){return strip_tags($x);}
function sanitize_text_field($x){return trim(strip_tags($x));}
function add_action(...$x){}
function current_user_can($x){return true;}
function esc_html($x){return htmlspecialchars((string)$x,ENT_QUOTES);}
function esc_url($x){return htmlspecialchars((string)$x,ENT_QUOTES);}
function admin_url($x){return 'https://example.invalid/wp-admin/'.$x;}
function wp_nonce_field(...$x){}
function wp_nonce_url($x,...$args){return $x;}
function wp_date($x,$stamp){return date($x,$stamp);}
class JRTG_Settings{
 static $data=['enabled'=>true,'token'=>'123456:'.'ABCDEFGHIJKLMNOPQRSTUVWX','chat_id'=>'999','enabled_since'=>1];
 static function get():array{return self::$data;}
 static function ready():bool{return self::$data['enabled']&&self::$data['token']!==''&&self::$data['chat_id']!=='';}
 static function error_label($x):string{return $x;}
}
class WC_Order {
 public array $meta=[]; public $id; public $status='jr-request'; public $created='joyrent'; public $date; public $saves=0;
 function __construct($id){$this->id=$id;$this->date=time();$this->meta=['_joyrent_request_key'=>'safe-request-key','_joyrent_completed'=>'yes','_joyrent_console'=>'ps5','_joyrent_days'=>3,'_joyrent_controllers'=>2,'_joyrent_start_date'=>'2026-10-20','_joyrent_return_date'=>'2026-10-23','_joyrent_rental_amount'=>1400,'_joyrent_delivery'=>'pending','_joyrent_deposit'=>25000,'_joyrent_security_mode'=>'deposit','_joyrent_game_ids'=>['fc27'],'_joyrent_method'=>'delivery'];}
 function get_meta($k){return $this->meta[$k]??'';} function update_meta_data($k,$v){$this->meta[$k]=$v;}
 function get_id(){return $this->id;} function get_order_number(){return $this->id;} function get_status(){return $this->status;}
 function get_created_via(){return $this->created;} function get_date_created(){return new DateTimeImmutable('@'.$this->date);}
 function get_billing_first_name(){return 'Private <b>Имя</b>'; } function get_billing_phone(){return '+380990000000';}
 function get_billing_address_1(){return 'Private address';} function get_edit_order_url(){return 'https://example.invalid/wp-admin/post.php?post='.$this->id.'&action=edit';}
 function save(){ $this->saves++; return $this->id; } function save_meta_data(){ $this->saves++; }
}
$GLOBALS['orders']=[];$GLOBALS['jobs']=[];$GLOBALS['cron']=[];$GLOBALS['schedule_fail']=false;$GLOBALS['locked']=false;
function wc_get_order($id){return $GLOBALS['orders'][$id]??false;}
function as_schedule_single_action($time,$hook,$args,$group,$unique=false){if($GLOBALS['schedule_fail'])throw new RuntimeException('Sensitive scheduler details');$GLOBALS['jobs'][]=[$time,$hook,$args,$group,$unique];return count($GLOBALS['jobs']);}
function wp_schedule_single_event($time,$hook,$args,$error=false){if($GLOBALS['schedule_fail'])return new WP_Error();$GLOBALS['cron'][]=[$time,$hook,$args];return true;}
function wp_next_scheduled($hook,$args){return false;}
$GLOBALS['has_job']=true;function as_has_scheduled_action($hook,$args,$group){return $GLOBALS['has_job'];}
class JR_Lock {static function acquire($x,$ttl=600){if($GLOBALS['locked'])return false;$GLOBALS['locked']=true;return 'owner';}static function release($x,$owner){$GLOBALS['locked']=false;}}
class JR_Games{static function records(){return [['id'=>'fc27','title'=>'EA SPORTS FC 27']];}}
require $base.'api.php';require $base.'message.php';require $base.'notifications.php';
function response($body,$http=200){$GLOBALS['response']=['response'=>['code'=>$http],'body'=>is_string($body)?$body:json_encode($body)];}
function fresh($id){$o=new WC_Order($id);$GLOBALS['orders'][$id]=$o;return $o;}
function state($o){$state=$o->get_meta('_joyrent_telegram_notification');return is_array($state)?$state:[];}
function sentresponse(){response(['ok'=>true,'result'=>['message_id'=>12]]);}
function calls(){return count($GLOBALS['http_calls']);}
JRTG_Settings::$data['enabled']=false;check(JRTG_Api::send_message('admin test')['status']==='sent','Authenticated setup can test valid credentials before enabling automatic delivery');JRTG_Settings::$data['enabled']=true;
$r=JRTG_Api::send_message('hello');
check($r['status']==='sent'&&$r['message_id']===12,'API requires acknowledged valid message id');
foreach([['ok'=>true,'result'=>[]],['ok'=>true,'result'=>['message_id'=>'12']],['ok'=>true,'result'=>['message_id'=>0]],['ok'=>true,'result'=>['message_id'=>-1]]] as $bad){response($bad);check(JRTG_Api::send_message('x')['status']==='unknown','Invalid acknowledgment is unknown');}
response('<html>gateway error</html>',502);check(JRTG_Api::send_message('x')['status']==='unknown','HTML gateway response does not blindly retry');
$GLOBALS['response']=new WP_Error();check(JRTG_Api::send_message('x')['status']==='unknown','Transport timeout never auto resends');
response(['ok'=>false,'error_code'=>429,'description'=>'token and PII must not leak','parameters'=>['retry_after'=>999999]],429);
$r=JRTG_Api::send_message('x');check($r['status']==='retry'&&$r['retry_after']<=3600,'429 retry delay is bounded');
response(['ok'=>false,'error_code'=>500],500);check(JRTG_Api::send_message('x')['status']==='retry','Explicit Telegram 500 retries');
response(['ok'=>false,'error_code'=>403,'description'=>'sensitive'],403);check(JRTG_Api::send_message('x')['status']==='failed','403 fails permanently');
check(!str_contains(json_encode(JRTG_Api::send_message('x')),'sensitive'),'Remote error details never reach state');
response(['ok'=>true,'result'=>['message_id'=>12]],500);check(JRTG_Api::send_message('x')['status']==='unknown','Contradictory HTTP status is unknown');
response(['ok'=>true,'result'=>[['update_id'=>1,'message'=>['chat'=>['id'=>999,'type'=>'private','first_name'=>'Owner']]],['update_id'=>2,'message'=>['chat'=>['id'=>999,'type'=>'private','first_name'=>'Owner']]],['update_id'=>3,'channel_post'=>['chat'=>['id'=>-100123,'type'=>'channel','title'=>'Group']]]]]);
$r=JRTG_Api::get_chats();check($r['status']==='ok'&&count($r['chats'])===2,'Chat discovery finds private and channel chats once');
check(!str_contains(json_encode($r),JRTG_Settings::$data['token']),'Discovery never returns token');
sentresponse();$start=calls();$old=fresh(10);$old->date=0;JRTG_Notifications::queue(10);check(state($old)===[],'No historical orders on activation');
$draft=fresh(11);$draft->meta['_joyrent_completed']='';JRTG_Notifications::queue(11);check(state($draft)===[],'No incomplete order notifications');
$ordinary=fresh(12);$ordinary->created='checkout';JRTG_Notifications::queue(12);check(state($ordinary)===[],'Ordinary WooCommerce orders ignored');
$o=fresh(20);$before=count($GLOBALS['jobs']);JRTG_Notifications::queue(20);JRTG_Notifications::queue(20);
check(state($o)['status']==='queued'&&count($GLOBALS['jobs'])===$before+1,'Repeated status hooks queue once durably');
check(calls()===$start,'Booking hook never makes Telegram HTTP request');
$job=end($GLOBALS['jobs']);check($job[2]===[20]&&$job[4]===true,'Job holds only order id and is unique');
check(!str_contains(json_encode(state($o)),JRTG_Settings::$data['token'])&&!str_contains(json_encode(state($o)),'Private'),'Queue state stores neither token nor customer data');
JRTG_Notifications::deliver(20);check(state($o)['status']==='sent'&&state($o)['message_id']===12,'Successful worker marks acknowledgment');
$before=calls();JRTG_Notifications::deliver(20);JRTG_Notifications::queue(20);check(JRTG_Notifications::resend(20)===false&&calls()===$before,'Sent notification cannot be resent');
$o=fresh(21);JRTG_Notifications::queue(21);$GLOBALS['response']=new WP_Error();JRTG_Notifications::deliver(21);
check(state($o)['status']==='unknown','Timeout durable state is unknown');
$before=calls();JRTG_Notifications::deliver(21);JRTG_Notifications::queue(21);check(calls()===$before,'Unknown does not automatically repeat');
sentresponse();check(JRTG_Notifications::resend(21)===true,'Explicit reviewed retry may requeue unknown');JRTG_Notifications::deliver(21);check(state($o)['status']==='sent','Reviewed retry completes');
$o=fresh(22);JRTG_Notifications::queue(22);response(['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>2]],429);
JRTG_Notifications::deliver(22);check(state($o)['status']==='retry','Rate limited notification waits for retry');
for($i=0;$i<5;$i++){ $retry=state($o);$retry['next_at']=0;$o->update_meta_data('_joyrent_telegram_notification',$retry);JRTG_Notifications::deliver(22); }
check(state($o)['status']==='failed'&&state($o)['attempts']<=4,'Retries stop after bounded attempts');
$o=fresh(23);JRTG_Notifications::queue(23);$s=state($o);$s['status']='sending';$s['updated_at']=time()-400;$o->update_meta_data('_joyrent_telegram_notification',$s);
$before=calls();JRTG_Notifications::deliver(23);check(state($o)['status']==='unknown'&&calls()===$before,'Crashed sending recovers to unknown without duplicate');
$o=fresh(24);JRTG_Notifications::queue(24);JRTG_Settings::$data['enabled']=false;$before=calls();JRTG_Notifications::deliver(24);
check(state($o)['status']==='failed'&&calls()===$before,'Disabled connector does not send queued jobs');JRTG_Settings::$data['enabled']=true;
$o=fresh(25);JRTG_Notifications::queue(25);JRTG_Settings::$data['chat_id']='111';$before=calls();JRTG_Notifications::deliver(25);
check(state($o)['status']==='failed'&&calls()===$before,'Changed destination blocks stale queued jobs');JRTG_Settings::$data['chat_id']='999';
$o=fresh(26);$GLOBALS['schedule_fail']=true;JRTG_Notifications::queue(26);check(state($o)['status']==='failed','Scheduler failure safely persists without throwing');$GLOBALS['schedule_fail']=false;
$o=fresh(27);JRTG_Notifications::queue(27);$GLOBALS['locked']=true;$before=calls();$jobsBefore=count($GLOBALS['jobs']);JRTG_Notifications::deliver(27);check(calls()===$before,'Concurrent worker cannot bypass mutex');check(count($GLOBALS['jobs'])>$jobsBefore,'A contended worker preserves a future recovery instead of consuming the last job');$GLOBALS['locked']=false;
$o=fresh(28);JRTG_Notifications::queue(28);$orphan=state($o);$orphan['updated_at']=time()-400;$o->update_meta_data('_joyrent_telegram_notification',$orphan);check(JRTG_Notifications::resend(28)===false,'Scheduled older jobs cannot be mistaken for abandoned');$GLOBALS['has_job']=false;check(JRTG_Notifications::resend(28)===true,'An old orphan queued order can be explicitly recovered after deactivation without backfill');$GLOBALS['has_job']=true;
$o=fresh(30);$o->meta['_joyrent_telegram']='@private_user';$o->meta['_joyrent_requested_game']=str_repeat('😀<b>З</b>',1500);
$m=JRTG_Message::for_order($o);check(str_contains($m,'EA SPORTS FC 27')&&str_contains($m,'@private_user'),'Manager sees real game title and optional customer Telegram');
check(!str_contains($m,'<b>')&&!str_contains($m,'Оплачено'),'Plain notification strips markup and does not claim payment');
$units=0;foreach(preg_split('//u',$m,-1,PREG_SPLIT_NO_EMPTY) as $char)$units+=strlen($char)===4?2:1;
check($units<=3900,'Long Unicode message fits Telegram UTF16 budget');
$o->meta['_joyrent_security_mode']='contract';$m=JRTG_Message::for_order($o);check(str_contains($m,'проверки документов')&&!str_contains($m,'25 000'),'Contract request does not promise no deposit or fixed deposit');
echo "PASS: $checks Telegram isolated behavioral checks\n";
