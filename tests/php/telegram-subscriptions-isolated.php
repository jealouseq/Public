<?php
define('ABSPATH','/isolated/');
define('MINUTE_IN_SECONDS',60);
$opts=[];$checks=0;
function get_option($key,$default=false){return $GLOBALS['opts'][$key]??$default;}
function update_option($key,$value,$autoload=null){$GLOBALS['opts'][$key]=$value;return true;}
function wp_cache_delete(...$args){}
function wp_generate_uuid4(){static $n=0;return 'generation-'.++$n;}
function wp_check_password($plain,$hash){return password_verify($plain,$hash);}
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
echo "PASS: $checks subscription checks\n";
