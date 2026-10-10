<?php
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
$checks=0;
function check($value,string $label):void { global $checks; $checks++; if (!$value) { fwrite(STDERR,"FAIL: $label\n"); exit(1); } }
$base=dirname(__DIR__,2).'/wordpress/joyrent-telegram/includes/';
check(is_file($base.'api.php')&&is_file($base.'notifications.php')&&is_file($base.'message.php'),'Telegram notification feature exists');
class WP_Error {}
function is_wp_error($x):bool{return $x instanceof WP_Error;}
$GLOBALS['response']=['response'=>['code'=>200],'body'=>'{"ok":true,"result":{"message_id":12,"chat":{"id":999}}}'];
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
$GLOBALS['orders']=[];$GLOBALS['jobs']=[];$GLOBALS['cron']=[];$GLOBALS['schedule_fail']=false;$GLOBALS['as_fail']=false;$GLOBALS['locked']=false;
class JRTG_Dispatcher {static array $wakes=[];static array $forgot=[];static bool $throw=false;static bool $forget_throw=false;static function wake($id):void {if(self::$throw)throw new RuntimeException('Fixture wake failure');self::$wakes[]=$id;}static function forget($id):void {if(self::$forget_throw)throw new RuntimeException('Fixture cleanup failure');self::$forgot[]=$id;}}
function wc_get_order($id){return $GLOBALS['orders'][$id]??false;}
function as_schedule_single_action($time,$hook,$args,$group,$unique=false){if($GLOBALS['schedule_fail']||$GLOBALS['as_fail'])throw new RuntimeException('Sensitive scheduler details');$GLOBALS['jobs'][]=[$time,$hook,$args,$group,$unique];return count($GLOBALS['jobs']);}
function wp_schedule_single_event($time,$hook,$args,$error=false){if($GLOBALS['schedule_fail'])return new WP_Error();$GLOBALS['cron'][]=[$time,$hook,$args];return true;}
function wp_next_scheduled($hook,$args){$at=false;foreach($GLOBALS['cron'] as $job)if($job[1]===$hook&&$job[2]===$args)$at=$at===false?$job[0]:min($at,$job[0]);return $at;}
function wp_clear_scheduled_hook($hook,$args){$GLOBALS['cron']=array_filter($GLOBALS['cron'],fn($job)=>$job[1]!==$hook||$job[2]!==$args);}
function _get_cron_array(){$cron=[];foreach($GLOBALS['cron'] as $job)$cron[$job[0]][$job[1]][md5(serialize($job[2]))]=['args'=>$job[2]];return $cron;}
function as_get_scheduled_actions($query,$format='OBJECT'){
 $GLOBALS['action_queries'][]=$query;
 $found=[];foreach($GLOBALS['jobs'] as $id=>$job)if($job[1]===$query['hook']&&(!isset($query['args'])||$job[2]===$query['args'])&&$job[3]===$query['group']&&($job[5]??'pending')===$query['status']&&(!isset($query['date'])||$job[0]<=$query['date']))$found[$id+1]=new class($job[0],$job[2]){function __construct(private int $at,private array $args){}function get_schedule(){return $this;}function get_date(){return new DateTimeImmutable('@'.$this->at);}function get_args(){return $this->args;}};
 uasort($found,fn($a,$b)=>$a->get_date()->getTimestamp()<=>$b->get_date()->getTimestamp());return array_slice($found,$query['offset']??0,$query['per_page']??1,true);
}
function as_unschedule_all_actions($hook,$args,$group){foreach($GLOBALS['jobs'] as $id=>$job)if($job[1]===$hook&&$job[2]===$args&&$job[3]===$group&&($job[5]??'pending')==='pending')$GLOBALS['jobs'][$id][5]='canceled';}
function pending_jobs($id,$hook='joyrent_telegram_broadcast'){return array_filter($GLOBALS['jobs'],fn($job)=>$job[1]===$hook&&$job[2]===[$id]&&($job[5]??'pending')==='pending');}

