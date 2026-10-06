<?php
// Disposable local WordPress only. No requests/mail; original options/globals restored.
require '/var/www/html/wp-load.php';
$checks=0;$failures=[];$created=[];$policy=get_option('wp_page_for_privacy_policy');$settings=get_option('joyrent_settings');$version=get_option('joyrent_version');$current_post=$GLOBALS['post']??null;
function review_check(bool $value,string $label):void{global $checks,$failures;$checks++;if(!$value)$failures[]=$label;}
try {
    $global_page=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Local current-page policy regression']);$created[]=$global_page;$GLOBALS['post']=get_post($global_page);
    update_option('wp_page_for_privacy_policy',0);review_check(str_contains(joyrent_privacy_url(),'konfidentsiinist'),'Zero assigned privacy ID ignores the current published page');
    delete_option('wp_page_for_privacy_policy');review_check(str_contains(joyrent_privacy_url(),'konfidentsiinist'),'Missing privacy option ignores the current published page');
    update_option('wp_page_for_privacy_policy',$global_page);review_check(joyrent_privacy_url()===get_permalink($global_page),'Positive published owner privacy ID remains authoritative');
    $legacy=array_column(json_decode((string)file_get_contents('/var/www/html/wp-content/plugins/joyrent-rentals/data/legacy-games.json'),true),null,'id');$source=$legacy['fc25'];$fixtures=[];
    foreach(['pristine'=>[],'excerpt'=>['post_excerpt'=>'Owner excerpt'],'priority'=>['menu_order'=>3],'draft'=>['post_status'=>'draft'],'trash'=>['post_status'=>'trash'],'parent'=>['post_parent'=>$global_page],'metadata'=>[]] as $kind=>$changes) {
        // Test each owner edit against the same canonical slug, with no competing legacy record.
        $id=wp_insert_post(array_merge(['post_type'=>'joyrent_game','post_status'=>'publish','post_title'=>$source['title'],'post_content'=>$source['description'],'post_name'=>'fc25','menu_order'=>10],$changes));$created[]=$id;update_post_meta($id,'_jr_game_id','fc25');update_post_meta($id,'_jr_game',$source);if($kind==='metadata')update_post_meta($id,'owner_condition','Preserve owner field');
        update_option('joyrent_version','1.3.0');JR_Store::upgrade();$post=get_post($id);
        review_check($kind==='pristine'?$post===null:$post!==null,$kind==='pristine'?'Proven pristine legacy fixture may be retired':'Owner legacy '.$kind.' edit preserved');
        if($kind==='excerpt')review_check($post&&$post->post_excerpt==='Owner excerpt','Owner legacy excerpt content retained');
        if($kind==='priority')review_check($post&&(int)$post->menu_order===3,'Owner legacy priority retained');
        if($kind==='metadata')review_check(get_post_meta($id,'owner_condition',true)==='Preserve owner field','Unknown owner post metadata retained');
        if($post)wp_delete_post($id,true);
    }
}finally{
    foreach($created as $id)if(get_post($id))wp_delete_post($id,true);update_option('wp_page_for_privacy_policy',$policy);update_option('joyrent_version',$version);update_option('joyrent_settings',$settings);$GLOBALS['post']=$current_post;
}
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
