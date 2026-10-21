<?php
// Disposable local WordPress only. Mail is intercepted; orders/options/pages are restored.
require '/var/www/html/wp-load.php';
$checks=0;$failures=[];$created=[];$mail=[];$settings=get_option('joyrent_settings');$version=get_option('joyrent_version');$original_user=get_current_user_id();$request_ids=[];$remote_address=$_SERVER['REMOTE_ADDR']??null;
add_filter('pre_wp_mail',function($return,$atts)use(&$mail){$mail[]=$atts;return true;},10,2);
function options_check(bool $value,string $label):void{global $checks,$failures;$checks++;if(!$value)$failures[]=$label;}
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
$payload=['console'=>'ps5','days'=>3,'startDate'=>$today,'controllers'=>2,'gameIds'=>[],'name'=>'Локальна перевірка','phone'=>'+380000000001','method'=>'delivery','address'=>'Одеса, тестова адреса 1','consent'=>true];
try {
    update_option('joyrent_settings',array_merge(JR_Settings::get(),['notification_email'=>'private-fixture@example.test','delivery_fee'=>'','base_controllers'=>2,'extra_controller_fee'=>0,'deposit_ps4'=>7500,'deposit_ps5'=>25000]));
    $inventory=JR_Games::records();$legacy=JR_Domain::validate($payload,$today,$inventory);
    $explicit=JR_Domain::validate(array_merge($payload,['securityMode'=>'deposit','requestedGame'=>'  ']),$today,$inventory);
    options_check($legacy===$explicit&&!isset($explicit['securityMode'])&&!isset($explicit['requestedGame']),'Explicit defaults preserve legacy canonical request/fingerprint');
    $contract=JR_Domain::validate(array_merge($payload,['securityMode'=>'contract','requestedGame'=>'  Інша гра — Deluxe  ']),$today,$inventory);
    options_check(($contract['securityMode']??null)==='contract'&&($contract['requestedGame']??null)==='Інша гра — Deluxe','Contract choice and trimmed requested title survive validation');
    options_check(hash('sha256',wp_json_encode($legacy))!==hash('sha256',wp_json_encode($contract)),'Changed contract/title changes fingerprint');
    foreach([['securityMode'=>'unknown'],['securityMode'=>null],['securityMode'=>['deposit']],['requestedGame'=>null],['requestedGame'=>[]],['requestedGame'=>str_repeat('Я',121)],['requestedGame'=>"Name\nOther"],['requestedGame'=>'<script>alert(1)</script>'],['requestedGame'=>"Bad\x00title"]] as $changes){
        try{JR_Domain::validate(array_merge($payload,$changes),$today,$inventory);options_check(false,'Invalid option rejected '.array_key_first($changes));}catch(InvalidArgumentException $e){options_check(true,'Invalid option rejected '.array_key_first($changes));options_check(JR_Locale::message($e->getMessage(),'ru')!=='Не удалось обработать заявку. Попробуй ещё раз чуть позже.','New validation is translated into Russian');}
    }
    $unicode=JR_Domain::validate(array_merge($payload,['requestedGame'=>str_repeat('Я',120)]),$today,$inventory);options_check(mb_strlen($unicode['requestedGame']??'')===120,'120 Unicode characters are accepted without byte truncation');
    foreach([['ps5','deposit',25000],['ps4','deposit',7500],['ps5','contract','pending']] as [$console,$mode,$deposit]){
        $data=JR_Domain::validate(array_merge($payload,['console'=>$console,'securityMode'=>$mode,'requestedGame'=>'Hades II & Deluxe']),$today,$inventory);$data['language']='ru';$key=hash('sha256',wp_generate_uuid4());$fingerprint=hash('sha256',wp_json_encode($data));
        $receipt=JR_Orders::create($data,$key,$fingerprint);$order=wc_get_order((int)substr($receipt['reference'],3));$created[]=$order->get_id();
        options_check($order->get_meta('_joyrent_security_mode')===$mode,'Chosen security mode is persisted '.$console.'/'.$mode);
        options_check((string)$order->get_meta('_joyrent_deposit')===(string)$deposit,'Deposit stays financial/pending correctly '.$console.'/'.$mode);
        options_check($mode!=='contract'||$order->get_meta('_joyrent_security_status')==='pending_document_verification','Contract requires document verification');
        options_check($order->get_meta('_joyrent_requested_game')==='Hades II & Deluxe','Missing-game title reaches owner metadata');
        $amount=(float)JR_Store::product($console,3)->get_price();options_check($receipt===['reference'=>'JR-'.$order->get_order_number(),'rentalAmount'=>$amount,'status'=>'awaiting_confirmation']&&(float)$order->get_total()===$amount,'Security choice/title do not alter rental amount, totals or public receipt '.$mode);
        options_check(JR_Orders::existing($key,$fingerprint)===$receipt,'Saved option request replay returns the same receipt');
        $sent=$mail[array_key_last($mail)];options_check(str_contains($sent['message'],'Hades II & Deluxe')&&str_contains($sent['message'],$mode==='contract'?'перевірки документів':'Застава'),'Owner-only notification contains chosen mode and missing game');
        options_check(!str_contains(wp_json_encode($receipt),'private-fixture@example.test')&&!str_contains(wp_json_encode(JR_Store::catalog()),'private-fixture@example.test'),'Public catalog/receipt never expose private notification recipient');
        $admins=get_users(['role'=>'administrator','number'=>1]);wp_set_current_user($admins[0]->ID);$order->update_meta_data('_joyrent_requested_game','<img src=x onerror=alert(1)>');$order->save();ob_start();JR_Orders::notification_admin($order);$ui=ob_get_clean();
        options_check(str_contains($ui,'&lt;img')&&!str_contains($ui,'<img'),'Owner UI escapes even tampered game metadata');
        wp_set_current_user(0);ob_start();JR_Orders::notification_admin($order);options_check(ob_get_clean()==='','Rental option UI is restricted to store managers');
    }
    options_check(count($mail)===3,'Exactly one intercepted shop email for each test order');
    $_SERVER['REMOTE_ADDR']='127.0.0.239';
    $rest=function(array $body){$request=new WP_REST_Request('POST','/joyrent/v1/requests');$request->set_header('content-type','application/json');$request->set_body(wp_json_encode($body));return JR_REST::create($request);};
    foreach(['deposit','contract'] as $mode){
        $id=wp_generate_uuid4();$request_ids[]=$id;$body=array_merge($payload,['requestId'=>$id,'language'=>'ru']);
        if($mode==='contract')$body=array_merge($body,['securityMode'=>'contract','requestedGame'=>'Інша гра']);
        $response=$rest($body);options_check(!is_wp_error($response)&&$response->get_status()===201,'REST accepts '.$mode.' request');
        if(is_wp_error($response))continue;
        $receipt=$response->get_data();$created[]=(int)substr($receipt['reference'],3);$attempts=count($mail);
        $replay=$rest(array_merge($body,$mode==='deposit'?['securityMode'=>'deposit','requestedGame'=>'']:[]));
        options_check(!is_wp_error($replay)&&$replay->get_status()===200&&$replay->get_data()===$receipt&&count($mail)===$attempts,'REST replay is compatible/idempotent '.$mode);
        $conflict=$rest(array_merge($body,$mode==='deposit'?['securityMode'=>'contract']:['requestedGame'=>'Інша версія']));
        options_check(is_wp_error($conflict)&&$conflict->get_error_data()['status']===409&&count($mail)===$attempts,'Changing mode/title under one request ID conflicts without another email/order '.$mode);
        $cached=get_option('jr_result_'.hash_hmac('sha256',$id,wp_salt('nonce')));
        options_check(array_keys($cached)===['fingerprint','receipt']&&!str_contains(wp_json_encode($cached),'Інша гра')&&!str_contains(wp_json_encode($cached),$payload['name']),'Durable public retry cache excludes title/name/security document information');
    }
    $bad=$rest(array_merge($payload,['requestId'=>wp_generate_uuid4(),'language'=>'ru','securityMode'=>['contract']]));
    options_check(is_wp_error($bad)&&$bad->get_error_data()['status']===400&&$bad->get_error_message()==='Выбери оформление с залогом или по договору.','REST returns localized invalid-option error');
    options_check(count($mail)===5,'Exactly five emails intercepted; replay/invalid/conflict produce none');
}finally{foreach($created as $id){$order=wc_get_order($id);if($order)$order->delete(true);}foreach($request_ids as $id)delete_option('jr_result_'.hash_hmac('sha256',$id,wp_salt('nonce')));delete_transient('jr_rate_'.hash_hmac('sha256','127.0.0.239',wp_salt('auth')));if($remote_address===null)unset($_SERVER['REMOTE_ADDR']);else $_SERVER['REMOTE_ADDR']=$remote_address;update_option('joyrent_settings',$settings);update_option('joyrent_version',$version);wp_set_current_user($original_user);}
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures,'interceptedMailAttempts'=>count($mail)],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
