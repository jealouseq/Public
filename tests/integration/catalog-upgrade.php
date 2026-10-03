<?php
// Run only in the disposable local WordPress fixture, never against the live host.
require '/var/www/html/wp-load.php';
$checks=0;
function verify_catalog(bool $value,string $label): void { global $checks; $checks++; if (!$value) throw new RuntimeException($label); }
function game_post(string $key): WP_Post {
    $posts=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>1,'meta_key'=>'_jr_game_id','meta_value'=>$key]);
    if (!$posts) throw new RuntimeException('Missing fixture game '.$key);
    return $posts[0];
}
function commerce_state(): array {
    $prices=[];
    foreach (JR_Domain::catalog()['tariffs'] as $console=>$tariffs) foreach ($tariffs as $tariff) {
        $product=JR_Store::product($console,$tariff['days']); $prices[$product->get_id()]=[$product->get_regular_price(),$product->get_sale_price(),$product->get_status()];
    }
    $orders=wc_get_orders(['limit'=>-1,'return'=>'ids']);sort($orders);
    return [$prices,get_option('joyrent_settings'),$orders];
}
$statuses=[];$originals=[];$attachment=0;$created=0;$version=get_option('joyrent_version');$commerce=commerce_state();
foreach (['it-takes-two','fc27','astro-bot','split-fiction','mk11'] as $key) {
    $post=game_post($key);$originals[$key]=['post'=>$post,'meta'=>get_post_meta($post->ID,'_jr_game',true),'key'=>get_post_meta($post->ID,'_jr_game_id',true),'thumbnail'=>get_post_meta($post->ID,'_thumbnail_id',true)];
}
try {
    $owner=$originals['it-takes-two']['post']->ID;
    wp_update_post(['ID'=>$owner,'post_title'=>'Owner-authored title','post_content'=>'Owner-authored description']);
    $meta=$originals['it-takes-two']['meta'];$meta['genreRu']='Авторский жанр';$meta['descriptionRu']='Авторское описание';$meta['available']=true;update_post_meta($owner,'_jr_game',$meta);
    $attachment=wp_insert_attachment(['post_title'=>'Local migration fixture','post_status'=>'inherit','post_mime_type'=>'image/webp','guid'=>'http://localhost:8080/wp-content/uploads/owner-cover.webp']);
    update_post_meta($attachment,'_wp_attached_file','owner-cover.webp');set_post_thumbnail($owner,$attachment);
    wp_update_post(['ID'=>$originals['fc27']['post']->ID,'post_status'=>'draft']);
    wp_update_post(['ID'=>$originals['astro-bot']['post']->ID,'post_status'=>'trash']);
    $split=$originals['split-fiction']['post']->ID;
    wp_update_post(['ID'=>$split,'post_status'=>'draft']);update_post_meta($split,'_jr_game_id','migration-hidden-split');
    $mk=$originals['mk11']['meta'];$mk['platforms']=['ps5','ps4'];update_post_meta($originals['mk11']['post']->ID,'_jr_game',$mk);
    foreach (['fc25','ufc5','cod-bo6'] as $key) {
        $legacy=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>1,'meta_key'=>'_jr_game_id','meta_value'=>$key]);
        if ($legacy) wp_update_post(['ID'=>$legacy[0]->ID,'post_status'=>'publish']);
        else { $id=wp_insert_post(['post_type'=>'joyrent_game','post_title'=>'Obsolete local fixture '.$key,'post_status'=>'publish']);update_post_meta($id,'_jr_game_id',$key); }
    }
    update_option('joyrent_version','1.3.0');JR_Store::upgrade();
    $created=game_post('split-fiction')->ID;
    verify_catalog($created!==$split,'Missing new game imported');
    verify_catalog(get_post_status($originals['fc27']['post']->ID)==='draft','Owner draft preserved');
    verify_catalog(get_post_status($originals['astro-bot']['post']->ID)==='trash','Owner trash preserved');
    verify_catalog(game_post('astro-bot')->ID===$originals['astro-bot']['post']->ID,'No duplicate trashed game');
    foreach (['fc25','ufc5','cod-bo6'] as $key) {
        $posts=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>1,'meta_key'=>'_jr_game_id','meta_value'=>$key]);
        verify_catalog(!$posts,'Obsolete test-store edition removed '.$key);
    }
    $record=array_column(JR_Games::records(),null,'id')['it-takes-two'];
    verify_catalog($record['title']==='Owner-authored title'&&$record['description']==='Owner-authored description'&&$record['genreRu']==='Авторский жанр'&&$record['available']===true,'Owner content and verified availability preserved');
    verify_catalog(str_contains($record['imageUrl']??'','owner-cover.webp')&&get_post_thumbnail_id($owner)===$attachment,'Owner thumbnail preserved');
    verify_catalog(get_post_meta($originals['mk11']['post']->ID,'_jr_game',true)['platforms']===['ps4'],'PS4-compatible Mortal Kombat retained');
    verify_catalog(commerce_state()===$commerce,'Prices, settings and order IDs unchanged');
    $before=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>100,'fields'=>'ids']);
    wp_update_post(['ID'=>$owner,'menu_order'=>3]);JR_Store::upgrade();
    $after=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>100,'fields'=>'ids']);
    verify_catalog($before===$after&&get_post($owner)->menu_order===3,'Repeated upgrade does not duplicate games or reset owner priority');
    $statuses=[];
    foreach (get_posts(['post_type'=>'joyrent_game','post_status'=>'publish','numberposts'=>100]) as $post) { $statuses[$post->ID]=$post->post_status;wp_update_post(['ID'=>$post->ID,'post_status'=>'draft']); }
    verify_catalog(JR_Games::records()===[],'Intentionally empty catalogue stays empty');
    foreach ($statuses as $id=>$status) wp_update_post(['ID'=>$id,'post_status'=>$status]);
} finally {
    foreach ($statuses as $id=>$status) wp_update_post(['ID'=>$id,'post_status'=>$status]);
    if ($created) wp_delete_post($created,true);
    foreach ($originals as $key=>$value) {
        $post=$value['post'];wp_update_post(['ID'=>$post->ID,'post_title'=>$post->post_title,'post_content'=>$post->post_content,'post_status'=>$post->post_status,'menu_order'=>$post->menu_order]);
        update_post_meta($post->ID,'_jr_game',$value['meta']);update_post_meta($post->ID,'_jr_game_id',$value['key']);
        if ($value['thumbnail']) update_post_meta($post->ID,'_thumbnail_id',$value['thumbnail']);else delete_post_meta($post->ID,'_thumbnail_id');
    }
    if ($attachment) wp_delete_attachment($attachment,true);
    update_option('joyrent_version',$version);
}
verify_catalog(count(JR_Games::records())===20,'Clean local fixture restored');
echo "PASS: $checks catalogue migration checks, fixture restored\n";
