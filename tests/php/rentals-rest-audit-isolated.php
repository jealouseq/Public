<?php
// Actual plugin registration, REST, domain, mutex and order lookup; isolated in-memory boundaries.
// No WordPress, real DB/orders, network, mail, Telegram or live-site traffic.
define('ABSPATH','/isolated/');
define('MINUTE_IN_SECONDS',60);
final class WooCommerce {}
final class WP_REST_Request {
    public function __construct(private mixed $payload) {}
    public function get_json_params(): mixed { return $this->payload; }
}
final class WP_REST_Response {
    public function __construct(private array $data, private int $status) {}
    public function get_data(): array { return $this->data; }
    public function get_status(): int { return $this->status; }
}
final class WP_Error {
    public function __construct(private string $code, private string $message, private array $data) {}
    public function get_error_data(): array { return $this->data; }
    public function get_error_code(): string { return $this->code; }
}
final class WC_Order {
    public function __construct(private array $meta,private string $status='jr-request') {}
    public function get_meta(string $key): mixed { return $this->meta[$key]??''; }
    public function get_status(): string { return $this->status; }
    public function get_order_number(): int { return 42; }
    public function get_billing_first_name(): string { return 'Isolated fixture'; }
    public function get_billing_phone(): string { return '+380500000077'; }
    public function get_billing_address_1(): string { return ''; }
}
$options=[];$transients=[];$orders=[];$queries=[];$filters=[];$checks=0;$failures=[];
function add_action(...$args): void {}
function add_filter(string $tag,callable $callback,int $priority=10,int $args=1): void { $GLOBALS['filters'][$tag][]=$callback; }
function register_activation_hook(...$args): void {}
function register_deactivation_hook(...$args): void {}
function get_option(string $key,mixed $default=false): mixed { return $GLOBALS['options'][$key]??$default; }
function add_option(string $key,mixed $value,mixed $deprecated='',mixed $autoload='yes'): bool {
    if(array_key_exists($key,$GLOBALS['options']))return false;
    $GLOBALS['options'][$key]=$value;return true;
}
function get_transient(string $key): mixed {
    $value=$GLOBALS['transients'][$key]??null;
    return $value&&$value['expires']>time()?$value['value']:false;
}
function set_transient(string $key,mixed $value,int $ttl): void { $GLOBALS['transients'][$key]=['value'=>$value,'expires'=>time()+$ttl]; }
function wp_salt(string $scheme): string { return 'isolated-'.$scheme; }
function wp_generate_uuid4(): string { return 'isolated-owner-'.(++$GLOBALS['ownerSequence']); }
function wp_cache_delete(...$args): void {}
function wp_json_encode(mixed $value): string { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
function get_woocommerce_currency(): string { return 'UAH'; }
function get_posts(array $args): array { return []; }
function get_post_stati(): array { return ['publish'=>'publish','draft'=>'draft']; }
function wc_get_orders(array $query): array {
    $GLOBALS['queries'][]=$query;
    return array_values(array_filter($GLOBALS['orders'],fn($o)=>$o->get_meta('_joyrent_request_key')===($query['joyrent_request_key']??'')));
}
function wp_mail(...$args): never { throw new LogicException('Real mail forbidden'); }
$GLOBALS['ownerSequence']=0;
$wpdb=new class {
    public string $options='isolated_options';
    public function prepare(string $sql,mixed ...$params): array { return [$sql,$params]; }
    public function query(array $prepared): int {
        [$sql,$params]=$prepared;$key=$params[0];
        if(str_starts_with($sql,'INSERT IGNORE')) {
            if(array_key_exists($key,$GLOBALS['options']))return 0;
            $GLOBALS['options'][$key]=$params[1];return 1;
        }
        if(str_starts_with($sql,'DELETE')) {
            if(($GLOBALS['options'][$key]??null)!==$params[1])return 0;
            unset($GLOBALS['options'][$key]);return 1;
        }
        throw new LogicException('Unexpected DB operation');
    }
};
require dirname(__DIR__,2).'/wordpress/joyrent-rentals/joyrent-rentals.php';
function audit_check(bool $value,string $label): void { $GLOBALS['checks']++;if(!$value)$GLOBALS['failures'][]=$label; }
function audit_status(mixed $response): int { return $response instanceof WP_Error?(int)$response->get_error_data()['status']:$response->get_status(); }
function audit_call(mixed $payload): mixed {
    try { return JR_REST::create(new WP_REST_Request($payload)); }
    catch(Throwable $e) { audit_check(false,'REST unexpectedly escaped '.get_class($e));return new WP_Error('unexpected','unexpected',['status'=>500]); }
}
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
$payload=['requestId'=>'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','console'=>'ps5','days'=>3,'startDate'=>$today,'controllers'=>2,'gameIds'=>[],'name'=>'Isolated fixture','phone'=>'+380500000077','method'=>'pickup','address'=>'','consent'=>true];
$options['joyrent_settings']=array_merge(JR_Settings::defaults(),['pickup'=>true]);
$_SERVER['REMOTE_ADDR']='192.0.2.77';
$rate='jr_rate_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR'],wp_salt('auth'));
set_transient($rate,8,15*MINUTE_IN_SECONDS);
foreach([null,false,42,'bad',new stdClass(),[]] as $value) audit_check(audit_status(audit_call($value))===400,'Malformed top-level input returns400: '.get_debug_type($value));
foreach(['','--------------------------------',$payload['requestId']."\n",$payload['requestId']."\r",str_repeat('a',33),'aaaaaaaabbbb-cccc-dddd-eeeeeeeeeeee','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeee!',['uuid']] as $id) {
    $bad=$payload;$bad['requestId']=$id;
    $response=audit_call($bad);
    audit_check(audit_status($response)===400&&$response instanceof WP_Error&&$response->get_error_code()==='jr_request_id','Malformed request ID rejected before reservation: '.json_encode($id));
}
foreach([$payload['requestId'],strtoupper($payload['requestId']),str_repeat('a',32)] as $id) {
    $valid=$payload;$valid['requestId']=$id;
    audit_check(audit_status(audit_call($valid))===429,'Browser UUID/32hex syntax remains accepted: '.$id);
}
foreach(['console'=>['ps5'],'days'=>['3'],'startDate'=>['today'],'controllers'=>['2'],'gameIds'=>['id'=>[]],'consent'=>'true','language'=>['uk'],'securityMode'=>['contract'],'telegram'=>['@fixture']] as $field=>$value) {
    $bad=$payload;$bad[$field]=$value;
    audit_check(audit_status(audit_call($bad))===400,'Malformed field returns400: '.$field);
}
$bad=$payload;$bad['website']='filled';
audit_check(audit_status(audit_call($bad))===400,'Filled honeypot rejected before order/rate work');

