<?php
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
$checks=0;$failures=[];
function api_check(bool $ok,string $label):void {
    global $checks,$failures;
    $checks++;
    if (!$ok) {$failures[]=$label;fwrite(STDERR,"FAIL: ".$label."\n");}
}
final class WP_Error {public function __construct(public string $message='transport details'){}}
function is_wp_error($value):bool {return $value instanceof WP_Error;}
function wp_json_encode($value) {return json_encode($value);}
function wp_parse_url($value) {return parse_url($value);}
function home_url($path=''):string {return 'https://example.invalid/';}
function wp_remote_retrieve_response_code($response) {return $response['response']['code']??0;}
function wp_remote_retrieve_body($response) {return $response['body']??'';}
$GLOBALS['api_calls']=[];$GLOBALS['api_response']=null;
function wp_remote_post($url,$args) {
    $GLOBALS['api_calls'][]=[$url,$args];
    $response=$GLOBALS['api_response'];
    if ($response instanceof Throwable) throw $response;
    return is_callable($response)?$response($url,$args):$response;
}
final class JRTG_Settings {
    public static array $data=['enabled'=>false,'token'=>'123456789:APIonlyFixtureToken_NotReal0123456789','chat_id'=>'999','webhook_secret'=>'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'];
    public static function get():array {return self::$data;}
}
$root=dirname(__DIR__,2);
require $root.'/wordpress/joyrent-telegram/includes/api.php';
function api_response($body,int $code=200):void {
    $GLOBALS['api_response']=['response'=>['code'=>$code],'body'=>is_string($body)?$body:json_encode($body)];
}
function api_send_fixture($result,int $code=200):array {
    api_response(['ok'=>true,'result'=>$result],$code);
    return JRTG_Api::send_message('Fixture booking');
}
function api_sent(int $chat=999,int $message=12):array {return ['message_id'=>$message,'chat'=>['id'=>$chat,'type'=>$chat<0?'supergroup':'private']];}
function api_call_count():int {return count($GLOBALS['api_calls']);}

api_check(api_send_fixture(api_sent())===['status'=>'sent','message_id'=>12],'Matching Telegram destination is acknowledged');
$call=$GLOBALS['api_calls'][array_key_last($GLOBALS['api_calls'])];
api_check($call[1]['timeout']>=6&&$call[1]['timeout']<=8,'HTTP timeout bounds shared-host worker to 6–8 seconds');
api_check($call[0]==='https://api.telegram.org/bot'.JRTG_Settings::$data['token'].'/sendMessage','Send uses fixed HTTPS Telegram endpoint');
api_check($call[1]['redirection']===0&&$call[1]['sslverify']===true&&$call[1]['blocking']===true,'Request rejects redirects and verifies TLS');
api_check($call[1]['limit_response_size']===65536,'Remote response size is bounded');
$payload=json_decode($call[1]['body'],true);
api_check($payload===['chat_id'=>'999','text'=>'Fixture booking','disable_web_page_preview'=>true],'Send payload contains only destination, plain message and preview flag');
api_check($call[1]['headers']['Content-Type']==='application/json'&&$call[1]['data_format']==='body','Send body is JSON');