$GLOBALS['has_job']=true;function as_has_scheduled_action($hook,$args,$group){return $GLOBALS['has_job'];}
$GLOBALS['held_locks']=[];
class JR_Lock {static function acquire($x,$ttl=600){if($GLOBALS['locked']||isset($GLOBALS['held_locks'][$x]))return false;$owner='owner-'.$x;$GLOBALS['held_locks'][$x]=$owner;return $owner;}static function release($x,$owner){if(($GLOBALS['held_locks'][$x]??null)===$owner)unset($GLOBALS['held_locks'][$x]);}}
class JR_Games{static function records(){return [['id'=>'fc27','title'=>'EA SPORTS FC 27']];}}
require $base.'api.php';require $base.'message.php';require $base.'notifications.php';
function response($body,$http=200){$GLOBALS['response']=['response'=>['code'=>$http],'body'=>is_string($body)?$body:json_encode($body)];}
function fresh($id){$o=new WC_Order($id);$GLOBALS['orders'][$id]=$o;return $o;}
function state($o){$state=$o->get_meta('_joyrent_telegram_broadcast');return is_array($state)?$state:[];}
function sentresponse(){$GLOBALS['response']=function($url,$args){$p=json_decode($args['body'],true);return ['response'=>['code'=>200],'body'=>json_encode(['ok'=>true,'result'=>['message_id'=>12,'chat'=>['id'=>(int)$p['chat_id']]]])];};}
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
$GLOBALS['response']=function($url,$args){$p=json_decode($args['body'],true);$r=$p['chat_id']==='999'?['ok'=>false,'error_code'=>403]:['ok'=>true,'result'=>['message_id'=>22,'chat'=>['id'=>(int)$p['chat_id']]]];return ['response'=>['code'=>$p['chat_id']==='999'?403:200],'body'=>json_encode($r)];};
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

$o=fresh(500);sentresponse();$wakes=count(JRTG_Dispatcher::$wakes);JRTG_Notifications::queue(500);
check(count(JRTG_Dispatcher::$wakes)===$wakes+1&&min(array_column(pending_jobs(500),0))<=time(),'Durable new booking wakes immediate work with due backup');
check(JRTG_Notifications::next_due(500)<=time(),'Dispatcher discovers fresh due work');
JRTG_Notifications::deliver(500);check(count(JRTG_Dispatcher::$wakes)===$wakes+2,'Ready recipient continuation wakes immediate work');check(count(pending_jobs(500,'joyrent_telegram_broadcast_recover'))===0,'Acknowledgment cancels send watchdog');
JRTG_Notifications::deliver(500);$saves=$o->saves;JRTG_Notifications::deliver(500);
check($o->saves===$saves&&JRTG_Notifications::next_due(500)===null&&!pending_jobs(500)&&!pending_jobs(500,'joyrent_telegram_broadcast_recover'),'Terminal broadcast performs no metadata writes and leaves no jobs');
$o=fresh(501);JRTG_Notifications::queue(501);$s=state($o);foreach($s['recipients'] as &$entry){$entry['status']='retry';$entry['next_at']=time()+600;}unset($entry);$o->update_meta_data('_joyrent_telegram_broadcast',$s);$wakes=count(JRTG_Dispatcher::$wakes);JRTG_Notifications::deliver(501);
check(count(JRTG_Dispatcher::$wakes)===$wakes&&JRTG_Notifications::next_due(501)>=time()+599,'Future rate-limit retry never wakes immediate worker');
$s=state($o);$s['recipients']['999']['status']='sending';$s['recipients']['999']['updated_at']=time();$o->update_meta_data('_joyrent_telegram_broadcast',$s);check(JRTG_Notifications::next_due(501)>=time()+329&&JRTG_Notifications::next_due(501)<=time()+330,'Sending recovery uses bounded lease deadline');
$s['recipients']['999']['status']='failed';$o->update_meta_data('_joyrent_telegram_broadcast',$s);$wakes=count(JRTG_Dispatcher::$wakes);check(JRTG_Notifications::resend(501)&&count(JRTG_Dispatcher::$wakes)===$wakes+1,'Manual resend wakes requeued recipient');
$method=new ReflectionMethod(JRTG_Notifications::class,'schedule');$GLOBALS['as_fail']=true;$GLOBALS['cron'][]=[time()+3600,'joyrent_telegram_broadcast',[502]];$method->invoke(null,502,time(),'joyrent_telegram_broadcast');
check(wp_next_scheduled('joyrent_telegram_broadcast',[502])<=time(),'WP-Cron replaces later event for immediate resend');
$before=count($GLOBALS['cron']);$method->invoke(null,502,time()+600,'joyrent_telegram_broadcast');check(count($GLOBALS['cron'])===$before,'WP-Cron preserves earlier pending event');$GLOBALS['as_fail']=false;
$method->invoke(null,503,time()+600,'joyrent_telegram_broadcast');$method->invoke(null,503,time()+600,'joyrent_telegram_broadcast');check(count(pending_jobs(503))===1,'Continuation deduplicates pending Action Scheduler jobs');
foreach($GLOBALS['jobs'] as &$job)if($job[1]==='joyrent_telegram_broadcast'&&$job[2]===[503]&&($job[5]??'pending')==='pending')$job[5]='in-progress';unset($job);$method->invoke(null,503,time(),'joyrent_telegram_broadcast');check(count(pending_jobs(503))===1,'Running action never suppresses next recipient');
$method->invoke(null,505,time(),'joyrent_telegram_broadcast');JRTG_Notifications::deliver(505);check(!pending_jobs(505),'Deleted booking clears orphaned notification jobs');
JRTG_Dispatcher::$throw=true;$o=fresh(504);JRTG_Notifications::queue(504);check(state($o)['status']==='queued'&&pending_jobs(504),'Dispatcher failure preserves accepted booking and durable backup');JRTG_Dispatcher::$throw=false;

