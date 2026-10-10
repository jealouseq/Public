<?php
define('ABSPATH','/isolated/');
define('MINUTE_IN_SECONDS',60);
$opts=[];$checks=0;$cache=[];$fail_write=false;
function get_option($key,$default=false){
 if(isset($GLOBALS['cache']['notoptions'][$key]))return $default;
 if(array_key_exists($key,$GLOBALS['cache']))return $GLOBALS['cache'][$key];
 if(!array_key_exists($key,$GLOBALS['opts'])){$GLOBALS['cache']['notoptions'][$key]=true;return $default;}
 return $GLOBALS['cache'][$key]=$GLOBALS['opts'][$key];
}
function update_option($key,$value,$autoload=null){
 if($GLOBALS['fail_write'])return false;
 if(($GLOBALS['opts'][$key]??null)===$value)return false;
 $GLOBALS['opts'][$key]=$value;unset($GLOBALS['cache'][$key],$GLOBALS['cache']['notoptions'][$key]);return true;
}
function wp_cache_delete($key,$group=''){unset($GLOBALS['cache'][$key]);return true;}
function wp_generate_uuid4(){static $n=0;return 'generation-'.++$n;}
function wp_check_password($plain,$hash){
 if(isset($GLOBALS['during_password_check'])){$callback=$GLOBALS['during_password_check'];unset($GLOBALS['during_password_check']);$callback();}
 return password_verify($plain,$hash);
}
function sanitize_text_field($s){return trim(strip_tags($s));}
class JR_Lock {static function acquire(...$a){return 'owner';}static function release(...$a){}}
class WP_Error {public function __construct(public $code,public $message,public $data=[]){}}
class WP_REST_Response {function __construct(public $data,public $status=200){}}
class WP_REST_Request {
 function __construct(public $headers=[],public $body=[]) {}
 function get_header($key){return $this->headers[$key]??'';}
 function get_body(){return json_encode($this->body);}
 function get_json_params(){return $this->body;}
}
class JRTG_Settings {
 static $data=[];
 static function get(){return self::$data;}
}
function subcheck($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException('FAIL: '.$label);}
$path=dirname(__DIR__,2).'/wordpress/joyrent-telegram/includes/subscriptions.php';
subcheck(is_file($path),'Subscriptions module exists');
require $path;
JRTG_Settings::$data=['token'=>'123456789:'.str_repeat('a',35),'webhook_connected'=>true,'password_hash'=>password_hash('fixture-pass',PASSWORD_BCRYPT),'access_revision'=>'access-1','webhook_secret'=>str_repeat('c',64)];
function update_fixture($id,$text,$user=101,$type='private'){return ['update_id'=>$id,'message'=>['chat'=>['id'=>$user,'type'=>$type,'first_name'=>'Fixture Owner'],'from'=>['id'=>$user,'is_bot'=>false],'text'=>$text]];}
subcheck(JRTG_Subscriptions::authorize(new WP_REST_Request()) instanceof WP_Error,'Forged webhook rejected');
subcheck(JRTG_Subscriptions::authorize(new WP_REST_Request(['x-telegram-bot-api-secret-token'=>str_repeat('c',64)]))===true,'Correct webhook secret accepted');
$r=JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(1,'/start')));
subcheck(str_contains($r->data['text']??'','Напишите пароль'),'Start asks for password');
$r=JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(2,'wrong')));
subcheck(str_contains($r->data['text']??'','Неверный пароль')&&JRTG_Subscriptions::all()===[],'Wrong password does not subscribe');
$r=JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(3,'fixture-pass')));
subcheck(count(JRTG_Subscriptions::all())===1&&str_contains($r->data['text']??'','подключены'),'Correct password subscribes account');
$before=JRTG_Subscriptions::all();
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(3,'fixture-pass')));
subcheck(JRTG_Subscriptions::all()===$before,'Duplicate update never recreates subscriber generation');
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(4,'/start',202)));
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(5,'fixture-pass',202)));
subcheck(count(JRTG_Subscriptions::all())===2,'Multiple authorized people subscribe independently');
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(6,'/stop',101)));
subcheck(count(JRTG_Subscriptions::all())===1&&!isset(JRTG_Subscriptions::all()['101']),'Stop removes only its own subscriber');
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(7,'fixture-pass',303)));
subcheck(!isset(JRTG_Subscriptions::all()['303']),'Password without start cannot subscribe');
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(8,'/start',404,'group')));
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(9,'fixture-pass',404,'group')));
subcheck(!isset(JRTG_Subscriptions::all()['404']),'Groups cannot subscribe with leaked password');
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(10,'/start',505)));
for($i=11;$i<=15;$i++)JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture($i,'wrong',505)));
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(16,'/start',505)));
JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(17,'fixture-pass',505)));
subcheck(!isset(JRTG_Subscriptions::all()['505']),'Start cannot bypass five-attempt throttle');
$serialized=json_encode($GLOBALS['opts']);
subcheck(!str_contains($serialized,'fixture-pass')&&!str_contains($serialized,JRTG_Settings::$data['token']),'Registry never stores plaintext password or token');
JRTG_Settings::$data['access_revision']='access-2';
subcheck(JRTG_Subscriptions::all()===[],'Password generation change invalidates prior subscribers');


