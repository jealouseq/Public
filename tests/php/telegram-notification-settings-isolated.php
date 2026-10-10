<?php
define('ABSPATH','/isolated/');
define('MINUTE_IN_SECONDS',60);
$options=[];$checks=0;$failures=[];
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function wp_generate_uuid4(){static $i=0;return 'revision-'.++$i;}
function current_user_can($cap){return true;}
function get_current_user_id(){return 1;}
function get_transient($key){return false;}
function delete_transient($key){return true;}
function esc_html($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function esc_attr($value){return esc_html($value);}
function esc_url($value){return esc_html($value);}
function admin_url($path=''){return 'https://example.test/wp-admin/'.$path;}
function wp_nonce_field($action){echo '<input name="_wpnonce" value="fixture">';}
function settings_check($value,$label){global $checks,$failures;$checks++;if(!$value)$failures[]=$label;}
$path=dirname(__DIR__,2).'/wordpress/joyrent-telegram/includes/settings.php';
if(is_file($path))require $path;
if(!class_exists('JRTG_Settings')){
 settings_check(false,'Telegram settings class is available');echo json_encode(['checks'=>$checks,'failures'=>$failures])."\n";exit(1);
}
$token='123456789:'.str_repeat('a',35);
$old=['enabled'=>true,'token'=>$token,'chat_id'=>'123456789','enabled_since'=>100,'revision'=>'old-generation'];
$next=JRTG_Settings::candidate($old,['enabled'=>true,'token'=>'','chat_id'=>'123456789','clear_token'=>false],200);
settings_check($next['token']===$token,'Blank password preserves saved bot token');
settings_check($next['enabled_since']===100&&$next['revision']==='old-generation','Unchanged config preserves active queue generation');
$changed=JRTG_Settings::candidate($old,['enabled'=>true,'token'=>'','chat_id'=>'-100123456789','clear_token'=>false],200);
settings_check($changed['chat_id']==='-100123456789'&&$changed['revision']!=='old-generation'&&$changed['enabled_since']===200,'Changed recipient starts a new generation and cutoff');
$disabled=JRTG_Settings::candidate($old,['enabled'=>false,'token'=>'','chat_id'=>'123456789','clear_token'=>true],200);
settings_check($disabled['token']===''&&!$disabled['enabled'],'Explicit disabled clear removes token');
$reenabled=JRTG_Settings::candidate(array_merge($old,['enabled'=>false]),['enabled'=>true,'token'=>'','chat_id'=>'123456789','clear_token'=>false],250);
settings_check($reenabled['enabled_since']===250&&$reenabled['revision']!=='old-generation','Re-enabling starts future booking notifications');
foreach([
 ['token'=>'https://evil.test/x'],['token'=>['secret']],['chat_id'=>'@unknown'],['chat_id'=>'0'],['chat_id'=>'1?x=1'],
 ['chat_id'=>'123<script>'],['chat_id'=>str_repeat('1',21)],['token'=>'','clear_token'=>true],
] as $patch){
 try{JRTG_Settings::candidate($old,array_merge(['enabled'=>true,'token'=>'','chat_id'=>'123456789','clear_token'=>false],$patch),200);settings_check(false,'Invalid setting rejected '.json_encode($patch));}
 catch(InvalidArgumentException $e){settings_check(!str_contains($e->getMessage(),$token),'Invalid setting is rejected without secret leakage');}
}
$options['joyrent_telegram_settings']=$old;
settings_check(JRTG_Settings::ready(),'Valid enabled settings are ready');
ob_start();JRTG_Settings::page();$html=ob_get_clean();
settings_check(!str_contains($html,$token),'Stored bot token never appears in admin HTML');
settings_check(str_contains($html,'type="password"')&&str_contains($html,'value=""'),'Token field is empty and concealed');
settings_check(str_contains($html,'_wpnonce')&&str_contains($html,'jrtg_test')&&str_contains($html,'jrtg_discover'),'Admin connection actions carry nonce fields');
$options['joyrent_telegram_settings']=array_merge($old,['enabled'=>false]);
settings_check(!JRTG_Settings::ready(),'Disabled plugin is not ready');
$options['joyrent_telegram_settings']=array_merge($old,['token'=>'invalid-token']);
settings_check(!JRTG_Settings::ready(),'Corrupt credential never becomes ready');
echo json_encode(['checks'=>$checks,'failures'=>$failures],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