$o=fresh(506);sentresponse();JRTG_Notifications::queue(506);$before=calls();$original=state($o);$GLOBALS['held_locks']['jrtg_notification_send']='other-worker';JRTG_Notifications::deliver(506);
check(calls()===$before&&state($o)===$original&&count(pending_jobs(506,'joyrent_telegram_broadcast_recover'))===1,'Busy global sender makes no HTTP call and retains durable recovery');
unset($GLOBALS['held_locks']['jrtg_notification_send']);$first=JRTG_Notifications::inspect(506);JRTG_Notifications::deliver(506);$second=JRTG_Notifications::inspect(506);JRTG_Notifications::deliver(506);$third=JRTG_Notifications::inspect(506);
check(is_string($first['marker'])&&strlen($first['marker'])===64&&$first['marker']!==$second['marker']&&$second['marker']!==$third['marker']&&$third['due']===null,'Authoritative progress marker changes after each acknowledged recipient');
check(JRTG_Notifications::inspect(506)===$third&&JRTG_Notifications::next_due(506)===$third['due'],'Inspection is stable without progress and shares due calculation');
check(!isset($GLOBALS['held_locks']['jrtg_notification_send'])&&calls()===$before+2,'Global sender lock releases between successful recipient steps');

$a=fresh(507);$b=fresh(508);JRTG_Notifications::queue(507);JRTG_Notifications::queue(508);$before=calls();$untouched=state($b);$nested=false;
$GLOBALS['response']=function($url,$args)use(&$nested){if(!$nested){$nested=true;JRTG_Notifications::deliver(508);}$p=json_decode($args['body'],true);return ['response'=>['code'=>200],'body'=>json_encode(['ok'=>true,'result'=>['message_id'=>52,'chat'=>['id'=>(int)$p['chat_id']]]])];};
JRTG_Notifications::deliver(507);check(calls()===$before+1&&state($b)===$untouched&&pending_jobs(508,'joyrent_telegram_broadcast_recover'),'Nested scheduler on another order cannot overlap the active outbound send');
sentresponse();JRTG_Notifications::deliver(508);check(calls()===$before+2&&!isset($GLOBALS['held_locks']['jrtg_notification_send']),'Other order progresses after outbound sender releases');

$o=fresh(509);sentresponse();JRTG_Notifications::queue(509);JRTG_Notifications::deliver(509);check(!in_array(509,JRTG_Dispatcher::$forgot,true),'Watchdog cleanup keeps active dispatcher recipients');JRTG_Notifications::deliver(509);check(in_array(509,JRTG_Dispatcher::$forgot,true),'Terminal broadcast removes dispatcher registry job');
$o=fresh(510);JRTG_Notifications::queue(510);JRTG_Notifications::deliver(510);JRTG_Dispatcher::$forget_throw=true;JRTG_Notifications::deliver(510);check(state($o)['status']==='sent','Dispatcher registry cleanup failure never changes confirmed delivery');JRTG_Dispatcher::$forget_throw=false;

