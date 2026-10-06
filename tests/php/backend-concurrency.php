<?php
// Local fixture worker; no external mail, even when invoking the real REST handler.
require '/var/www/html/wp-load.php';
add_filter('pre_wp_mail',function(){file_put_contents('/tmp/joyrent-backend-mail.log',"attempt\n",FILE_APPEND|LOCK_EX);return true;});
$mode=$argv[1]??'';$state_path='/tmp/joyrent-backend-state.json';
if ($mode==='setup') {
    $post=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>1,'meta_key'=>'_jr_game_id','meta_value'=>'astro-bot'])[0];
    $state=['version'=>get_option('joyrent_version'),'settings'=>get_option('joyrent_settings'),'post'=>get_object_vars($post),'meta'=>get_post_meta($post->ID,'_jr_game',true),'requestId'=>wp_generate_uuid4(),'gameIDs'=>get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'fields'=>'ids'])];
    $state['requestKey']=hash_hmac('sha256',$state['requestId'],wp_salt('nonce'));
    file_put_contents($state_path,wp_json_encode($state));chmod($state_path,0600);file_put_contents('/tmp/joyrent-backend-mail.log','');
    wp_update_post(['ID'=>$post->ID,'post_status'=>'draft']);update_post_meta($post->ID,'_jr_game_id','fixture-hidden-astro');update_option('joyrent_version','1.5.0');echo "setup\n";exit;
}
if ($mode==='migrate') { JR_Store::upgrade();$games=JR_Games::records();echo wp_json_encode(['version'=>get_option('joyrent_version'),'count'=>count($games),'unique'=>count(array_unique(array_column($games,'id')))]);exit; }
$state=json_decode(file_get_contents($state_path),true);
if ($mode==='request') {
    $_SERVER['REMOTE_ADDR']='127.0.0.231';$date=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->modify('+2 days')->format('Y-m-d');
    $payload=['requestId'=>$state['requestId'],'console'=>'ps5','days'=>7,'startDate'=>$date,'controllers'=>1,'gameIds'=>[],'name'=>'Локальна конкуренція','phone'=>'+380000000001','method'=>'delivery','address'=>'Тестове місто, тестова адреса 1','consent'=>true,'language'=>'uk'];
    $request=new WP_REST_Request('POST','/joyrent/v1/requests');$request->set_header('content-type','application/json');$request->set_body(wp_json_encode($payload));$response=JR_REST::create($request);
    echo wp_json_encode(is_wp_error($response)?['status'=>$response->get_error_data()['status'],'code'=>$response->get_error_code()]:['status'=>$response->get_status(),'reference'=>$response->get_data()['reference']]);exit;
}
if ($mode==='inspect') {
    $games=JR_Games::records();$canonical=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'meta_key'=>'_jr_game_id','meta_value'=>'astro-bot','fields'=>'ids']);
    $orders=wc_get_orders(['limit'=>-1,'joyrent_request_key'=>$state['requestKey'],'meta_query'=>[['key'=>'_joyrent_request_key','value'=>$state['requestKey']]]]);
    echo wp_json_encode(['canonicalGames'=>count($canonical),'catalogCount'=>count($games),'unique'=>count(array_unique(array_column($games,'id'))),'orders'=>count($orders),'notificationStatuses'=>array_map(fn($o)=>$o->get_meta('_joyrent_notification_status'),$orders),'mailAttempts'=>count(file('/tmp/joyrent-backend-mail.log'))]);exit;
}
if ($mode==='cleanup') {
    foreach(wc_get_orders(['limit'=>-1,'joyrent_request_key'=>$state['requestKey'],'meta_query'=>[['key'=>'_joyrent_request_key','value'=>$state['requestKey']]]]) as $order)$order->delete(true);
    delete_option('jr_result_'.$state['requestKey']);$rate='jr_rate_'.hash_hmac('sha256','127.0.0.231',wp_salt('auth'));delete_transient($rate);delete_option($rate.'_lock');
    foreach(get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'fields'=>'ids']) as $id)if(!in_array($id,$state['gameIDs']))wp_delete_post($id,true);
    $post=$state['post'];wp_update_post(['ID'=>$post['ID'],'post_status'=>$post['post_status'],'menu_order'=>$post['menu_order']]);update_post_meta($post['ID'],'_jr_game_id','astro-bot');update_post_meta($post['ID'],'_jr_game',$state['meta']);update_option('joyrent_version',$state['version']);update_option('joyrent_settings',$state['settings']);unlink($state_path);unlink('/tmp/joyrent-backend-mail.log');echo "restored\n";
}
