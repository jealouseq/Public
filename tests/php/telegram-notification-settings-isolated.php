<?php
define('ABSPATH','/isolated/');define('MINUTE_IN_SECONDS',60);
$options=[];$checks=0;$cache=[];$fail_write=false;$write_attempts=0;$finish_code='';
function get_option($key,$default=false){
 if(isset($GLOBALS['cache']['notoptions'][$key]))return $default;
 if(array_key_exists($key,$GLOBALS['cache']))return $GLOBALS['cache'][$key];
 if(!array_key_exists($key,$GLOBALS['options'])){$GLOBALS['cache']['notoptions'][$key]=true;return $default;}
 return $GLOBALS['cache'][$key]=$GLOBALS['options'][$key];
}
function wp_cache_delete($key,$group=''){unset($GLOBALS['cache'][$key]);return true;}
function update_option($key,$value,$autoload=null){
 $GLOBALS['write_attempts']++;
 if($GLOBALS['fail_write'])return false;
 if(($GLOBALS['options'][$key]??null)===$value)return false;
 $GLOBALS['options'][$key]=$value;unset($GLOBALS['cache'][$key],$GLOBALS['cache']['notoptions'][$key]);return true;
}
function wp_generate_uuid4(){static $i=0;return 'revision-'.++$i;}
function wp_hash_password($p){return password_hash($p,PASSWORD_BCRYPT);}
function current_user_can($c){return true;}function get_current_user_id(){return 1;}
function get_transient($k){return false;}function delete_transient($k){}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return esc_html($v);}function esc_url($v){return esc_html($v);}
function admin_url($p=''){return 'https://example.test/wp-admin/'.$p;}
function wp_nonce_field($action){echo '<input name="_wpnonce" value="fixture">';}
function sc($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException('FAIL: '.$label);}
require dirname(__DIR__,2).'/wordpress/joyrent-telegram/includes/settings.php';
$token='123456789:'.str_repeat('a',35);
$first=JRTG_Settings::candidate([],['enabled'=>false,'token'=>$token,'password'=>'fixture-pass','clear_token'=>false],100);
sc(isset($first['password_hash'])&&password_verify('fixture-pass',$first['password_hash']),'Access password is hashed');
sc(!str_contains(json_encode($first),'fixture-pass'),'Plain password absent from saved settings');
sc(!$first['enabled']&&!$first['webhook_connected'],'Initial config waits for authenticated webhook setup');
$old=$first;$old['webhook_connected']=true;$old['webhook_bot']=hash('sha256',$token);$old['enabled']=true;
$old['enabled_since']=100;$old['revision']='existing';
$next=JRTG_Settings::candidate($old,['enabled'=>true,'token'=>'','password'=>'','clear_token'=>false],200);
sc($next['token']===$token&&$next['password_hash']===$old['password_hash']&&$next['revision']==='existing','Blank fields preserve secrets and active queue');
$changed=JRTG_Settings::candidate($old,['enabled'=>true,'token'=>'','password'=>'new-fixture-pass','clear_token'=>false],200);
sc($changed['access_revision']!==$old['access_revision']&&!$changed['enabled'],'Password rotation invalidates subscriptions and waits for enable');
$changed=JRTG_Settings::candidate($old,['enabled'=>true,'token'=>'987654321:'.str_repeat('b',35),'password'=>'','clear_token'=>false],200);
sc(!$changed['webhook_connected']&&!$changed['enabled']&&$changed['webhook_secret']!==$old['webhook_secret'],'Bot change disconnects and rotates webhook secret');
foreach([['token'=>['bad']],['password'=>'ab'],['token'=>'https://example.test'],['password'=>['bad']]]as$patch){
 try{JRTG_Settings::candidate($old,array_merge(['enabled'=>true,'token'=>'','password'=>'','clear_token'=>false],$patch),200);sc(false,'Invalid input rejected');}
 catch(InvalidArgumentException $e){sc(true,'Invalid input rejected');}
}
$options['joyrent_telegram_settings']=$old;
sc(JRTG_Settings::ready(),'Enabled connected bot ready');
ob_start();JRTG_Settings::page();$html=ob_get_clean();
sc(!str_contains($html,$token)&&!str_contains($html,$old['password_hash'])&&!str_contains($html,$old['webhook_secret']),'Admin HTML hides every secret');
sc(!str_contains($html,'ID чата')&&!str_contains($html,'name="chat_id"'),'No numeric destination UI');
sc(str_contains($html,'Пароль доступа')&&str_contains($html,'Подключить бота')&&str_contains($html,'/start'),'Password subscriber flow in admin');
sc(str_contains($html,'_wpnonce')&&str_contains($html,'jrtg_connect')&&str_contains($html,'jrtg_test'),'Authenticated connection actions in admin');


