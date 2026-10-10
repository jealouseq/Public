<?php
// Production domain/REST/order lookup with in-memory boundaries. No wp-load, DB or mail.
define('ABSPATH', '/isolated/');
define('MINUTE_IN_SECONDS', 60);
final class WooCommerce {}
final class WP_REST_Request {
    public function __construct(private mixed $payload) {}
    public function get_json_params(): mixed { return $this->payload; }
}
final class WP_REST_Response {
    public function __construct(private array $data, private int $status) {}
    public function get_status(): int { return $this->status; }
    public function get_data(): array { return $this->data; }
}
final class WP_Error {
    public function __construct(private string $code, private string $message, private array $data) {}
    public function get_error_data(): array { return $this->data; }
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}
final class WC_Product {
    public function get_status(): string { return 'publish'; }
    public function get_price(): string { return '1400'; }
    public function is_in_stock(): bool { return false; }
}
final class WC_Order {
    public function __construct(private array $meta, private array $billing = [],private string $status='jr-request') {}
    public function get_meta(string $key): mixed { return $this->meta[$key] ?? ''; }
    public function get_status(): string { return $this->status; }
    public function get_order_number(): int { return 42; }
    public function get_billing_first_name(): string { return $this->billing['name'] ?? ''; }
    public function get_billing_phone(): string { return $this->billing['phone'] ?? ''; }
    public function get_billing_address_1(): string { return $this->billing['address'] ?? ''; }
}
final class JR_Settings {
    public static array $settings=['maxGames'=>100,'pickup'=>true];
    public static function public(): array { return self::$settings; }
}
final class JR_Games {
    public static array $inventory=[['id'=>'game-a','platforms'=>['ps5'],'available'=>false],['id'=>'game-b','platforms'=>['ps5'],'available'=>false]];
    public static function records(): array { return self::$inventory; }
}
$options=[]; $orders=[]; $reads=[]; $transients=[]; $checks=0; $failures=[];
function get_option(string $key, mixed $default=false): mixed { global $options,$reads; $reads[]=$key; return $options[$key]??$default; }
function add_option(string $key, mixed $value, mixed $deprecated='', mixed $autoload='yes'): bool { global $options; if(isset($options[$key]))return false; $options[$key]=$value; return true; }
function get_woocommerce_currency(): string { return 'UAH'; }
function wp_salt(string $scheme): string { return 'isolated-'.$scheme; }
function wp_json_encode(mixed $value): string { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
function wp_generate_uuid4(): string { return 'lock-owner-uuid'; }
function wp_cache_delete(...$args): void {}
function get_transient(string $key): mixed { return $GLOBALS['transients'][$key]??false; }
function set_transient(string $key,mixed $value,int $ttl): void { $GLOBALS['transients'][$key]=$value; }
function wc_get_orders(array $query): array { return array_values(array_filter($GLOBALS['orders'],fn($order)=>$order->get_meta('_joyrent_request_key')===$query['joyrent_request_key'])); }
function wc_get_product_id_by_sku(string $sku): int { return 1; }
function wc_get_product(int $id): WC_Product { return new WC_Product(); }
function wp_mail(...$args): never { throw new LogicException('Real mail forbidden'); }
$wpdb=new class {
    public string $options='isolated_options';
    public function prepare(string $sql,mixed ...$params): array { return [$sql,$params]; }
    public function query(array $query): int {
        [$sql,$params]=$query; $key=$params[0];
        if(str_starts_with($sql,'INSERT IGNORE')) { if(isset($GLOBALS['options'][$key])) return 0; $GLOBALS['options'][$key]=$params[1]; return 1; }
        if(str_starts_with($sql,'DELETE')) { if(($GLOBALS['options'][$key]??null)===$params[1])unset($GLOBALS['options'][$key]); return 1; }
        throw new LogicException('Unexpected database boundary');
    }
};
$plugin=getenv('JR_TEST_PLUGIN_PATH')?:dirname(__DIR__,2).'/wordpress/joyrent-rentals';
foreach(['domain','locale','store','orders','rest'] as $file)require $plugin.'/includes/'.$file.'.php';
function check_request(bool $condition,string $label): void { global $checks,$failures; $checks++; if(!$condition)$failures[]=$label; }
function status(mixed $response): int { return $response instanceof WP_Error?$response->get_error_data()['status']:$response->get_status(); }
function call_request(array $payload): mixed { try{return JR_REST::create(new WP_REST_Request($payload));}catch(Throwable $e){return $e;} }
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
$yesterday=(new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
$payload=['requestId'=>'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','console'=>'ps5','days'=>3,'startDate'=>$today,'controllers'=>2,'gameIds'=>['game-a','game-b'],'name'=>'Audit Test','phone'=>'+380000000001','method'=>'pickup','address'=>'','consent'=>true];
$receipt=['reference'=>'JR-42','rentalAmount'=>1400.0,'status'=>'awaiting_confirmation'];
function fixture_receipt(array $payload,string $when,string $status='jr-request'): array {
    $GLOBALS['options']=[];$GLOBALS['orders']=[];$GLOBALS['reads']=[];
    JR_Settings::$settings=['maxGames'=>100,'pickup'=>true];
    JR_Games::$inventory=[['id'=>'game-a','platforms'=>['ps5'],'available'=>false],['id'=>'game-b','platforms'=>['ps5'],'available'=>false]];
    $data=JR_Domain::validate($payload,$when,JR_Games::records());
    $key=hash_hmac('sha256',$payload['requestId'],wp_salt('nonce'));
    $fingerprint=hash('sha256',wp_json_encode($data));
    $GLOBALS['options']['jr_result_'.$key]=['fingerprint'=>$fingerprint,'receipt'=>$GLOBALS['receipt']];
    $meta=['_joyrent_request_key'=>$key,'_joyrent_fingerprint'=>$fingerprint,'_joyrent_completed'=>'yes','_joyrent_rental_amount'=>1400.0];
    foreach(['console'=>'console','days'=>'days','start_date'=>'startDate','controllers'=>'controllers','game_ids'=>'gameIds','method'=>'method'] as $stored=>$field)$meta['_joyrent_'.$stored]=$data[$field];
    if (isset($data['telegram'])) $meta['_joyrent_telegram']=$data['telegram'];
    $GLOBALS['orders'][]=new WC_Order($meta,$data,$status);
    return [$key,$data];
}
fixture_receipt($payload,$today);check_request(status(call_request($payload))===200,'Unchanged legacy cached receipt replays');
fixture_receipt($payload,$today);JR_Games::$inventory=[];check_request(status(call_request($payload))===200,'Published game removal does not reject completed receipt');
fixture_receipt($payload,$today);JR_Settings::$settings['pickup']=false;check_request(status(call_request($payload))===200,'Pickup setting change does not reject completed receipt');
$past=$payload;$past['startDate']=$yesterday;fixture_receipt($past,$yesterday);check_request(status(call_request($past))===200,'Past original date does not reject completed receipt');
fixture_receipt($payload,$today);$changed=$payload;$changed['controllers']=1;check_request(status(call_request($changed))===409,'Changed completed intent stays a conflict');
fixture_receipt($payload,$today);$changed=$payload;$changed['securityMode']='contract';check_request(status(call_request($changed))===409,'Changed security mode stays a conflict');
fixture_receipt($payload,$today);$changed=$payload;$changed['requestedGame']='Another game';check_request(status(call_request($changed))===409,'Changed requested title stays a conflict');
fixture_receipt($payload,$today);$equivalent=$payload;$equivalent['language']='ru';$equivalent['securityMode']='deposit';$equivalent['requestedGame']='  ';check_request(status(call_request($equivalent))===200,'Language and explicit empty/default options preserve legacy intent');
fixture_receipt($payload,$today);$equivalent=$payload;$equivalent['gameIds']=['game-b','game-a','game-a'];$equivalent['phone']='+380 (00) 000-00-01';check_request(status(call_request($equivalent))===200,'Legacy order recovery accepts equivalent game set and phone formatting');
[$key]=fixture_receipt($payload,$today);unset($options['jr_result_'.$key]);JR_Games::$inventory=[];JR_Settings::$settings['pickup']=false;check_request(status(call_request($payload))===200,'Durable Woo order recovers before current eligibility');
fixture_receipt($payload,$today);$empty=$payload;$empty['telegram']='  ';check_request(status(call_request($empty))===200,'Empty Telegram preserves pre-feature cached receipts');
fixture_receipt($payload,$today);$changed=$payload;$changed['telegram']='@customer_one';check_request(status(call_request($changed))===409,'Adding Telegram to completed booking conflicts');
$contact=$payload;$contact['telegram']='@Customer_One';[$key]=fixture_receipt($contact,$today);unset($options['jr_result_'.$key]);
$equivalent=$contact;$equivalent['telegram']='https://t.me/customer_one/';$equivalent['gameIds']=['game-b','game-a'];$equivalent['phone']='+380 (00) 000-00-01';
check_request(status(call_request($equivalent))===200,'Telegram formats replay through durable intent reconstruction');
$changed=$contact;$changed['telegram']='customer_two';check_request(status(call_request($changed))===409,'Changing saved Telegram conflicts without another order');
$changed=$contact;unset($changed['telegram']);check_request(status(call_request($changed))===409,'Removing saved Telegram conflicts without another order');
$bad=$payload;$bad['telegram']='https://evil.test/customer';$bad['language']='ru';$response=call_request($bad);
check_request($response instanceof WP_Error&&status($response)===400&&$response->get_error_message()==='Укажи Telegram в формате @username или https://t.me/username.','Invalid Telegram returns localized Russian validation');
$options=[];$orders=[];JR_Games::$inventory=[['id'=>'game-a','platforms'=>['ps5']]];JR_Settings::$settings['pickup']=true;

$newPast=$past;$newPast['gameIds']=[];check_request(status(call_request($newPast))===400,'New requests still reject dates in the past');
check_request(status(call_request($payload))===400,'New requests still reject unpublished selected games');
JR_Settings::$settings['pickup']=false;$newPickup=$payload;$newPickup['gameIds']=[];check_request(status(call_request($newPickup))===400,'New requests still reject disabled pickup');JR_Settings::$settings['pickup']=true;
$nul=$payload;$nul['startDate']=$today."\0";$nul['gameIds']=[];$response=call_request($nul);check_request($response instanceof WP_Error&&status($response)===400,'Null-byte date returns validation400 without escaping');
$huge=$payload;$huge['gameIds']=[];$huge['address']=str_repeat('x',1048576);$response=call_request($huge);check_request($response instanceof WP_Error&&status($response)===400,'Pickup address is bounded before persistence');
$pickup=$payload;$pickup['address']='Hidden old delivery address';$normalized=JR_Domain::validate($pickup,$today,[['id'=>'game-a','platforms'=>['ps5']],['id'=>'game-b','platforms'=>['ps5']]]);check_request($normalized['address']==='','Pickup discards unnecessary address data');
$new=$payload;$new['method']='delivery';$new['address']='Audit address';$new['gameIds']=[];
$newData=JR_Domain::validate($new,$today,[]);$stockRejected=false;
try { JR_Orders::create($newData,'test-stock-key','test-stock-fingerprint'); }
catch(RuntimeException $e) { $stockRejected=$e->getMessage()==='Цей комплект тимчасово недоступний.'; }
catch(Throwable $e) {}
check_request($stockRejected,'Out-of-stock tariff rejects new request before order construction');
foreach(['checkout-draft','jr-incomplete'] as $partialStatus) {
    [$key]=fixture_receipt($payload,$today,$partialStatus);unset($options['jr_result_'.$key]);
    $response=call_request($payload);
    check_request($response instanceof WP_Error&&status($response)===503,'A partial completed flag in '.$partialStatus.' never acknowledges acceptance');
    $options['jr_lock_'.$key]=time().':active-final-save';
    $response=call_request($payload);
    check_request($response instanceof WP_Error&&status($response)===409&&$response->get_error_code()==='jr_busy','Partial '.$partialStatus.' active worker returns busy');
    unset($options['jr_lock_'.$key]);
}
[$key]=fixture_receipt($payload,$today,'processing');unset($options['jr_result_'.$key]);
check_request(status(call_request($payload))===200,'Accepted booking later moved to processing still replays');
echo json_encode(['checks'=>$checks,'failures'=>$failures,'realOrdersCreated'=>0,'realMailCalls'=>0,'wordpressLoaded'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
