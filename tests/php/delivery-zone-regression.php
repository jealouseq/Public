<?php
// Disposable local WordPress only. All mail intercepted; saved settings/orders restored.
require '/var/www/html/wp-load.php';
$settings=get_option('joyrent_settings');$created=[];$checks=0;$failures=[];$mail_attempts=0;
add_filter('pre_wp_mail',function()use(&$mail_attempts){$mail_attempts++;return true;});
function delivery_check(bool $value,string $label):void{global $checks,$failures;$checks++;if(!$value)$failures[]=$label;}
function delivery_fixture(int $days,string $method,string|int $expected,bool $client_green=false): WC_Order {
    global $created;
    $date=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
    $payload=['console'=>'ps5','days'=>$days,'startDate'=>$date,'controllers'=>1,'gameIds'=>[],'name'=>'Локальна доставка','phone'=>'+380000000001','method'=>$method,'address'=>'Тестове місто, тестова адреса 1','consent'=>true,'deliveryZone'=>$client_green?'green':'unknown'];
    $data=JR_Domain::validate($payload,$date,JR_Games::records());$data['language']='uk';
    if($client_green){delivery_check(!array_key_exists('deliveryZone',$data),'Client zone is excluded by validation');$data['deliveryZone']='green';}
    $key=hash('sha256',wp_generate_uuid4());$receipt=JR_Orders::create($data,$key,hash('sha256',wp_json_encode($data)));$order=wc_get_order((int)substr($receipt['reference'],3));$created[]=$order->get_id();
    delivery_check((string)$order->get_meta('_joyrent_delivery')===(string)$expected,'Delivery '.$method.'/'.$days.($client_green?' claimed green':' unknown zone').' remains '.$expected);
    $rental=(float)JR_Store::product('ps5',$days)->get_price();delivery_check($receipt['rentalAmount']===$rental&&(float)$order->get_meta('_joyrent_rental_amount')===$rental,'Rental estimate unchanged '.$method.'/'.$days);
    delivery_check((float)$order->get_total()===$rental+(is_int($expected)?$expected:0),'Known fee only enters total '.$method.'/'.$days);
    return $order;
}
try {
    update_option('joyrent_settings',array_merge(JR_Settings::get(),['delivery_fee'=>'','free_delivery_from'=>7,'pickup'=>true,'base_controllers'=>2,'extra_controller_fee'=>0]));
    $historic=new WC_Order();$historic->update_meta_data('_joyrent_delivery',0);$historic->save();$created[]=$historic->get_id();
    delivery_fixture(3,'delivery','pending');
    foreach([7,30] as $days){$order=delivery_fixture($days,'delivery','pending');delivery_check($order->get_meta('_joyrent_delivery_free_eligibility')==='pending_zone_confirmation','Long rental eligibility awaits confirmed zone '.$days);$notes=implode(' ',array_map(fn($n)=>$n->content,wc_get_order_notes(['order_id'=>$order->get_id()])));delivery_check(str_contains($notes,'зеленій')&&str_contains($notes,'жовтій')&&str_contains($notes,'підтвердження'),'Long rental order note qualifies free delivery '.$days);}
    delivery_fixture(7,'delivery','pending',true);
    update_option('joyrent_settings',array_merge(JR_Settings::get(),['delivery_fee'=>200]));
    delivery_fixture(3,'delivery',200);delivery_fixture(7,'delivery','pending');delivery_fixture(30,'delivery','pending');
    delivery_fixture(7,'pickup',0);delivery_fixture(30,'pickup',0);
    $historic=wc_get_order($historic->get_id());delivery_check((string)$historic->get_meta('_joyrent_delivery')==='0'&&$historic->get_meta('_joyrent_delivery_free_eligibility')==='','Historical saved delivery metadata unchanged');
}finally{foreach($created as $id){$order=wc_get_order($id);if($order)$order->delete(true);}update_option('joyrent_settings',$settings);}
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures,'interceptedMailAttempts'=>$mail_attempts],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
