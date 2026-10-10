<?php
define('ABSPATH','/isolated/');define('MINUTE_IN_SECONDS',60);
$options=[];$checks=0;
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
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
echo "PASS: $checks settings checks\n";
