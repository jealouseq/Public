<?php
declare(strict_types=1);
$path = __DIR__ . '/../../wordpress/joyrent-rentals/includes/domain.php';
if (!file_exists($path)) { fwrite(STDERR, "FAIL: rental domain implementation missing\n"); exit(1); }
require $path;
$checks = 0;
function check(bool $actual, string $name): void { global $checks; $checks++; if (!$actual) { throw new RuntimeException('FAIL: ' . $name); } }
foreach ([['ps5',1,600],['ps5',3,1400],['ps5',7,2500],['ps5',30,6000],['ps4',3,750],['ps4',7,1200],['ps4',30,2500]] as [$console,$days,$price]) {
    check(JR_Domain::tariff($console, $days)['price'] === $price, "$console/$days amount");
}
try { JR_Domain::tariff('ps4',1); check(false,'unsupported tariff'); } catch (InvalidArgumentException $e) { check(true,'unsupported tariff rejected'); }
check(JR_Domain::return_date('2026-10-24',3) === '2026-10-27','DST calendar boundary');
check(JR_Domain::return_date('2028-02-28',1) === '2028-02-29','leap day');
try { JR_Domain::return_date('2026-02-30',3); check(false,'invalid date'); } catch (InvalidArgumentException $e) { check(true,'invalid date rejected'); }
check(JR_Domain::language([]) === 'uk','default UI language');
check(JR_Domain::language(['language'=>'ru']) === 'ru','Russian UI language');
try { JR_Domain::language(['language'=>['ru']]); check(false,'array language'); } catch (InvalidArgumentException $e) { check(true,'array language rejected'); }
try { JR_Domain::language(['language'=>'en']); check(false,'unsupported language'); } catch (InvalidArgumentException $e) { check(true,'unsupported language rejected'); }
$payload=['console'=>'ps5','days'=>3,'startDate'=>'2026-10-20','controllers'=>2,'gameIds'=>[],'name'=>'Domain Test','phone'=>'+380000000001','method'=>'pickup','address'=>'','consent'=>true];
$legacy=JR_Domain::canonical($payload);
// Recorded from release 1.8.4: blank optional contact must preserve existing retries.
check(hash('sha256',json_encode($legacy))==='01806400c1a1dce557ff7448bc747e7025099bb197385ff102c094d26e0fcab4','Blank contact keeps release canonical fingerprint');
check(JR_Domain::intent_fingerprint($legacy)==='046c23ecec79411269fa0db5c387e5d2a11861f5bc9284a85090c3deb0de608f','Blank contact keeps release intent fingerprint');
foreach (['','   '] as $blank) {
    $empty=JR_Domain::canonical(array_merge($payload,['telegram'=>$blank]));
    check($empty===$legacy,'Blank optional Telegram preserves legacy canonical data');
    check(JR_Domain::intent_fingerprint($empty)===JR_Domain::intent_fingerprint($legacy),'Blank optional Telegram preserves legacy intent');
}
foreach (['User_Name','@User_Name','https://t.me/User_Name','https://t.me/User_Name/','  @User_Name  ',str_repeat('a',5),str_repeat('b',32)] as $input) {
    $actual=JR_Domain::canonical(array_merge($payload,['telegram'=>$input]));
    $expected=str_contains($input,'User_Name')?'@user_name':'@'.$input;
    check(($actual['telegram']??null)===$expected,'Telegram contact is normalized');
}
$contact=JR_Domain::canonical(array_merge($payload,['telegram'=>'@User_Name']));
check(JR_Domain::intent_fingerprint($contact)!==JR_Domain::intent_fingerprint($legacy),'Added Telegram changes booking intent');
$other=JR_Domain::canonical(array_merge($payload,['telegram'=>'another_user']));
check(JR_Domain::intent_fingerprint($contact)!==JR_Domain::intent_fingerprint($other),'Changed Telegram changes booking intent');
foreach ([null,[],false,123,'@','abcd',str_repeat('a',33),'@@username','user name','user-name','ІмяКористувача','https://evil.test/username','https://t.me.evil.test/username','http://t.me/username','https://t.me/username/123','https://t.me/username?start=secret','https://t.me/username#profile','https://t.me/+380000000001','<b>username</b>',"user\nname","username\0","username\xff",str_repeat(' ',1000).'username'] as $input) {
    try { JR_Domain::canonical(array_merge($payload,['telegram'=>$input])); check(false,'Invalid Telegram rejected'); }
    catch (InvalidArgumentException $e) { check($e->getMessage()==='Вкажи Telegram у форматі @username або https://t.me/username.','Invalid Telegram has a specific validation message'); }
}
echo "PASS: $checks PHP domain assertions\n";