// Optional presentation cannot override the pinned destination, message or API transport.
$bookingUrl='https://example.invalid/wp-admin/admin.php?page=wc-orders&action=edit&id=87';
$keyboard=['inline_keyboard'=>[[['text'=>'Открыть бронь','url'=>$bookingUrl]]]];
api_response(['ok'=>true,'result'=>api_sent()]);
api_check(JRTG_Api::send_message('<b>Бронь JR-87</b>',null,['parse_mode'=>'HTML','reply_markup'=>$keyboard])===['status'=>'sent','message_id'=>12],'HTML booking with one inline link is acknowledged');
$formatted=$GLOBALS['api_calls'][array_key_last($GLOBALS['api_calls'])];
$payload=json_decode($formatted[1]['body'],true);
api_check(($payload['parse_mode']??null)==='HTML'&&($payload['reply_markup']??null)===$keyboard,'Telegram receives bold HTML and the inline booking button');
api_check(($payload['chat_id']??null)==='999'&&($payload['text']??null)==='<b>Бронь JR-87</b>'&&($payload['disable_web_page_preview']??null)===true,'Presentation preserves the recipient, exact message and suppressed preview');
api_check($formatted[1]['timeout']===8&&$formatted[1]['redirection']===0&&$formatted[1]['sslverify']===true,'Rich messages preserve bounded fixed-endpoint transport');
api_check(JRTG_Api::send_message('Plain test',null,[])['status']==='sent','Empty presentation options preserve plain test messages');
$plain=json_decode($GLOBALS['api_calls'][array_key_last($GLOBALS['api_calls'])][1]['body'],true);
api_check($plain===['chat_id'=>'999','text'=>'Plain test','disable_web_page_preview'=>true],'Plain messages do not acquire parse mode or buttons');
api_check(JRTG_Api::send_message('Booking',null,['reply_markup'=>$keyboard])['status']==='sent','An inline link also works with a plain message');
$beforeInvalidOptions=api_call_count();
foreach ([
    'recipient overwrite'=>['chat_id'=>'111'],
    'message overwrite'=>['text'=>'Injected'],
    'token overwrite'=>['token'=>'not-a-token'],
    'preview overwrite'=>['disable_web_page_preview'=>false],
    'Markdown not supported'=>['parse_mode'=>'MarkdownV2'],
    'non-string parse mode'=>['parse_mode'=>['HTML']],
    'lowercase parse mode'=>['parse_mode'=>'html'],
    'empty parse mode'=>['parse_mode'=>''],
    'null parse mode'=>['parse_mode'=>null],
    'non-array keyboard'=>['reply_markup'=>'invalid'],
    'extra keyboard action'=>['reply_markup'=>$keyboard+['resize_keyboard'=>true]],
    'empty keyboard'=>['reply_markup'=>['inline_keyboard'=>[]]],
    'multiple rows'=>['reply_markup'=>['inline_keyboard'=>[$keyboard['inline_keyboard'][0],$keyboard['inline_keyboard'][0]]]],
    'multiple buttons'=>['reply_markup'=>['inline_keyboard'=>[[$keyboard['inline_keyboard'][0][0],$keyboard['inline_keyboard'][0][0]]]]],
    'associative row'=>['reply_markup'=>['inline_keyboard'=>[['button'=>$keyboard['inline_keyboard'][0][0]]]]],
    'callback action'=>['reply_markup'=>['inline_keyboard'=>[[['text'=>'Open','url'=>$bookingUrl,'callback_data'=>'delete']]]]],
    'empty label'=>['reply_markup'=>['inline_keyboard'=>[[['text'=>'   ','url'=>$bookingUrl]]]]],
    'multiline label'=>['reply_markup'=>['inline_keyboard'=>[[['text'=>"Open\nbooking",'url'=>$bookingUrl]]]]],
    'long label'=>['reply_markup'=>['inline_keyboard'=>[[['text'=>str_repeat('я',65),'url'=>$bookingUrl]]]]],
    'missing URL'=>['reply_markup'=>['inline_keyboard'=>[[['text'=>'Open']]]]],
    'non-string URL'=>['reply_markup'=>['inline_keyboard'=>[[['text'=>'Open','url'=>true]]]]],
] as $label=>$options) {
    api_check(JRTG_Api::send_message('Booking',null,$options)===['status'=>'failed','error'=>'invalid_message'],'Rejected message presentation: '.$label);
}
foreach (['javascript:alert(1)','tel:+380991234567','https://user:password@example.invalid/booking',$bookingUrl.'#fragment','https://example.invalid/booking path','https://example.invalid/booking\n','/relative/booking',str_repeat('x',2049),'https:///missing-host'] as $url) {
    $badKeyboard=['inline_keyboard'=>[[['text'=>'Open booking','url'=>$url]]]];
    api_check(JRTG_Api::send_message('Booking',null,['reply_markup'=>$badKeyboard])===['status'=>'failed','error'=>'invalid_message'],'Unsafe inline destination rejected before HTTP');
}
api_check(api_call_count()===$beforeInvalidOptions,'Invalid presentation makes zero Telegram API calls');
$httpKeyboard=['inline_keyboard'=>[[['text'=>'Open booking','url'=>'http://example.invalid/wp-admin/post.php?post=87&action=edit']]]];
api_check(JRTG_Api::send_message('Booking',null,['reply_markup'=>$httpKeyboard])['status']==='sent','A valid HTTP admin URL works on local development installations');

