<?php
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
$base=dirname(__DIR__,2).'/wordpress/joyrent-telegram/includes/';
if(!is_file($base.'dispatcher.php')){fwrite(STDERR,"FAIL: New bookings have no independent bounded dispatcher\n");exit(1);}
$checks=0;function check($ok,$label){global$checks;$checks++;if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}}
$GLOBALS['opts']=[];$GLOBALS['locks']=[];$GLOBALS['http']=[];$GLOBALS['outbox']=[];$GLOBALS['sends']=0;$GLOBALS['write_fail']=false;$GLOBALS['delivery_mode']='normal';$GLOBALS['steps']=0;$GLOBALS['write_count']=0;$GLOBALS['fail_at']=0;$GLOBALS['before_write']=null;$GLOBALS['http_error']=false;$GLOBALS['observe_launch']=false;$GLOBALS['launch_locked']=false;$GLOBALS['registry_busy_attempts']=0;$GLOBALS['worker_busy_attempts']=0;
function get_option($k,$d=[]){return $GLOBALS['opts'][$k]??$d;}
function update_option($k,$v,$a=false){if($GLOBALS['observe_launch']&&isset($v['launch_at']))$GLOBALS['launch_locked']=isset($GLOBALS['locks']['jrtg_dispatch_worker']);$GLOBALS['write_count']++;if($GLOBALS['before_write']){ $f=$GLOBALS['before_write'];$GLOBALS['before_write']=null;$f(); }if($GLOBALS['write_fail']||($GLOBALS['fail_at']&&$GLOBALS['write_count']===$GLOBALS['fail_at']))return false;$GLOBALS['opts'][$k]=$v;return true;}
function wp_cache_delete(...$args){}
function wp_salt($kind){return 'disposable-signing-fixture';}
function wp_json_encode($x){return json_encode($x);}
function add_action(...$args){}
function register_rest_route(...$args){}
function is_wp_error($x){return $x instanceof WP_Error;}
function rest_url($path){return 'https://example.invalid/wp-json/'.$path;}
function wp_remote_post($url,$args){$GLOBALS['http'][]=[$url,$args];if($GLOBALS['http_error'])return new WP_Error('fixture','offline',[]);return ['response'=>['code'=>200]];}
class WP_Error{function __construct(public $code,public $message,public $data){}}
class WP_REST_Response{function __construct(public $data,public $status=200){}}
class WP_REST_Request{function __construct(public $body,public $key){}function get_body(){return $this->body;}function get_json_params(){return json_decode($this->body,true);}function get_header($key){return $this->key;}}
class JR_Lock{static function acquire($k,$ttl){if($k==='jrtg_dispatch_worker'&&$GLOBALS['worker_busy_attempts']>0){$GLOBALS['worker_busy_attempts']--;return false;}if($k==='jrtg_dispatch_registry'&&$GLOBALS['registry_busy_attempts']>0){$GLOBALS['registry_busy_attempts']--;return false;}if(isset($GLOBALS['locks'][$k]))return false;$GLOBALS['locks'][$k]='owner';return'owner';}static function release($k,$owner){unset($GLOBALS['locks'][$k]);}}
class JRTG_Notifications{
 static function pending_due_ids(){return array_keys(array_filter($GLOBALS['outbox'],static fn($s)=>!empty($s['backup'])&&!empty($s['left'])&&$s['at']<=time()));}
 static function next_due($id){$s=$GLOBALS['outbox'][$id]??[];return !empty($s['left'])?$s['at']:null;}
 static function inspect($id){return ['due'=>self::next_due($id),'marker'=>hash('sha256',json_encode($GLOBALS['outbox'][$id]??[]))];}
 static function deliver($id){$GLOBALS['steps']++;if($GLOBALS['delivery_mode']==='noop')return;if($GLOBALS['delivery_mode']==='throw')throw new RuntimeException('fixture');if($GLOBALS['delivery_mode']==='fail_replace')$GLOBALS['write_fail']=true;if(!empty($GLOBALS['outbox'][$id]['left'])){$GLOBALS['outbox'][$id]['left']--;$GLOBALS['sends']++;}}
}
require $base.'dispatcher.php';
$GLOBALS['outbox'][10]=['left'=>2,'at'=>time()];
JRTG_Dispatcher::wake(10);JRTG_Dispatcher::wake(10);
check(count(JRTG_Dispatcher::status()['jobs'])===1&&$GLOBALS['sends']===0&&!$GLOBALS['http'],'Durable wake deduplicates without HTTP in booking callback');
JRTG_Dispatcher::run();
check($GLOBALS['sends']===2&&!JRTG_Dispatcher::status()['jobs'],'One bounded worker drains current recipients independently of cron');
$before=$GLOBALS['sends'];JRTG_Dispatcher::run();check($GLOBALS['sends']===$before,'Empty/repeated worker does not send');
$GLOBALS['outbox'][20]=['left'=>10,'at'=>time()];JRTG_Dispatcher::wake(20);JRTG_Dispatcher::run();
check($GLOBALS['sends']-$before<=3&&isset(JRTG_Dispatcher::status()['jobs'][20]),'Worker caps sends and leaves durable remaining work');
$GLOBALS['outbox'][20]['at']=time()+120;JRTG_Dispatcher::run();$before=$GLOBALS['sends'];JRTG_Dispatcher::run();check($GLOBALS['sends']===$before,'Worker never spins/waits for future retry');
$GLOBALS['locks']['jrtg_dispatch_worker']='other';$before=$GLOBALS['sends'];JRTG_Dispatcher::run();check($GLOBALS['sends']===$before,'Single global worker limits shared-host load');unset($GLOBALS['locks']['jrtg_dispatch_worker']);

