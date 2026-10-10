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
function wp_remote_post($url,$args){$GLOBALS['http_calls'][]=[$url,$args];if($GLOBALS['response'] instanceof Throwable)throw $GLOBALS['response'];if(is_callable($GLOBALS['response']))return ($GLOBALS['response'])($url,$args);return $GLOBALS['response'];}
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
 static $data=['enabled'=>true,'token'=>'123456:'.'ABCDEFGHIJKLMNOPQRSTUVWX','chat_id'=>'999','enabled_since'=>1,'revision'=>'config-a','access_revision'=>'members-a','webhook_secret'=>'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'];
 static function get():array{return self::$data;}
 static function ready():bool{return self::$data['enabled']&&self::$data['token']!==''&&self::$data['chat_id']!=='';}
 static function error_label($x):string{return $x;}
}
class JRTG_Subscriptions {static $members=['999'=>['generation'=>'a'],'111'=>['generation'=>'b']];static function all():array{return self::$members;}}
function wp_parse_url($x){return parse_url($x);}
function home_url($x){return 'https://example.invalid/';}
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
function state($o){$state=$o->get_meta('_joyrent_telegram_broadcast');return is_array($state)?$state:[];}
function sentresponse(){response(['ok'=>true,'result'=>['message_id'=>12]]);}
function calls(){return count($GLOBALS['http_calls']);}
JRTG_Settings::$data['enabled']=false;check(JRTG_Api::send_message('admin test')['status']==='sent','Admin can test before enabling');JRTG_Settings::$data['enabled']=true;
foreach([['ok'=>true,'result'=>[]],['ok'=>true,'result'=>['message_id'=>'12']],['ok'=>true,'result'=>['message_id'=>0]]]as$bad){response($bad);check(JRTG_Api::send_message('x')['status']==='unknown','Malformed acknowledgment is unknown');}
response('<html>gateway</html>',502);check(JRTG_Api::send_message('x')['status']==='unknown','Uncertain gateway error is not retried');
$GLOBALS['response']=new WP_Error();check(JRTG_Api::send_message('x')['status']==='unknown','Timeout remains uncertain');
response(['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>99999]],429);check(JRTG_Api::send_message('x')['retry_after']===3600,'Rate limit bounded');
response(['ok'=>false,'error_code'=>403,'description'=>'sensitive'],403);check(JRTG_Api::send_message('x')===['status'=>'failed','error'=>'chat_forbidden'],'Remote details never escape');
$before=calls();check(JRTG_Api::connect_webhook(JRTG_Settings::$data,'http://example.invalid/receiver')['status']==='failed'&&calls()===$before,'Webhook requires HTTPS');
check(JRTG_Api::connect_webhook(JRTG_Settings::$data,'https://attacker.invalid/receiver')['status']==='failed','Webhook cannot target unrelated site');
$GLOBALS['response']=function($url,$args){$p=json_decode($args['body'],true);$result=str_ends_with($url,'/setWebhook')?true:['url'=>'https://example.invalid/receiver'];return ['response'=>['code'=>200],'body'=>json_encode(['ok'=>true,'result'=>$result])];};
check(JRTG_Api::connect_webhook(JRTG_Settings::$data,'https://example.invalid/receiver')['status']==='ok','Webhook registration verified');
$payload=json_decode($GLOBALS['http_calls'][count($GLOBALS['http_calls'])-2][1]['body'],true);
check($payload['secret_token']===str_repeat('a',64)&&$payload['allowed_updates']===['message'],'Webhook uses secret and minimal updates');

sentresponse();$old=fresh(10);$old->date=0;JRTG_Notifications::queue(10);check(state($old)===[],'No old order backfill');
$draft=fresh(11);$draft->meta['_joyrent_completed']='';JRTG_Notifications::queue(11);check(state($draft)===[],'No incomplete booking');
$o=fresh(20);$before=calls();$jobCount=count($GLOBALS['jobs']);JRTG_Notifications::queue(20);JRTG_Notifications::queue(20);
check(count(state($o)['recipients'])===2&&count($GLOBALS['jobs'])===$jobCount+1,'Status hooks create one two-recipient snapshot');
check(calls()===$before&&!str_contains(json_encode(state($o)),JRTG_Settings::$data['token']),'Booking hook has no HTTP or token in outbox');
JRTG_Subscriptions::$members['222']=['generation'=>'c'];
JRTG_Notifications::deliver(20);JRTG_Notifications::deliver(20);
check(array_column(state($o)['recipients'],'status')===['sent','sent'],'Every snapshot subscriber receives acknowledgment');
check(calls()===$before+2&&!isset(state($o)['recipients']['222']),'Later subscriber does not receive previous booking');
$before=calls();JRTG_Notifications::deliver(20);check(!JRTG_Notifications::resend(20)&&calls()===$before,'Acknowledged sends never repeat');
unset(JRTG_Subscriptions::$members['222']);

$o=fresh(21);JRTG_Notifications::queue(21);
$GLOBALS['response']=function($url,$args){$p=json_decode($args['body'],true);$r=$p['chat_id']==='999'?['ok'=>false,'error_code'=>403]:['ok'=>true,'result'=>['message_id'=>22]];return ['response'=>['code'=>$p['chat_id']==='999'?403:200],'body'=>json_encode($r)];};
JRTG_Notifications::deliver(21);JRTG_Notifications::deliver(21);
check(state($o)['recipients']['999']['status']==='failed'&&state($o)['recipients']['111']['status']==='sent','One failed recipient does not block another');
sentresponse();$before=calls();check(JRTG_Notifications::resend(21),'Failed recipient requeued');JRTG_Notifications::deliver(21);
check(state($o)['status']==='sent'&&calls()===$before+1,'Manual retry preserves other acknowledged recipient');

$o=fresh(22);JRTG_Notifications::queue(22);$GLOBALS['response']=new WP_Error();JRTG_Notifications::deliver(22);sentresponse();JRTG_Notifications::deliver(22);
$before=calls();JRTG_Notifications::deliver(22);
check(state($o)['recipients']['999']['status']==='unknown'&&state($o)['recipients']['111']['status']==='sent'&&calls()===$before,'Unknown result does not resend or block others');

$o=fresh(23);JRTG_Notifications::queue(23);unset(JRTG_Subscriptions::$members['999']);JRTG_Notifications::deliver(23);
check(state($o)['recipients']['999']['error']==='unsubscribed'&&state($o)['recipients']['111']['status']==='sent','Stop cancels pending membership');
JRTG_Subscriptions::$members['999']=['generation'=>'new-a'];$before=calls();
check(!JRTG_Notifications::resend(23)&&calls()===$before,'Rejoined user cannot receive old-generation booking');

JRTG_Subscriptions::$members=['999'=>['generation'=>'new-a'],'111'=>['generation'=>'b']];
$o=fresh(24);JRTG_Notifications::queue(24);JRTG_Settings::$data['revision']='config-b';$before=calls();JRTG_Notifications::deliver(24);
check(state($o)['status']==='failed'&&calls()===$before,'Configuration rotation cancels remaining queue');
$o=fresh(25);JRTG_Notifications::queue(25);$s=state($o);$s['recipients']['999']['status']='sending';$s['recipients']['999']['updated_at']=time()-400;$o->update_meta_data('_joyrent_telegram_broadcast',$s);$before=calls();JRTG_Notifications::deliver(25);
check(state($o)['recipients']['999']['status']==='unknown'&&state($o)['recipients']['111']['status']==='sent'&&calls()===$before+1,'Crashed worker recovered without resending');

$o=fresh(26);$GLOBALS['schedule_fail']=true;JRTG_Notifications::queue(26);check(state($o)['status']==='failed','Unavailable queue cannot reject booking');$GLOBALS['schedule_fail']=false;
$o=fresh(27);JRTG_Notifications::queue(27);$GLOBALS['locked']=true;$before=calls();$jobCount=count($GLOBALS['jobs']);JRTG_Notifications::deliver(27);
check(calls()===$before&&count($GLOBALS['jobs'])>$jobCount,'Contended worker schedules recovery');$GLOBALS['locked']=false;

$o=fresh(28);JRTG_Notifications::queue(28);response(['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>2]],429);JRTG_Notifications::deliver(28);sentresponse();JRTG_Notifications::deliver(28);
check(state($o)['recipients']['999']['status']==='retry'&&state($o)['recipients']['111']['status']==='sent','Rate limit on one recipient does not stall ready recipients');
response(['ok'=>false,'error_code'=>429],429);
for($i=0;$i<5;$i++){$s=state($o);$s['recipients']['999']['next_at']=0;$o->update_meta_data('_joyrent_telegram_broadcast',$s);JRTG_Notifications::deliver(28);}
check(state($o)['recipients']['999']['status']==='failed'&&state($o)['recipients']['999']['attempts']===4,'Retry cap is per recipient');

$o=fresh(30);$o->meta['_joyrent_telegram']='@private_user';$o->meta['_joyrent_requested_game']=str_repeat('😀<b>З</b>',1500);
$m=JRTG_Message::for_order($o);check(str_contains($m,'EA SPORTS FC 27')&&str_contains($m,'@private_user')&&!str_contains($m,'<b>')&&!str_contains($m,'Оплачено'),'Plain booking message contains actual fields without false payment claim');
$units=0;foreach(preg_split('//u',$m,-1,PREG_SPLIT_NO_EMPTY) as $char)$units+=strlen($char)===4?2:1;check($units<=3900,'Message fits Unicode budget');
echo "PASS: $checks Telegram fan-out/API checks\n";
