<?php
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
$base=dirname(__DIR__,2).'/wordpress/joyrent-telegram/includes/';
$checks=0;
function check($ok,string $label):void {global $checks;$checks++;if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}}
function wp_strip_all_tags($s){return strip_tags($s);}
function wp_parse_url($s){return parse_url($s);}
function admin_url($s){return 'https://example.invalid/wp-admin/'.$s;}
class WC_Order {
 public array $meta=['_joyrent_console'=>'ps5','_joyrent_days'=>30,'_joyrent_controllers'=>2,'_joyrent_start_date'=>'2026-10-10','_joyrent_return_date'=>'2026-11-09','_joyrent_rental_amount'=>6000,'_joyrent_delivery'=>'pending','_joyrent_security_mode'=>'contract','_joyrent_deposit'=>'pending','_joyrent_method'=>'pickup','_joyrent_language'=>'uk','_joyrent_game_ids'=>['gta','mk'],'_joyrent_telegram'=>'@client_name'];
 public string $name='Владимир';public string $phone='+380500000077';public string $address='Фонтанская дорога, 10';
 public string $url='https://example.invalid/wp-admin/admin.php?page=wc-orders&action=edit&id=88';
 function get_meta($key){return $this->meta[$key]??'';}function get_order_number(){return 88;}
 function get_billing_first_name(){return $this->name;}function get_billing_phone(){return $this->phone;}
 function get_billing_address_1(){return $this->address;}function get_edit_order_url(){return $this->url;}
}
class JR_Games {static function records(){return [['id'=>'gta','title'=>'Grand Theft Auto V'],['id'=>'mk','title'=>'Mortal Kombat 1'],['id'=>'fc','title'=>'EA SPORTS FC 27'],['id'=>'ufc','title'=>'EA SPORTS UFC 6']];}}
require $base.'message.php';
$o=new WC_Order();$m=JRTG_Message::for_order($o);
check(str_contains($m,'<b>')&&!str_contains($m,'https://'),'Message emphasizes essential fields and hides raw admin URL');
check(str_contains($m,'JR-88')&&str_contains($m,'PS5')&&str_contains($m,'30 дней')&&str_contains($m,'2 геймпада'),'Main booking identity and rental details retained');
check(str_contains($m,'10.10 — 09.11.2026'),'Same-year date range avoids repeated year');
check(str_contains($m,'Владимир')&&str_contains($m,'UA')&&str_contains($m,'+380500000077')&&str_contains($m,'@client_name'),'Contact information and client language retained');
check(substr_count($m,'Самовывоз')===1&&!str_contains($m,'Доставка'),'Pickup appears once without a redundant delivery cost');
check(str_contains($m,'6 000 грн')&&str_contains($m,'Аренда')&&!str_contains($m,'Итого'),'Only rental cost is presented as rental, not an unconfirmed total');
check(str_contains($m,'Договор')&&str_contains($m,'залог')&&str_contains($m,'проверки документов'),'Contract and unconfirmed deposit conditions remain explicit');
check(str_contains($m,'Grand Theft Auto V')&&str_contains($m,'Mortal Kombat 1'),'Selected game titles retained');
check(!str_contains($m,'Оплачено')&&!str_contains($m,'Итог и наличие'),'No false paid claim or redundant closing disclaimer');
$options=JRTG_Message::options_for_order($o);
check(($options['parse_mode']??'')==='HTML','Composer requests Telegram HTML');
check(($options['reply_markup']['inline_keyboard'][0][0]??[])===['text'=>'Открыть бронь','url'=>$o->url],'One native button uses trusted WooCommerce edit URL');
$o->meta['_joyrent_start_date']='2026-12-31';$o->meta['_joyrent_return_date']='2027-01-02';$o->meta['_joyrent_days']=2;$o->meta['_joyrent_controllers']=1;
$m=JRTG_Message::for_order($o);
check(str_contains($m,'31.12.2026 — 02.01.2027'),'Cross-year dates retain both years');
check(str_contains($m,'2 дня')&&str_contains($m,'1 геймпад')&&!str_contains($m,'1 геймпадов'),'Small quantities use correct Russian forms');
foreach([1=>'1 день',5=>'5 дней',11=>'11 дней',21=>'21 день',22=>'22 дня']as$n=>$text){$o->meta['_joyrent_days']=$n;check(str_contains(JRTG_Message::for_order($o),$text),'Day plural '.$n);}
$o->meta['_joyrent_method']='delivery';$o->meta['_joyrent_security_mode']='deposit';$o->meta['_joyrent_deposit']=25000;$o->meta['_joyrent_extra_controller']='pending';
$m=JRTG_Message::for_order($o);
check(substr_count($m,'Доставка')===1&&str_contains($m,'стоимость согласуем')&&str_contains($m,$o->address),'Pending delivery retains address and provisional price once');
check(str_contains($m,'Возвратный залог')&&str_contains($m,'25 000 грн'),'Known refundable deposit retained');
check(str_contains($m,'Доп. геймпад')&&str_contains($m,'согласуем'),'Pending extra controller charge retained');
$o->meta['_joyrent_delivery']=0;check(str_contains(JRTG_Message::for_order($o),'Доставка · включена'),'Zero delivery is included');
$o->meta['_joyrent_delivery']=150;check(str_contains(JRTG_Message::for_order($o),'Доставка · 150 грн'),'Paid delivery shown without changing rental amount');
$o->meta['_joyrent_extra_controller']=200;check(str_contains(JRTG_Message::for_order($o),'Доп. геймпад · 200 грн'),'Known extra-controller charge retained');
$o->meta['_joyrent_game_ids']=[];$o->meta['_joyrent_requested_game']='';$o->meta['_joyrent_telegram']='';$o->meta['_joyrent_language']='ru';
$m=JRTG_Message::for_order($o);
check(!str_contains($m,'<b>Игры</b>')&&!str_contains($m,'Пожелание')&&!str_contains($m,'Telegram:')&&str_contains($m,'RU'),'Empty optional fields omitted');
$o->meta['_joyrent_requested_game']='Игра & вечер <b>вдвоём</b>';
$o->name='Анна & Сергей <a href="https://evil.invalid">Клиент</a>';$o->address='Дом <3 & квартира "5"';
$m=JRTG_Message::for_order($o);
check(str_contains($m,'Анна &amp; Сергей')&&!str_contains($m,'evil.invalid'),'Client HTML cannot inject links or formatting');
check(str_contains($m,'Игра &amp; вечер')&&str_contains($m,'Пожелание'),'Wish escaped and retained');
foreach(['https://evil.invalid/wp-admin/admin.php','javascript:alert(1)','https://user:secret@example.invalid/wp-admin/post.php','https://example.invalid/public/page','https://example.invalid/wp-admin/post.php#x']as$url){$o->url=$url;check(!isset(JRTG_Message::options_for_order($o)['reply_markup']),'Untrusted order URL cannot create a button');}
$o->url='https://example.invalid/wp-admin/post.php?post=88&action=edit';check(isset(JRTG_Message::options_for_order($o)['reply_markup']),'Classic CPT edit URL supported');
$o->meta['_joyrent_game_ids']=['gta','mk','fc','ufc'];$m=JRTG_Message::for_order($o);
check(str_contains($m,'ещё 1')&&!str_contains($m,'EA SPORTS UFC 6'),'Long game lists summarized with explicit remaining count');
$o->name=str_repeat('😀&',500);$o->address=str_repeat('😀&',500);$o->meta['_joyrent_requested_game']=str_repeat('😀&',500);$o->meta['_joyrent_game_ids']=array_fill(0,30,str_repeat('😀&',500));
$m=JRTG_Message::for_order($o);$visible=html_entity_decode(strip_tags($m),ENT_QUOTES|ENT_HTML5,'UTF-8');$units=0;foreach(preg_split('//u',$visible,-1,PREG_SPLIT_NO_EMPTY)as$c)$units+=strlen($c)===4?2:1;
check($units<=3900&&preg_match('//u',$m)===1,'Adversarial Unicode fits rendered Telegram UTF-16 limit');
check(substr_count($m,'<b>')===substr_count($m,'</b>')&&substr_count($m,'<i>')===substr_count($m,'</i>'),'Per-field truncation preserves complete formatting tags');
echo "PASS: $checks compact booking message checks\n";