foreach ([
    'another chat'=>api_sent(111),
    'opposite signed chat'=>api_sent(-999),
    'missing chat'=>['message_id'=>12],
    'missing chat id'=>['message_id'=>12,'chat'=>['type'=>'private']],
    'non-object chat'=>['message_id'=>12,'chat'=>'999'],
    'string chat id'=>['message_id'=>12,'chat'=>['id'=>'999']],
    'boolean chat id'=>['message_id'=>12,'chat'=>['id'=>true]],
    'missing message id'=>['chat'=>['id'=>999]],
    'string message id'=>['message_id'=>'12','chat'=>['id'=>999]],
    'zero message id'=>api_sent(999,0),
    'negative message id'=>api_sent(999,-1),
] as $label=>$result) {
    api_check(api_send_fixture($result)===['status'=>'unknown','error'=>'invalid_ack'],'Uncertain acknowledgment: '.$label);
}
api_response('{"ok":true,"result":{"message_id":12,"chat":{"id":999.0}}}');
api_check(JRTG_Api::send_message('Fixture booking')===['status'=>'unknown','error'=>'invalid_ack'],'Floating point chat ID cannot confirm destination');
$negative=JRTG_Settings::$data;$negative['chat_id']='-1001234567890';
api_response(['ok'=>true,'result'=>api_sent(-1001234567890)]);
api_check(JRTG_Api::send_message('Fixture booking',$negative)===['status'=>'sent','message_id'=>12],'Large signed destination matches without integer truncation');
foreach ([201,204,299] as $code) api_check(api_send_fixture(api_sent(),$code)['status']==='sent','Valid 2xx acknowledgment accepted: '.$code);
foreach ([0,199,300,302,500] as $code) api_check(api_send_fixture(api_sent(),$code)===['status'=>'unknown','error'=>'invalid_ack'],'Unexpected HTTP code cannot acknowledge delivery: '.$code);

$private='private token/contact details';
foreach ([new WP_Error($private),new RuntimeException($private)] as $transport) {
    $GLOBALS['api_response']=$transport;$before=api_call_count();ob_start();
    $result=JRTG_Api::send_message('Fixture booking');$output=ob_get_clean();
    api_check($result===['status'=>'unknown','error'=>'http_unknown'],'Transport uncertainty never becomes retry or failure');
    api_check(api_call_count()===$before+1&&$output===''&&!str_contains(json_encode($result),$private),'Transport details are not printed, returned or retried immediately');
}
foreach (['<html>gateway</html>','', '{"ok":"true","result":{}}','{"ok":true}','{"ok":false}', str_repeat('x',65536)] as $body) {
    api_response($body,502);
    api_check(JRTG_Api::send_message('Fixture booking')['status']==='unknown','Malformed or truncated response stays uncertain');
}
foreach ([[0,1],[1,1],[17,17],[99999,3600],['17',60],[null,60]] as [$delay,$expected]) {
    api_response(['ok'=>false,'error_code'=>429,'description'=>$private,'parameters'=>['retry_after'=>$delay]],429);$before=api_call_count();
    api_check(JRTG_Api::send_message('Fixture booking')===['status'=>'retry','error'=>'rate_limited','retry_after'=>$expected],'Explicit rate-limit delay is clamped or defaults safely');
    api_check(api_call_count()===$before+1,'Rate limit schedules later work without looping inside HTTP call');
}
foreach ([500,502,503,599] as $code) {
    api_response(['ok'=>false,'error_code'=>$code,'description'=>$private],$code);
    api_check(JRTG_Api::send_message('Fixture booking')===['status'=>'retry','error'=>'telegram_server_error','retry_after'=>60],'Explicit Telegram rejection permits bounded later retry: '.$code);
}
foreach ([400=>'bad_request',401=>'bot_unauthorized',403=>'chat_forbidden',409=>'bot_webhook_conflict',418=>'telegram_error'] as $code=>$error) {
    api_response(['ok'=>false,'error_code'=>$code,'description'=>$private],$code);
    api_check(JRTG_Api::send_message('Fixture booking')===['status'=>'failed','error'=>$error],'Known rejection returns only safe error code: '.$code);
}