class JRTG_Test_Finish extends RuntimeException {}
class JR_Lock {
 static $held=false;static $busy=false;static $acquired=0;
 static function acquire($key,$ttl=600){if(self::$busy||self::$held)return false;self::$held=true;self::$acquired++;return 'owner';}
 static function release($key,$owner){self::$held=false;}
}
class JRTG_Api {
 static $during_connect=null;static $send_result=['status'=>'sent','message_id'=>1];static $calls=[];
 static function connect_webhook($settings,$url){if(self::$during_connect)(self::$during_connect)();return ['status'=>'ok'];}
 static function send_message($message,$settings){self::$calls[]=$settings['chat_id'];if(self::$send_result instanceof Throwable)throw self::$send_result;return self::$send_result;}
}
class JRTG_Subscriptions {static $members=[];static function all(){return self::$members;}}
class JRTG_Dispatcher {static $data=[];static function status(){return self::$data;}}
class WP_Error {}
function is_wp_error($value){return $value instanceof WP_Error;}
function wp_date($format,$timestamp){return date($format,$timestamp);}
function as_enqueue_async_action($hook,$args,$group,$unique=false){
 if(!empty($GLOBALS['async_failure']))return 0;
 $GLOBALS['test_jobs'][]=$args;if(!empty($GLOBALS['fast_test_worker']))JRTG_Settings::send_test(...$args);return count($GLOBALS['test_jobs']);
}
function wp_schedule_single_event($at,$hook,$args,$error=false){
 if(!empty($GLOBALS['cron_failure'])){if(!empty($GLOBALS['fail_health_after_schedule']))$GLOBALS['fail_write']=true;if(!empty($GLOBALS['busy_health_after_schedule']))JR_Lock::$busy=true;return new WP_Error();}
 $GLOBALS['cron_jobs'][]=$args;return true;
}
function add_action(...$args){$GLOBALS['recorded_actions'][]=$args;}
function wp_unslash($s){return $s;}
function check_admin_referer($action){}
function set_transient($key,$value,$ttl){$GLOBALS['finish_code']=$value;return true;}
function wp_safe_redirect($url){throw new JRTG_Test_Finish();}
function rest_url($route){return 'https://example.test/wp-json/'.$route;}
function wp_die($message,...$args){throw new RuntimeException($message);}
function settings_action($method){
 try{JRTG_Settings::$method();}catch(JRTG_Test_Finish $e){return $GLOBALS['finish_code'];}
 throw new RuntimeException('Missing settings redirect');
}
function settings_fixture($value){
 $GLOBALS['options']=[JRTG_Settings::OPTION=>$value];$GLOBALS['cache']=[];$GLOBALS['fail_write']=false;
 $GLOBALS['finish_code']='';$GLOBALS['write_attempts']=0;JR_Lock::$held=false;JR_Lock::$busy=false;JR_Lock::$acquired=0;
 JRTG_Api::$during_connect=null;JRTG_Api::$send_result=['status'=>'sent','message_id'=>1];JRTG_Api::$calls=[];
 JRTG_Subscriptions::$members=[];JRTG_Dispatcher::$data=[];
 $GLOBALS['test_jobs']=[];$GLOBALS['cron_jobs']=[];$GLOBALS['async_failure']=false;$GLOBALS['cron_failure']=false;$GLOBALS['fail_health_after_schedule']=false;$GLOBALS['busy_health_after_schedule']=false;$GLOBALS['fast_test_worker']=false;
 $_SERVER['REQUEST_METHOD']='POST';$_POST=['token'=>'','password'=>'','enabled'=>'1'];
}
$cases=[];
$cases['fresh positive option cache']=function()use($old){
 settings_fixture($old);JRTG_Settings::get();$GLOBALS['options'][JRTG_Settings::OPTION]['enabled']=false;
 sc(!JRTG_Settings::ready(),'Concurrent disable is visible on configuration reread');
};
$cases['fresh negative option cache']=function()use($old){
 settings_fixture([]);unset($GLOBALS['options'][JRTG_Settings::OPTION]);JRTG_Settings::get();
 $GLOBALS['options'][JRTG_Settings::OPTION]=$old;
 sc(JRTG_Settings::ready(),'Configuration created by another worker is visible after cached absence');
};
$cases['failed settings save']=function()use($old){
 settings_fixture($old);unset($_POST['enabled']);$GLOBALS['fail_write']=true;
 sc(settings_action('save')==='settings_unavailable','Failed DB write never announces settings saved');
 sc($GLOBALS['options'][JRTG_Settings::OPTION]===$old&&!JR_Lock::$held,'Failed save preserves existing config and releases its lock');
};
$cases['unchanged settings save']=function()use($old){
 settings_fixture($old);
 sc(settings_action('save')==='saved','Identical configuration remains a successful no-op save');
 sc($GLOBALS['options'][JRTG_Settings::OPTION]===$old,'Plain save preserves access generation and queue revision');
 sc(JR_Lock::$acquired===1&&!JR_Lock::$held,'Settings save acquires and releases the shared write lock');
};
$cases['busy settings save']=function()use($old){
 settings_fixture($old);JR_Lock::$busy=true;unset($_POST['enabled']);
 sc(settings_action('save')==='settings_busy'&&$GLOBALS['write_attempts']===0,'Competing writer never overwrites settings while lock is busy');
};
$cases['connect configuration race']=function()use($old){
 settings_fixture($old);$changed=$old;$changed['enabled']=false;$changed['revision']='concurrent-config';
 $changed['access_revision']='concurrent-access';$changed['password_hash']=password_hash('rotated-fixture',PASSWORD_BCRYPT);
 JRTG_Api::$during_connect=function()use($changed){$GLOBALS['options'][JRTG_Settings::OPTION]=$changed;};
 sc(settings_action('connect')==='config_changed','Connect detects password and disable changes during Telegram request');
 sc($GLOBALS['options'][JRTG_Settings::OPTION]===$changed,'Connect does not roll back another administrator configuration');
 sc(!JR_Lock::$held,'Connect race releases its write lock');
};
$cases['failed connect persistence']=function()use($old){
 $disconnected=$old;$disconnected['webhook_connected']=false;$disconnected['webhook_bot']='';$disconnected['enabled']=false;
 settings_fixture($disconnected);$GLOBALS['fail_write']=true;
 sc(settings_action('connect')==='settings_unavailable','Failed DB write never announces bot connected');
 sc($GLOBALS['options'][JRTG_Settings::OPTION]===$disconnected,'Failed connection commit leaves local bot disconnected');
};
$cases['connect successful commit']=function()use($old){
 $disconnected=$old;$disconnected['webhook_connected']=false;$disconnected['webhook_bot']='';$disconnected['enabled']=false;
 settings_fixture($disconnected);
 JRTG_Api::$during_connect=function(){sc(!JR_Lock::$held,'Slow Telegram request runs outside settings lock');};
 sc(settings_action('connect')==='connected','Verified webhook connection can commit');
 sc(!empty($GLOBALS['options'][JRTG_Settings::OPTION]['webhook_connected'])&&JR_Lock::$acquired===1&&!JR_Lock::$held,'Connection commit holds and releases the shared write lock');
};
foreach([
 'token'=>'987654321:'.str_repeat('b',35),
 'webhook_secret'=>str_repeat('d',64),
 'revision'=>'other-queue-generation',
 'access_revision'=>'other-membership-generation',
 'password_hash'=>password_hash('another-fixture-pass',PASSWORD_BCRYPT),
 'enabled'=>false,
 'enabled_since'=>200,
 'webhook_connected'=>false,
 'webhook_bot'=>hash('sha256','another-fixture-token'),
] as $field=>$value){
 $cases['connect independent '.$field.' race']=function()use($old,$field,$value){
  settings_fixture($old);$changed=$old;$changed[$field]=$value;
  JRTG_Api::$during_connect=function()use($changed){$GLOBALS['options'][JRTG_Settings::OPTION]=$changed;};
  sc(settings_action('connect')==='config_changed','Connect rejects independent '.$field.' changes during Telegram request');
  sc($GLOBALS['options'][JRTG_Settings::OPTION]===$changed&&!JR_Lock::$held,'Concurrent '.$field.' value survives connect without a retained lock');
 };
}
$cases['busy connect commit']=function()use($old){
 settings_fixture($old);JR_Lock::$busy=true;
 sc(settings_action('connect')==='settings_busy'&&$GLOBALS['write_attempts']===0,'Busy connection commit leaves current configuration unchanged');
};
$cases['unchanged false return']=function()use($old){
 settings_fixture($old);$GLOBALS['fail_write']=true;
 sc(settings_action('save')==='saved'&&$GLOBALS['options'][JRTG_Settings::OPTION]===$old,'A false update return remains successful after an identical fresh roundtrip');
};
function test_fixture($settings){
 settings_fixture($settings);
 JRTG_Subscriptions::$members=['987654321'=>['generation'=>'member-one','label'=>'Private Member One'],'987654322'=>['generation'=>'member-two','label'=>'Private Member Two']];
}
function test_health(){return $GLOBALS['options']['joyrent_telegram_test_status']??[];}
function test_count($status){return count(array_filter(test_health()['results']??[],fn($result)=>($result['status']??'')===$status));}
function settings_html(){ob_start();JRTG_Settings::page();return ob_get_clean();}
$cases['dispatcher admin diagnostics']=function()use($old){
 settings_fixture($old);$now=time();
 JRTG_Dispatcher::$data=['jobs'=>[123=>$now-120,234=>$now+60],'last_start'=>$now-20,'last_finish'=>$now-10,'error'=>'loopback_failed'];
 $html=settings_html();
 sc(str_contains($html,'В очереди броней: 2')&&str_contains($html,'Готовы к отправке: 1'),'Admin reports queued and due dispatcher jobs');
 sc(str_contains($html,wp_date('d.m.Y H:i:s',$now-20))&&str_contains($html,wp_date('d.m.Y H:i:s',$now-10)),'Admin reports last worker start and completion');
 sc(str_contains($html,'запрос к собственному сайту'),'Admin explains dispatcher loopback failure');
 sc(!str_contains($html,'Бот подключён')&&str_contains($html,'Webhook зарегистрирован'),'Saved registration does not claim current webhook health');
 JRTG_Dispatcher::$data['error']='worker_failed';$html=settings_html();
 sc(str_contains($html,'Фоновая отправка завершилась с ошибкой'),'Admin explains dispatcher worker failure');
 JRTG_Dispatcher::$data['error']='PRIVATE_UNKNOWN_ERROR '.$old['token'];$html=settings_html();
 sc(!str_contains($html,'PRIVATE_UNKNOWN_ERROR')&&!str_contains($html,$old['token']),'Unknown worker errors never expose raw details');
};
$cases['test queue records safe pending status']=function()use($old){
 test_fixture($old);
 sc(settings_action('test')==='test_queued'&&test_count('queued')===2,'Queued test status is persisted for every scheduled recipient');
 $health=json_encode(test_health());
 sc(!str_contains($health,'987654321')&&!str_contains($health,'Private Member')&&!str_contains($health,$old['token'])&&!str_contains($health,$old['password_hash'])&&!str_contains($health,$old['webhook_secret']),'Test health stores no chat IDs, names, credentials or message text');
};
foreach([
 'sent'=>['status'=>'sent','message_id'=>1],
 'failed'=>['status'=>'failed','error'=>'chat_forbidden'],
 'retry'=>['status'=>'retry','error'=>'rate_limited','retry_after'=>20],
 'unknown'=>['status'=>'unknown','error'=>'http_unknown'],
] as $name=>$response){
 $cases['test worker records '.$name]=function()use($old,$name,$response){
  test_fixture($old);settings_action('test');JRTG_Api::$send_result=$response;
  foreach($GLOBALS['test_jobs'] as $args)JRTG_Settings::send_test(...$args);
  $expected=$name==='retry'?'failed':$name;
  sc(test_count($expected)===2&&test_count('queued')===0,'Worker persists '.$name.' outcome for the test batch');
  $html=settings_html();sc(str_contains($html,'Последний тест'),'Persistent test result is visible in admin');
  if($name==='failed')sc(str_contains($html,'Получатель заблокировал бота'),'Rejected test explains the safe recipient failure');
 };
}
$cases['test worker deduplication']=function()use($old){
 test_fixture($old);settings_action('test');$args=$GLOBALS['test_jobs'][0];
 JRTG_Settings::send_test(...$args);JRTG_Settings::send_test(...$args);
 sc(count(JRTG_Api::$calls)===1&&test_count('sent')===1,'Repeated worker does not resend an acknowledged test');
};
$cases['legacy test worker captures failure']=function()use($old){
 test_fixture($old);JRTG_Api::$send_result=['status'=>'failed','error'=>'chat_forbidden'];
 JRTG_Settings::send_test('987654321');
 sc(test_count('failed')===1,'Legacy one-argument test job persists its failure');
};
$cases['test worker sanitizes exceptions']=function()use($old){
 test_fixture($old);settings_action('test');JRTG_Api::$send_result=new RuntimeException('PRIVATE_TEST_FAILURE '.$old['token']);
 JRTG_Settings::send_test(...$GLOBALS['test_jobs'][0]);
 sc(test_count('unknown')===1&&!str_contains(json_encode(test_health()),'PRIVATE_TEST_FAILURE'),'Thrown transport errors become safe unknown test outcomes');
};
$cases['test queue scheduler fallback']=function()use($old){
 test_fixture($old);$GLOBALS['async_failure']=true;
 sc(settings_action('test')==='test_queued'&&count($GLOBALS['cron_jobs'])===2,'Test queue uses WP-Cron after Action Scheduler refuses the job');
};
$cases['test queue rejects unavailable scheduling']=function()use($old){
 test_fixture($old);$GLOBALS['async_failure']=true;$GLOBALS['cron_failure']=true;
 sc(settings_action('test')==='queue_unavailable'&&test_count('failed')===2,'Unscheduled tests persist failed status rather than remaining queued');
};
$cases['test status DB failure']=function()use($old){
 test_fixture($old);$GLOBALS['fail_write']=true;
 sc(settings_action('test')==='test_status_unavailable'&&$GLOBALS['test_jobs']===[],'Test is not queued when durable health initialization fails');
};
$cases['old test batch cannot overwrite new batch']=function()use($old){
 test_fixture($old);settings_action('test');$oldArgs=$GLOBALS['test_jobs'][0];
 settings_action('test');JRTG_Settings::send_test(...$oldArgs);
 sc(JRTG_Api::$calls===[]&&test_count('queued')===2,'Old queued batch cannot send or alter the current test result');
};
$cases['test detects changed access generation']=function()use($old){
 test_fixture($old);settings_action('test');$GLOBALS['options'][JRTG_Settings::OPTION]['access_revision']='rotated-access';
 JRTG_Settings::send_test(...$GLOBALS['test_jobs'][0]);
 sc(JRTG_Api::$calls===[]&&test_count('failed')===1,'Test worker stops delivery after access generation changes');
};
$cases['test detects subscriber generation change']=function()use($old){
 test_fixture($old);settings_action('test');JRTG_Subscriptions::$members['987654321']['generation']='new-membership';
 JRTG_Settings::send_test(...$GLOBALS['test_jobs'][0]);
 sc(JRTG_Api::$calls===[]&&test_count('failed')===1,'Test scheduled before unsubscribe and resubscribe is not delivered');
};
$cases['legacy job cannot cancel modern test batch']=function()use($old){
 test_fixture($old);settings_action('test');$modern=test_health();$jobs=$GLOBALS['test_jobs'];
 JRTG_Settings::send_test('987654321');
 sc(test_health()===$modern&&JRTG_Api::$calls===[],'Superseded legacy job preserves the latest modern batch');
 foreach($jobs as $args)JRTG_Settings::send_test(...$args);
 sc(test_count('sent')===2,'Legacy execution cannot prevent modern scheduled test deliveries');
};
$cases['scheduler failure with unavailable result persistence']=function()use($old){
 test_fixture($old);$GLOBALS['async_failure']=true;$GLOBALS['cron_failure']=true;$GLOBALS['fail_health_after_schedule']=true;
 sc(settings_action('test')==='test_status_unavailable','Scheduler failure plus health DB failure reports unconfirmed diagnostics');
 sc(test_count('queued')===0,'Unscheduled tickets are never reported as confirmed queued after status-write failure');
};
$cases['test hook accepts batch arguments']=function(){
 $GLOBALS['recorded_actions']=[];JRTG_Settings::boot();
 $hooks=array_values(array_filter($GLOBALS['recorded_actions'],fn($args)=>$args[0]==='joyrent_telegram_subscriber_test'));
 sc(($hooks[0][3]??0)===4,'WordPress test worker receives batch, ticket and membership arguments');
};
$cases['fast test worker completion survives scheduling acknowledgment']=function()use($old){
 test_fixture($old);$GLOBALS['fast_test_worker']=true;
 sc(settings_action('test')==='test_queued'&&test_count('sent')===2&&test_count('queued')===0,'Fast worker acknowledgment is never overwritten with queued status');
};
$cases['multiple legacy jobs retain their batch results']=function()use($old){
 test_fixture($old);JRTG_Settings::send_test('987654321');$batch=test_health()['batch'];
 JRTG_Settings::send_test('987654322');
 sc(test_count('sent')===2&&test_health()['batch']===$batch,'Legacy jobs retain earlier safe outcomes in the same legacy batch');
};
$cases['test health unavailable before send']=function()use($old){
 test_fixture($old);settings_action('test');$GLOBALS['fail_write']=true;
 JRTG_Settings::send_test(...$GLOBALS['test_jobs'][0]);
 sc(JRTG_Api::$calls===[]&&count($GLOBALS['cron_jobs'])===1,'Worker defers without sending when it cannot persist its sending state');
 $GLOBALS['fail_write']=false;JRTG_Settings::send_test(...$GLOBALS['cron_jobs'][0]);
 sc(test_count('sent')===1,'Deferred test can complete after health persistence recovers');
};
$cases['unconfirmed scheduling lock failure']=function()use($old){
 test_fixture($old);$GLOBALS['async_failure']=true;$GLOBALS['cron_failure']=true;$GLOBALS['busy_health_after_schedule']=true;
 sc(settings_action('test')==='test_status_unavailable'&&test_count('planning')===2,'Busy result lock leaves truthful unconfirmed planning status');
 JR_Lock::$busy=false;$html=settings_html();
 sc(str_contains($html,'Планирование не подтверждено: 2'),'Admin shows unconfirmed scheduling rather than a false queued count');
};
$cases['safe unknown API error code']=function()use($old){
 test_fixture($old);settings_action('test');JRTG_Api::$send_result=['status'=>'failed','error'=>'PRIVATE_UNSAFE_ERROR '.$old['token']];
 JRTG_Settings::send_test(...$GLOBALS['test_jobs'][0]);$html=settings_html();
 sc(test_count('failed')===1&&!str_contains(json_encode(test_health()),'PRIVATE_UNSAFE_ERROR')&&!str_contains($html,$old['token']),'Unsafe API error values never enter health storage or admin HTML');
};
$cases['interrupted test displays uncertain result']=function()use($old){
 test_fixture($old);settings_action('test');$state=test_health();$ticket=array_key_first($state['results']);
 $state['results'][$ticket]=['status'=>'sending','updated_at'=>time()-61];$GLOBALS['options']['joyrent_telegram_test_status']=$state;
 $html=settings_html();
 sc(str_contains($html,'Результат неизвестен: 1')&&str_contains($html,'Отправка прервалась без подтверждения'),'Stale sending test is displayed as uncertain with a safe retry warning');
};
$failures=[];
foreach($cases as $label=>$case){try{$case();}catch(Throwable $e){$failures[]=$label;fwrite(STDERR,$e->getMessage()."\n");}}
if($failures)throw new RuntimeException('FAIL cases: '.implode(', ',$failures));
echo "PASS: $checks settings checks\n";