$reserve=new ReflectionMethod(JR_REST::class,'reserve');
unset($transients[$rate]);
for($i=1;$i<=8;$i++)audit_check($reserve->invoke(null,$rate)===true,'Rate reservation '.$i.' succeeds');
audit_check($reserve->invoke(null,$rate)===false&&get_transient($rate)===8,'Ninth reservation blocked without increasing counter');
audit_check(audit_status(audit_call($payload))===429,'Validated new request returns429 at rate saturation');
audit_check(($transients[$rate]['expires']-time())<=15*MINUTE_IN_SECONDS,'Rate window bounded to fifteen minutes');
$transients[$rate]['expires']=time()-1;
audit_check($reserve->invoke(null,$rate)===true&&get_transient($rate)===1,'Expired rate window recovers');
$options[$rate.'_lock']=time().':other-owner';
audit_check($reserve->invoke(null,$rate)===false&&get_transient($rate)===1,'Concurrent rate-lock owner rejects without increment');
$options[$rate.'_lock']=(time()-11).':expired-owner';
audit_check($reserve->invoke(null,$rate)===true&&get_transient($rate)===2,'Expired short rate mutex recovers');
unset($options[$rate.'_lock']);

$key=hash_hmac('sha256',$payload['requestId'],wp_salt('nonce'));
$options['jr_lock_'.$key]=time().':another-worker';
audit_check(audit_status(audit_call($payload))===409&&get_transient($rate)===2,'In-flight booking returns409 and consumes no rate slot');
$release=new ReflectionMethod(JR_REST::class,'release');
$release->invoke(null,'jr_lock_'.$key,'wrong-owner');
audit_check(isset($options['jr_lock_'.$key]),'Another worker cannot release booking mutex');
unset($options['jr_lock_'.$key]);
$data=JR_Domain::canonical($payload);
$fingerprint=hash('sha256',wp_json_encode($data));
$receipt=['reference'=>'JR-42','rentalAmount'=>1400.0,'status'=>'awaiting_confirmation'];
$options['jr_result_'.$key]=['fingerprint'=>$fingerprint,'receipt'=>$receipt];
set_transient($rate,8,15*MINUTE_IN_SECONDS);
$replay=audit_call($payload);
audit_check(audit_status($replay)===200&&$replay->get_data()===$receipt,'Completed cached replay succeeds after rate saturation');
audit_check(get_transient($rate)===8,'Completed replay consumes no rate slot');
audit_check(array_keys($replay->get_data())===['reference','rentalAmount','status'],'Receipt contains no private customer/contact fields');
$changed=$payload;$changed['name']='Changed customer';
audit_check(audit_status(audit_call($changed))===409,'Changed intent under accepted request ID remains conflict');
$uppercase=$payload;$uppercase['requestId']=strtoupper($payload['requestId']);
$uppercaseKey=hash_hmac('sha256',$uppercase['requestId'],wp_salt('nonce'));
$options['jr_result_'.$uppercaseKey]=['fingerprint'=>$fingerprint,'receipt'=>$receipt];
audit_check(audit_status(audit_call($uppercase))===200,'Legacy uppercase valid request IDs preserve original derived key');
unset($options['jr_result_'.$key]);
$orders=[new WC_Order(['_joyrent_request_key'=>$key,'_joyrent_fingerprint'=>$fingerprint,'_joyrent_completed'=>'yes','_joyrent_rental_amount'=>1400.0])];
audit_check(audit_status(audit_call($payload))===200,'Durable Woo order recovers without cached receipt');
$queries=[];
$reordered=$payload;$reordered['phone']='+380 (50) 000-00-77';
audit_check(audit_status(audit_call($reordered))===200,'Durable intent replay remains formatting-compatible');