$before=api_call_count();
foreach (['https://attacker.invalid/secret','123456789:bad/path'] as $token) {
    $bad=JRTG_Settings::$data;$bad['token']=$token;
    api_check(JRTG_Api::send_message('Fixture booking',$bad)===['status'=>'failed','error'=>'not_configured'],'Malformed token cannot change endpoint');
}
foreach (['0','@username','999/redirect'] as $chat) {
    $bad=JRTG_Settings::$data;$bad['chat_id']=$chat;
    api_check(JRTG_Api::send_message('Fixture booking',$bad)===['status'=>'failed','error'=>'not_configured'],'Malformed destination rejected before HTTP');
}
api_check(JRTG_Api::send_message('')===['status'=>'failed','error'=>'invalid_message'],'Empty message rejected before HTTP');
api_check(api_call_count()===$before,'Invalid configuration makes zero HTTP calls');

$url='https://example.invalid/wp-json/joyrent-telegram/v1/update';
foreach (['http://example.invalid/receiver','https://attacker.invalid/receiver','https://user:pass@example.invalid/receiver',$url.'#secret'] as $badUrl) {
    api_check(JRTG_Api::connect_webhook(JRTG_Settings::$data,$badUrl)===['status'=>'failed','error'=>'webhook_url'],'Webhook destination validation: '.$badUrl);
}
$bad=JRTG_Settings::$data;$bad['webhook_secret']='bad-secret';
api_check(JRTG_Api::connect_webhook($bad,$url)===['status'=>'failed','error'=>'webhook_url'],'Webhook requires random-length authentication secret');
api_check(api_call_count()===$before,'Rejected webhook configurations make zero HTTP calls');
$GLOBALS['api_response']=static function($requestUrl,$args)use($url) {
    return ['response'=>['code'=>200],'body'=>json_encode(['ok'=>true,'result'=>str_ends_with($requestUrl,'/setWebhook')?true:['url'=>$url]])];
};
api_check(JRTG_Api::connect_webhook(JRTG_Settings::$data,$url)===['status'=>'ok'],'Webhook registration verifies remote acknowledgment');
api_check(api_call_count()===$before+2,'Webhook connection makes only registration and verification calls');
[$registerUrl,$registerArgs]=$GLOBALS['api_calls'][$before];
[$infoUrl,$infoArgs]=$GLOBALS['api_calls'][$before+1];
api_check($registerUrl==='https://api.telegram.org/bot'.JRTG_Settings::$data['token'].'/setWebhook'&&str_ends_with($infoUrl,'/getWebhookInfo'),'Webhook methods use same fixed Telegram API');
api_check(json_decode($registerArgs['body'],true)===['url'=>$url,'secret_token'=>JRTG_Settings::$data['webhook_secret'],'allowed_updates'=>['message'],'max_connections'=>1,'drop_pending_updates'=>false],'Webhook registration requests minimal updates and one connection');
api_check(json_decode($infoArgs['body'],true)===[],'Webhook verification payload contains no extra data');
api_check($registerArgs['timeout']<=8&&$infoArgs['timeout']<=8,'Webhook HTTP requests also remain bounded');
api_response(['ok'=>true,'result'=>false]);$before=api_call_count();
api_check(JRTG_Api::connect_webhook(JRTG_Settings::$data,$url)===['status'=>'failed','error'=>'invalid_ack']&&api_call_count()===$before+1,'Rejected registration does not query webhook info');
$GLOBALS['api_response']=static function($requestUrl,$args) {
    return ['response'=>['code'=>200],'body'=>json_encode(['ok'=>true,'result'=>str_ends_with($requestUrl,'/setWebhook')?true:['url'=>'https://example.invalid/other']])];
};
api_check(JRTG_Api::connect_webhook(JRTG_Settings::$data,$url)===['status'=>'failed','error'=>'webhook_mismatch'],'Webhook activation requires exact receiver acknowledgment');
if ($failures!==[]) {fwrite(STDERR,count($failures)." failure(s) from ".$checks." isolated Telegram API checks\n");exit(1);}
echo "PASS: ".$checks." isolated Telegram API checks\n";
