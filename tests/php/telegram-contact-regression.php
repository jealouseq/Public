<?php
// Disposable local WordPress only. Shop mail is intercepted; fixture orders/settings are restored.
require '/var/www/html/wp-load.php';
$checks=0;$failures=[];$created=[];$mail=[];$settings=get_option('joyrent_settings');$original_user=get_current_user_id();
add_filter('pre_wp_mail',function($return,$atts)use(&$mail){$mail[]=$atts;return true;},10,2);
function telegram_check(bool $value,string $label):void{global $checks,$failures;$checks++;if(!$value)$failures[]=$label;}
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
$payload=['console'=>'ps5','days'=>3,'startDate'=>$today,'controllers'=>2,'gameIds'=>[],'name'=>'Telegram Fixture','phone'=>'+380000000001','method'=>'pickup','address'=>'','consent'=>true];
try {
    update_option('joyrent_settings',array_merge(JR_Settings::get(),['notification_email'=>'telegram-private@example.test']));
    $legacy=JR_Domain::validate($payload,$today,[]);
    $data=JR_Domain::validate(array_merge($payload,['telegram'=>'https://t.me/Customer_Name/']),$today,[]);
    $key=hash('sha256',wp_generate_uuid4());$fingerprint=hash('sha256',wp_json_encode($data));
    $receipt=JR_Orders::create($data,$key,$fingerprint);$order=wc_get_order((int)substr($receipt['reference'],3));$created[]=$order->get_id();
    telegram_check($order->get_meta('_joyrent_telegram')==='@customer_name','Normalized customer Telegram reaches private order metadata');
    telegram_check(count($mail)===1&&str_contains($mail[0]['message'],"Telegram: @customer_name\n"),'Manager email includes customer Telegram once');
    telegram_check(substr_count($mail[0]['message'],'@customer_name')===1,'Manager email contains a single customer Telegram contact');
    telegram_check(!str_contains(wp_json_encode($receipt),'customer_name')&&!str_contains(wp_json_encode(JR_Store::catalog()),'customer_name'),'Customer Telegram is absent from public receipt/catalog');
    telegram_check(!str_contains($order->get_customer_note(),'customer_name'),'Customer Telegram is not duplicated in customer notes');
    foreach(wc_get_order_notes(['order_id'=>$order->get_id()]) as $note)telegram_check(!str_contains($note->content,'customer_name'),'Customer Telegram is not duplicated in order notes');
    $admins=get_users(['role'=>'administrator','number'=>1]);wp_set_current_user($admins[0]->ID);
    ob_start();JR_Orders::notification_admin($order);$ui=ob_get_clean();
    telegram_check(str_contains($ui,'@customer_name')&&str_contains($ui,'href="https://t.me/customer_name"'),'Manager admin block offers fixed Telegram profile link');
    wp_set_current_user(0);ob_start();JR_Orders::notification_admin($order);telegram_check(ob_get_clean()==='','Guest cannot read Telegram in manager order block');
    wp_set_current_user($admins[0]->ID);
    $order->update_meta_data('_joyrent_telegram','"><img src=x onerror=alert(1)>');$order->save();
    ob_start();JR_Orders::notification_admin($order);$ui=ob_get_clean();
    telegram_check(str_contains($ui,'&lt;img')&&!str_contains($ui,'<img')&&!str_contains($ui,'href="https://t.me/'),'Tampered Telegram metadata is escaped without a profile link');
    $order->update_meta_data('_joyrent_telegram','@customer_name');$order->set_billing_email('telegram-export@example.test');$order->save();
    $export=WC_Privacy_Exporters::order_data_exporter('telegram-export@example.test',1);$exported=false;
    foreach($export['data'] as $record)foreach($record['data'] as $field)if($field['name']==='Telegram'&&$field['value']==='@customer_name')$exported=true;
    telegram_check($exported,'WooCommerce order personal data export includes Telegram');
    WC_Privacy_Erasers::remove_order_personal_data($order);$order=wc_get_order($order->get_id());
    telegram_check($order->get_meta('_joyrent_telegram')===''&&$order->get_meta('_anonymized')==='yes','WooCommerce order erasure removes customer Telegram');
    $blankKey=hash('sha256',wp_generate_uuid4());$blankReceipt=JR_Orders::create($legacy,$blankKey,hash('sha256',wp_json_encode($legacy)));$blank=wc_get_order((int)substr($blankReceipt['reference'],3));$created[]=$blank->get_id();
    telegram_check($blank->get_meta('_joyrent_telegram')==='','Blank optional Telegram creates no customer metadata');
    telegram_check(count($mail)===2&&!str_contains($mail[1]['message'],'Telegram:'),'Blank optional Telegram adds no manager email row');
    ob_start();JR_Orders::notification_admin($blank);$blankUi=ob_get_clean();
    telegram_check(!str_contains($blankUi,'Telegram'),'Blank optional Telegram adds no admin row');
    telegram_check(count($mail)===2,'Privacy checks do not send extra notifications');
}finally{
    foreach($created as $id){$order=wc_get_order($id);if($order)$order->delete(true);}
    update_option('joyrent_settings',$settings);wp_set_current_user($original_user);
}
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures,'interceptedMailAttempts'=>count($mail)],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