check(method_exists(JRTG_Notifications::class,'pending_due_ids'),'Durable scheduled backups can discover lost dispatcher producers');
$GLOBALS['jobs']=[];$GLOBALS['cron']=[];check(JRTG_Notifications::pending_due_ids()===[],'Empty durable queues discover no orders');$now=time();
$GLOBALS['jobs']=[[$now-2,'joyrent_telegram_broadcast',[701],'joyrent-telegram',false],[$now-1,'joyrent_telegram_broadcast_recover',[702],'joyrent-telegram',false],[$now-1,'joyrent_telegram_broadcast_recover',[701],'joyrent-telegram',false],[$now+600,'joyrent_telegram_broadcast',[703],'joyrent-telegram',false],[$now-1,'joyrent_telegram_broadcast',[704],'another-plugin',false],[$now-1,'joyrent_telegram_broadcast',[705],'joyrent-telegram',false,'in-progress'],[$now-1,'another_hook',[706],'joyrent-telegram',false],[$now-1,'joyrent_telegram_broadcast',[-5],'joyrent-telegram',false]];
$GLOBALS['cron']=[[$now-1,'joyrent_telegram_broadcast',[707]],[$now-1,'joyrent_telegram_broadcast_recover',[702]],[$now+600,'joyrent_telegram_broadcast',[708]],[$now-1,'another_hook',[709]]];$before=calls();$jobs=$GLOBALS['jobs'];$cron=$GLOBALS['cron'];$ids=JRTG_Notifications::pending_due_ids();sort($ids);
check($ids===[701,702,707],'Discovery includes due own-group backups and WP-Cron while excluding future, unrelated, running and invalid actions');
check(calls()===$before&&$GLOBALS['jobs']===$jobs&&$GLOBALS['cron']===$cron,'Durable backup discovery performs no sends or queue mutations');
$GLOBALS['jobs']=[];$GLOBALS['cron']=[];for($i=0;$i<30;$i++){ $GLOBALS['jobs'][]=[$now-2,'joyrent_telegram_broadcast',[800+$i],'joyrent-telegram',false];$GLOBALS['jobs'][]=[$now-1,'joyrent_telegram_broadcast_recover',[900+$i],'joyrent-telegram',false];}
check(count(JRTG_Notifications::pending_due_ids())===40,'Durable backup discovery remains bounded to forty new IDs');

$GLOBALS['jobs']=[];$GLOBALS['cron']=[];$GLOBALS['action_queries']=[];for($i=0;$i<20;$i++)$GLOBALS['jobs'][]=[$now-2,'joyrent_telegram_broadcast',[1000+$i],'joyrent-telegram',false];
$GLOBALS['jobs'][]=[$now-1,'joyrent_telegram_broadcast',[1020],'joyrent-telegram',false];$GLOBALS['jobs'][]=[$now+600,'joyrent_telegram_broadcast',[1021],'joyrent-telegram',false];$GLOBALS['jobs'][]=[$now-1,'joyrent_telegram_broadcast',[1022],'another-plugin',false];
check(JRTG_Notifications::pending_due_ids(range(1000,1019))===[1020],'Known registry backoff backups do not hide the next missing due order');
$GLOBALS['cron']=[[$now-1,'joyrent_telegram_broadcast',[1000]],[$now-1,'joyrent_telegram_broadcast',[1023]]];check(JRTG_Notifications::pending_due_ids(range(1000,1020))===[1023],'WP-Cron discovery excludes known IDs before applying result cap');
$GLOBALS['jobs']=[];$GLOBALS['cron']=[];$GLOBALS['action_queries']=[];for($i=0;$i<65;$i++){ $GLOBALS['jobs'][]=[$now-2,'joyrent_telegram_broadcast',[1100+$i],'joyrent-telegram',false];$GLOBALS['jobs'][]=[$now-1,'joyrent_telegram_broadcast_recover',[1200+$i],'joyrent-telegram',false];}
check(JRTG_Notifications::pending_due_ids(array_merge(range(1100,1164),range(1200,1264)))===[]&&count($GLOBALS['action_queries'])===6,'Excluded backup discovery stops after three pages per hook');
echo "PASS: $checks Telegram fan-out/API checks\n";