$GLOBALS['opts']['joyrent_telegram_dispatch']=[];$GLOBALS['outbox'][30]=['left'=>2,'at'=>time()];JRTG_Dispatcher::wake(30);
$GLOBALS['delivery_mode']='noop';$beforeSteps=$GLOBALS['steps'];$beforeHttp=count($GLOBALS['http']);JRTG_Dispatcher::run();
check($GLOBALS['steps']-$beforeSteps===1&&count($GLOBALS['http'])===$beforeHttp,'Contended/no-progress delivery stops immediate self-request chain');
check((JRTG_Dispatcher::status()['jobs'][30]??0)>time(),'No-progress work remains durable with a future backoff');
$GLOBALS['opts']['joyrent_telegram_dispatch']=[];$GLOBALS['outbox'][31]=['left'=>2,'at'=>time()];JRTG_Dispatcher::wake(31);
$GLOBALS['delivery_mode']='fail_replace';$beforeHttp=count($GLOBALS['http']);JRTG_Dispatcher::run();
check(count($GLOBALS['http'])===$beforeHttp,'Failed registry persistence stops handoff');$GLOBALS['write_fail']=false;
$GLOBALS['opts']['joyrent_telegram_dispatch']=[];$GLOBALS['outbox'][32]=['left'=>2,'at'=>time()];JRTG_Dispatcher::wake(32);
$GLOBALS['delivery_mode']='throw';$beforeHttp=count($GLOBALS['http']);JRTG_Dispatcher::run();
check(count($GLOBALS['http'])===$beforeHttp&&isset(JRTG_Dispatcher::status()['jobs'][32]),'Thrown delivery retains work without endless loopback');$GLOBALS['delivery_mode']='normal';
$GLOBALS['opts']['joyrent_telegram_dispatch']=[];$GLOBALS['outbox'][33]=['left'=>2,'at'=>time()];JRTG_Dispatcher::wake(33);
$GLOBALS['write_fail']=true;$beforeSteps=$GLOBALS['steps'];$beforeHttp=count($GLOBALS['http']);JRTG_Dispatcher::run();$GLOBALS['write_fail']=false;
check($GLOBALS['steps']===$beforeSteps&&count($GLOBALS['http'])===$beforeHttp,'Failed worker status persistence stops before external delivery');

$replace=new ReflectionMethod(JRTG_Dispatcher::class,'replace_job');
$GLOBALS['outbox'][40]=['left'=>0,'at'=>time()];$new='new-generation';
$GLOBALS['opts']['joyrent_telegram_dispatch']=['jobs'=>[40=>time()],'generations'=>[40=>$new]];
$replace->invoke(null,40,'old-generation',['due'=>null,'marker'=>'old']);
check((JRTG_Dispatcher::status()['generations'][40]??null)===$new&&isset(JRTG_Dispatcher::status()['jobs'][40]),'Newer wake survives stale terminal worker replacement');
$GLOBALS['outbox'][40]=['left'=>1,'at'=>time()+180];
$replace->invoke(null,40,$new,['due'=>time(),'marker'=>'old']);
check(JRTG_Dispatcher::status()['jobs'][40]===$GLOBALS['outbox'][40]['at'],'Fresh authoritative future retry is not replaced by stale immediate deadline');
$kick=new ReflectionMethod(JRTG_Dispatcher::class,'kick');$GLOBALS['opts']['joyrent_telegram_dispatch']=[];
$GLOBALS['observe_launch']=true;$beforeHttp=count($GLOBALS['http']);$kick->invoke(null);$kick->invoke(null);$GLOBALS['observe_launch']=false;
check(count($GLOBALS['http'])===$beforeHttp+1,'Concurrent kick attempts coalesce to one self-request');
check($GLOBALS['launch_locked'],'Launch reservation is written while holding worker probe');
$GLOBALS['opts']['joyrent_telegram_dispatch']=[];$GLOBALS['http_error']=true;$kick->invoke(null);$GLOBALS['http_error']=false;
check(!isset(JRTG_Dispatcher::status()['launch_at'])&&(JRTG_Dispatcher::status()['error']??'')==='loopback_failed','Failed loopback clears launch reservation and exposes safe error');
$beforeHttp=count($GLOBALS['http']);$kick->invoke(null);check(count($GLOBALS['http'])===$beforeHttp+1,'Later wake can retry failed loopback');
$GLOBALS['outbox'][41]=['left'=>4,'at'=>time()];JRTG_Dispatcher::wake(41);$beforeHttp=count($GLOBALS['http']);JRTG_Dispatcher::run();
check(count($GLOBALS['http'])===$beforeHttp+1,'Started worker clears launch reservation and hands off bounded remainder');