$cases=[];
$cases['failed onboarding write']=function(){
 JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(100,'/start',606)));
 $GLOBALS['fail_write']=true;
 $r=JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(101,'fixture-pass',606)));
 subcheck($r instanceof WP_Error&&($r->data['status']??0)===503,'Failed subscription persistence asks Telegram to retry rather than announcing success');
 subcheck(!isset(JRTG_Subscriptions::all()['606']),'Failed subscription cannot become an active recipient');
 $GLOBALS['fail_write']=false;
 $r=JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(101,'fixture-pass',606)));
 subcheck(isset(JRTG_Subscriptions::all()['606'])&&str_contains($r->data['text']??'','Вы подключены!'),'Same update can recover after DB failure without being incorrectly deduplicated');
};
$cases['failed stop write']=function(){
 $GLOBALS['fail_write']=false;
 JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(102,'/start',707)));
 JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(103,'fixture-pass',707)));
 $GLOBALS['fail_write']=true;
 $r=JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(104,'/stop',707)));
 subcheck($r instanceof WP_Error&&($r->data['status']??0)===503,'Failed unsubscribe is never acknowledged as successful');
 subcheck(isset(JRTG_Subscriptions::all()['707']),'Failed unsubscribe preserves the previously persisted registry');
 $GLOBALS['fail_write']=false;
 JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(104,'/stop',707)));
 subcheck(!isset(JRTG_Subscriptions::all()['707']),'Unsubscribe retry can persist after DB recovers');
};
$cases['fresh negative registry cache']=function(){
 $GLOBALS['opts']=[];$GLOBALS['cache']=[];$GLOBALS['fail_write']=false;JRTG_Subscriptions::all();
 $GLOBALS['opts'][JRTG_Subscriptions::OPTION]=['bot'=>JRTG_Subscriptions::stamp(),'subscribers'=>['808'=>['generation'=>'external','label'=>'External']],'pending'=>[],'seen'=>[]];
 subcheck(isset(JRTG_Subscriptions::all()['808']),'Registry created by another worker is visible after a cached absence');
};
$cases['access rotation during onboarding']=function(){
 $GLOBALS['opts']=[];$GLOBALS['cache']=[];$GLOBALS['fail_write']=false;
 JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(200,'/start',909)));
 $before=$GLOBALS['opts'][JRTG_Subscriptions::OPTION];
 $GLOBALS['during_password_check']=function(){JRTG_Settings::$data['access_revision']='access-during-password';};
 $r=JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(201,'fixture-pass',909)));
 subcheck($r instanceof WP_Error&&($r->data['status']??0)===503,'Password rotation during onboarding never announces an old-generation subscription');
 subcheck($GLOBALS['opts'][JRTG_Subscriptions::OPTION]===$before,'Password rotation prevents persisting old-generation onboarding state');
 JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(202,'/start',909)));
 $r=JRTG_Subscriptions::receive(new WP_REST_Request([],update_fixture(203,'fixture-pass',909)));
 subcheck(isset(JRTG_Subscriptions::all()['909'])&&str_contains($r->data['text']??'','Вы подключены!'),'Fresh onboarding remains possible after access generation changed');
};
$failures=[];
foreach($cases as $label=>$case){try{$case();}catch(Throwable $e){$failures[]=$label;fwrite(STDERR,$e->getMessage()."\n");}finally{$GLOBALS['fail_write']=false;}}
if($failures)throw new RuntimeException('FAIL cases: '.implode(', ',$failures));
echo "PASS: $checks subscription checks\n";
