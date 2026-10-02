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
echo "PASS: $checks PHP domain assertions\n";