$GLOBALS['opts']['joyrent_telegram_dispatch']=[];$GLOBALS['outbox'][50]=['left'=>2,'at'=>time(),'backup'=>true];
$GLOBALS['locks']['jrtg_dispatch_registry']='other';JRTG_Dispatcher::wake(50);unset($GLOBALS['locks']['jrtg_dispatch_registry']);$before=$GLOBALS['sends'];JRTG_Dispatcher::run();
check($GLOBALS['sends']===$before+2,'Worker recovers a durable booking whose registry wake lost a concurrent lock');
$GLOBALS['outbox'][51]=['left'=>2,'at'=>time(),'backup'=>true];JRTG_Dispatcher::wake(51);$GLOBALS['delivery_mode']='noop';JRTG_Dispatcher::run();$GLOBALS['delivery_mode']='normal';$before=$GLOBALS['sends'];$beforeHttp=count($GLOBALS['http']);JRTG_Dispatcher::run();
check($GLOBALS['sends']===$before&&count($GLOBALS['http'])===$beforeHttp,'Backup discovery preserves existing contention backoff');
$GLOBALS['opts']['joyrent_telegram_dispatch']=[];$GLOBALS['outbox']=[];$GLOBALS['outbox'][60]=['left'=>2,'at'=>time()];$GLOBALS['registry_busy_attempts']=2;JRTG_Dispatcher::wake(60);
check(isset(JRTG_Dispatcher::status()['jobs'][60]),'Short concurrent registry ownership is awaited within a bounded acquisition budget');
$before=$GLOBALS['sends'];$GLOBALS['registry_busy_attempts']=2;JRTG_Dispatcher::run();
check($GLOBALS['sends']===$before+2,'Worker begins delivery after short producer mutex contention');
$GLOBALS['opts']['joyrent_telegram_dispatch']=[];$GLOBALS['outbox']=[];$GLOBALS['outbox'][61]=['left'=>2,'at'=>time()];JRTG_Dispatcher::wake(61);$GLOBALS['worker_busy_attempts']=2;$before=$GLOBALS['sends'];JRTG_Dispatcher::run();
check($GLOBALS['sends']===$before+2,'Actual dispatch waits briefly for a launch probe instead of abandoning all bookings');
$GLOBALS['opts']['joyrent_telegram_dispatch']=['launch_at'=>time()];$GLOBALS['worker_busy_attempts']=2;$kick->invoke(null);
check($GLOBALS['worker_busy_attempts']===2,'Coalesced sibling launch never acquires the real worker probe');$GLOBALS['worker_busy_attempts']=0;
$at=time();$nonce=str_repeat('a',32);$body=json_encode(['at'=>$at,'nonce'=>$nonce]);$key=hash_hmac('sha256','dispatch|'.$at.'|'.$nonce,wp_salt('auth'));
check(JRTG_Dispatcher::authorize(new WP_REST_Request($body,$key))===true,'Signed self-dispatch authorized');
check(JRTG_Dispatcher::authorize(new WP_REST_Request($body,'forged'))instanceof WP_Error,'Forged worker request rejected');
$stale=json_encode(['at'=>$at-120,'nonce'=>$nonce]);check(JRTG_Dispatcher::authorize(new WP_REST_Request($stale,$key))instanceof WP_Error,'Expired worker request rejected');
check(JRTG_Dispatcher::authorize(new WP_REST_Request(str_repeat('x',2048),$key))instanceof WP_Error,'Oversized worker request rejected');
check(JRTG_Dispatcher::authorize(new WP_REST_Request('42',$key))instanceof WP_Error,'Scalar JSON rejected');
check(JRTG_Dispatcher::authorize(new WP_REST_Request('null',$key))instanceof WP_Error,'Null JSON rejected');
echo "PASS: $checks independent dispatcher checks\n";
