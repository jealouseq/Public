<?php
// Public settings projection audit; no WordPress bootstrap, DB, orders, email or network.
define('ABSPATH','/isolated/');
$saved=[];$checks=0;$failures=[];
function get_option(string $name,mixed $default=false): mixed {return $name==='joyrent_settings'?$GLOBALS['saved']:$default;}
function update_option(...$args): never {throw new LogicException('Public projection must not write settings');}
function check(bool $value,string $label): void {$GLOBALS['checks']++;if(!$value)$GLOBALS['failures'][]=$label;}
require dirname(__DIR__,2).'/wordpress/joyrent-rentals/includes/settings.php';
set_error_handler(function($severity,$message){throw new ErrorException($message,0,$severity);});
$strings=['city','cityRu','phone','email','telegram','instagram','deliveryText','deliveryTextRu'];
$amounts=['delivery_fee'=>'deliveryFee','delivery_green_fee'=>'deliveryGreenFee','delivery_yellow_fee'=>'deliveryYellowFee','deposit_ps5'=>'depositPs5','deposit_ps4'=>'depositPs4','extra_controller_fee'=>'extraControllerFee'];
try {
 $s=JR_Settings::public();
 check($s['city']==='Одеса'&&$s['cityRu']==='Одесса'&&$s['email']==='info@joyrent.online','Approved public defaults preserved');
 check($s['depositPs5']===25000.0&&$s['depositPs4']===7500.0&&$s['deliveryFee']===null&&$s['deliveryGreenFee']===200.0&&$s['deliveryYellowFee']===300.0,'Known amounts and pending delivery preserved');
 check($s['baseControllers']===2&&$s['freeDeliveryFrom']===7&&$s['maxGames']===100&&$s['pickup']===false,'Default integer and boolean settings preserved');
 foreach($amounts as $key=>$public) {
  foreach([null,'',[],['bad'],new stdClass(),true,false,INF,-INF,NAN,-1,'-1','oops','1e9999'] as $value) {
   $saved=[$key=>$value];
   try {check(JR_Settings::public()[$public]===null,'Invalid amount is pending: '.$key.' '.get_debug_type($value));}
   catch(Throwable $e){check(false,'Malformed amount crashed: '.$key.' '.get_debug_type($value).' '.get_class($e));}
  }
  foreach([0=>0,1=>200,2=>200.25,3=>'0',4=>'200',5=>'200.25'] as $value) {$saved=[$key=>$value];check(JR_Settings::public()[$public]===(float)$value,'Explicit valid amount preserved: '.$key.' '.(string)$value);}
 }
 foreach([true,1,'1'] as $value){$saved=['pickup'=>$value];check(JR_Settings::public()['pickup']===true,'Explicit enabled pickup accepted '.get_debug_type($value));}
 foreach([false,0,'0','false','true','yes',[],['bad'],new stdClass(),null,''] as $value){$saved=['pickup'=>$value];check(JR_Settings::public()['pickup']===false,'Malformed/disabled pickup stays disabled '.get_debug_type($value));}
 foreach(['base_controllers'=>'baseControllers','free_delivery_from'=>'freeDeliveryFrom','max_games'=>'maxGames'] as $key=>$public){
  foreach([[],['bad'],new stdClass(),INF,NAN,'oops'] as $value){$saved=[$key=>$value];try{$s=JR_Settings::public();check(is_int($s[$public]),'Malformed integer projects usable int '.$key.' '.get_debug_type($value));}catch(Throwable $e){check(false,'Malformed integer crashed '.$key.' '.get_class($e));}}
 }
 $saved=['base_controllers'=>999,'free_delivery_from'=>999,'max_games'=>999];$s=JR_Settings::public();check($s['baseControllers']===2&&$s['freeDeliveryFrom']===30&&$s['maxGames']===100,'Upper integer bounds enforced');
 $saved=['base_controllers'=>0,'free_delivery_from'=>0,'max_games'=>0];$s=JR_Settings::public();check($s['baseControllers']===1&&$s['freeDeliveryFrom']===1&&$s['maxGames']===1,'Lower integer bounds enforced');
 $saved=['base_controllers'=>'1','free_delivery_from'=>'15','max_games'=>'75'];$s=JR_Settings::public();check($s['baseControllers']===1&&$s['freeDeliveryFrom']===15&&$s['maxGames']===75,'Valid legacy numeric-string integers preserved');
 $saved=['city'=>[],'city_ru'=>new stdClass(),'phone'=>[],'email'=>[],'telegram'=>[],'instagram'=>[],'delivery_text'=>[],'delivery_text_ru'=>[],'deposit_ps5'=>INF,'notification_email'=>'private@example.invalid','search_indexing'=>true,'owner_custom'=>['do'=>'not change']];
 try {$before=serialize($saved);$s=JR_Settings::public();foreach($strings as $key)check(is_string($s[$key]),'Published field remains string: '.$key);check(json_encode($s)!==false,'Malformed settings still produce valid JSON');check(serialize($saved)===$before,'Projection leaves saved option and unknown owner keys untouched');check(!isset($s['notification_email'],$s['search_indexing'],$s['owner_custom'])&&!str_contains(json_encode($s),'private@example.invalid'),'Admin/private settings remain excluded');}catch(Throwable $e){check(false,'Malformed string/settings shape crashed '.get_class($e));}
 $saved=['email'=>'','telegram'=>'','instagram'=>'','city'=>'','city_ru'=>'','delivery_text'=>'','delivery_text_ru'=>''];$s=JR_Settings::public();foreach(['email','telegram','instagram','city','cityRu','deliveryText','deliveryTextRu'] as $key)check($s[$key]==='','Explicit owner-cleared string preserved: '.$key);
 $saved=['city'=>'Owner city','delivery_text'=>'Owner delivery'];$s=JR_Settings::public();check($s['cityRu']===''&&$s['deliveryTextRu']==='','Custom untranslated copy never receives invented Odessa/Russian default');
 $saved=['city'=>'Київ','city_ru'=>'Киев','phone'=>'+380000000099','email'=>'owner@example.invalid','telegram'=>'https://t.me/owner_name','instagram'=>'https://www.instagram.com/owner_name/','delivery_text'=>'Owner delivery','delivery_text_ru'=>'Доставка владельца','owner_custom'=>'private'];
 $before=serialize($saved);$s=JR_Settings::public();check($s['city']===$saved['city']&&$s['cityRu']===$saved['city_ru']&&$s['phone']===$saved['phone']&&$s['email']===$saved['email']&&$s['telegram']===$saved['telegram']&&$s['instagram']===$saved['instagram']&&$s['deliveryText']===$saved['delivery_text']&&$s['deliveryTextRu']===$saved['delivery_text_ru'],'All valid custom public copy and contacts preserved exactly');check(serialize($saved)===$before&&JR_Settings::get()['owner_custom']==='private','Read-only raw getter keeps owner data unchanged');
 $saved=['city'=>str_repeat('М',10000),'city_ru'=>str_repeat('К',10000),'phone'=>str_repeat('1',10000),'email'=>str_repeat('x',10000),'telegram'=>str_repeat('t',10000),'instagram'=>str_repeat('i',10000),'delivery_text'=>str_repeat('😀',10000),'delivery_text_ru'=>str_repeat('Р',10000)];$s=JR_Settings::public();
 foreach($strings as $key)check(is_string($s[$key])&&mb_strlen($s[$key])<=4096&&preg_match('//u',$s[$key])===1,'Oversized public string bounded and valid UTF-8: '.$key);
}finally{restore_error_handler();}
echo json_encode(['checks'=>$checks,'failures'=>$failures,'realDatabaseWrites'=>0,'realMailCalls'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";exit($failures?1:0);
