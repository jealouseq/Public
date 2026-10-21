<?php
// Local WordPress settings migration; no requests and no mail.
require '/var/www/html/wp-load.php';
$settings=get_option('joyrent_settings');$version=get_option('joyrent_version');$checks=0;$failures=[];
function settings_check(bool $value,string $label):void{global $checks,$failures;$checks++;if(!$value)$failures[]=$label;}
try {
    update_option('joyrent_settings',['city'=>'','phone'=>'','telegram'=>'','deposit_ps4'=>'','deposit_ps5'=>'','base_controllers'=>1,'extra_controller_fee'=>'','notification_email'=>'private@example.test','delivery_text'=>'Вкажи місто та адресу у заявці. Ми перевіримо можливість доставки й узгодимо час отримання та повернення.']);
    update_option('joyrent_version','1.5.0');JR_Store::upgrade();$s=JR_Settings::get();$p=JR_Settings::public();
    settings_check($p['city']==='Одеса'&&$p['cityRu']==='Одесса','Approved Odessa translations fill blank settings');
    settings_check($p['depositPs4']===7500.0&&$p['depositPs5']===25000.0,'Approved deposits fill blank settings');
    settings_check($p['baseControllers']===2&&$p['extraControllerFee']===0.0,'Unconfigured included controller pair upgrades to two free controllers');
    settings_check($p['deliveryFee']===null&&$p['deliveryGreenFee']===200.0&&$p['deliveryYellowFee']===300.0&&$p['freeDeliveryFrom']===7,'Zone costs configured while address-dependent short fee remains unknown');
    settings_check($p['deliveryText']===JR_Settings::defaults()['delivery_text'],'Only managed old delivery text replaced');
    settings_check($s['notification_email']==='private@example.test'&&!str_contains(wp_json_encode($p),'private@example.test'),'Private notification recipient preserved without public leakage');
    settings_check($p['phone']==='+380996669946'&&$p['telegram']==='https://t.me/joyrent_od','Approved public contacts fill blank settings');
    settings_check(JR_Settings::defaults()['phone']==='+380996669946'&&JR_Settings::defaults()['telegram']==='https://t.me/joyrent_od','Fresh-install public contacts use approved defaults');
    settings_check($p['email']===''&&JR_Settings::defaults()['email']===''&&JR_Settings::defaults()['notification_email']==='','No public email is invented and private recipient has no source default');
    $custom=['city'=>'Owner city','city_ru'=>'Город владельца','phone'=>'+380000000099','telegram'=>'https://t.me/owner_local','deposit_ps4'=>0,'deposit_ps5'=>9000,'base_controllers'=>1,'extra_controller_fee'=>75,'delivery_fee'=>125,'delivery_green_fee'=>150,'delivery_yellow_fee'=>250,'delivery_text'=>'Owner delivery','delivery_text_ru'=>'Авторская доставка','notification_email'=>'private@example.test'];
    update_option('joyrent_settings',$custom);update_option('joyrent_version','1.5.0');JR_Store::upgrade();$s=JR_Settings::get();
    foreach($custom as $key=>$value)settings_check($s[$key]===$value,'Configured owner setting preserved '.$key);
    update_option('joyrent_settings',['notification_email'=>'private@example.test']);update_option('joyrent_version','1.5.0');JR_Store::upgrade();$missing=get_option('joyrent_settings');settings_check(($missing['phone']??null)==='+380996669946'&&($missing['telegram']??null)==='https://t.me/joyrent_od','Approved public contacts are stored when keys were missing');
    update_option('joyrent_settings',['city'=>'Owner city']);settings_check(JR_Settings::get()['city_ru']==='','Custom city has no invented Odessa translation');
    $sanitized=JR_Settings::sanitize(['notification_email'=>'private@example.test','max_games'=>150,'delivery_green_fee'=>'200.50','city_ru'=>'Одесса']);
    settings_check($sanitized['notification_email']==='private@example.test'&&$sanitized['max_games']===100&&$sanitized['delivery_green_fee']===200.5,'Private email, catalog bound and zone amount sanitize');
} finally {update_option('joyrent_settings',$settings);update_option('joyrent_version',$version);}
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures,'savedSettingsRestored'=>true],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