// A first worker stores a durable key before the order is complete. Other workers
// must reach the existing request mutex rather than mistake that progress for a failure.
$orders=[new WC_Order(['_joyrent_request_key'=>$key,'_joyrent_fingerprint'=>$fingerprint,'_joyrent_completed'=>'no','_joyrent_rental_amount'=>1400.0])];
set_transient($rate,2,15*MINUTE_IN_SECONDS);
$options['jr_lock_'.$key]=time().':active-booking-worker';
$busy=audit_call($payload);
audit_check(audit_status($busy)===409&&$busy instanceof WP_Error&&$busy->get_error_code()==='jr_busy','Incomplete order with active worker returns normal busy409');
audit_check(get_transient($rate)===2,'Active incomplete booking consumes no second rate slot');
unset($options['jr_lock_'.$key]);$queries=[];
$unfinished=audit_call($payload);
audit_check(audit_status($unfinished)===503&&$unfinished instanceof WP_Error&&$unfinished->get_error_code()==='jr_create','Stale incomplete order remains503 for manual review');
audit_check(count($queries)===2,'Stale incomplete order rechecked under request mutex before stopping');
audit_check(get_transient($rate)===2&&!isset($options['jr_lock_'.$key]),'Incomplete order retry neither reserves another slot nor leaves request lock');

// A completion flag can persist before the final status update succeeds. Drafts
// must not acknowledge an accepted booking, and jr-incomplete survives WC cleanup.
$completeMeta=['_joyrent_request_key'=>$key,'_joyrent_fingerprint'=>$fingerprint,'_joyrent_completed'=>'yes','_joyrent_rental_amount'=>1400.0];
foreach(['','draft','auto-draft','checkout-draft','jr-incomplete'] as $draftStatus) {
    $orders=[new WC_Order($completeMeta,$draftStatus)];
    unset($options['jr_lock_'.$key]);
    $partial=audit_call($payload);
    audit_check(audit_status($partial)===503&&$partial instanceof WP_Error&&$partial->get_error_code()==='jr_create','Completed flag in unaccepted status cannot replay receipt: '.$draftStatus);
    $options['jr_lock_'.$key]=time().':active-final-save';
    $partial=audit_call($payload);
    audit_check(audit_status($partial)===409&&$partial instanceof WP_Error&&$partial->get_error_code()==='jr_busy','Active partial final save follows request mutex: '.$draftStatus);
    unset($options['jr_lock_'.$key]);
}
$orders=[new WC_Order($completeMeta,'processing')];
$processing=audit_call($payload);
audit_check(audit_status($processing)===200&&$processing->get_data()===$receipt,'Accepted booking moved to processing remains replayable');
audit_check(get_transient($rate)===2,'Partial final-save probes consume no additional rate slots');

$filter=$filters['woocommerce_order_data_store_cpt_get_orders_query'][0];
$clause=['key'=>'_joyrent_request_key','value'=>$key];
$once=$filter(['meta_query'=>[$clause]],['joyrent_request_key'=>$key]);
audit_check(count($once['meta_query'])===1,'CPT query does not duplicate an existing durable-key condition');
$mapped=$filter([],['joyrent_request_key'=>$key]);
audit_check(($mapped['meta_query']??[])===[$clause],'CPT custom-key-only query still maps to private meta');
$other=['key'=>'owner_field','value'=>'keep'];
$preserved=$filter(['meta_query'=>[$other]],['joyrent_request_key'=>$key]);
audit_check(($preserved['meta_query']??[])===[$other,$clause],'CPT mapping preserves unrelated owner query clauses');
$different=['key'=>'_joyrent_request_key','value'=>'different-key'];
$preserved=$filter(['meta_query'=>[$different]],['joyrent_request_key'=>$key]);
audit_check(($preserved['meta_query']??[])===[$different,$clause],'Different key condition never weakens requested durable match');
audit_check($filter(['meta_query'=>[$other]],[])===['meta_query'=>[$other]],'Unrelated Woo query remains unchanged');

echo json_encode(['checks'=>$checks,'failures'=>$failures,'wordpressLoaded'=>false,'realOrdersCreated'=>0,'realMailCalls'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
