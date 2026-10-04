<?php
// Disposable local WordPress only. Managed pages/options are restored; no mail reaches a provider.
require '/var/www/html/wp-load.php';
$settings=get_option('joyrent_settings');$version=get_option('joyrent_version');$checks=0;$failures=[];$original=[];$mail=0;
add_filter('pre_wp_mail',function()use(&$mail){$mail++;return true;});
function options_migration_check(bool $value,string $label):void{global $checks,$failures;$checks++;if(!$value)$failures[]=$label;}
$base='/var/www/html/wp-content/plugins/joyrent-rentals/data/';$old=json_decode(file_get_contents($base.'page-seeds-1.7.json'),true);$next=json_decode(file_get_contents($base.'legal-pages.json'),true);$faq=json_decode(file_get_contents($base.'faq.json'),true);
foreach(['uk'=>['faq','Питання про оренду'],'ru'=>['faq-ru','Вопросы об аренде']] as $lang=>[$slug,$title]){$content='';foreach($faq[$lang] as $entry)$content.='<details><summary>'.esc_html($entry['question']).'</summary><p>'.esc_html($entry['answer']).'</p></details>';$next[$slug]=['title'=>$title,'content'=>$content];}
try {
    $orders=wc_get_orders(['limit'=>-1,'return'=>'ids']);$games=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'fields'=>'ids']);
    update_option('joyrent_settings',array_merge((array)$settings,['city'=>'Одеса','city_ru'=>'Одесса','deposit_ps4'=>7500,'deposit_ps5'=>25000,'base_controllers'=>2,'extra_controller_fee'=>0,'delivery_fee'=>'','delivery_green_fee'=>200,'delivery_yellow_fee'=>300,'free_delivery_from'=>7]));
    foreach($old as $slug=>$seed){$page=get_page_by_path($slug);if(!$page)throw new RuntimeException('Missing local page '.$slug);$original[$slug]=$page;wp_update_post(['ID'=>$page->ID,'post_title'=>$seed['title'],'post_content'=>$seed['content'],'post_excerpt'=>'','post_status'=>'publish']);}
    $approved=get_option('joyrent_settings');update_option('joyrent_version','1.7.1');JR_Store::upgrade();
    foreach($next as $slug=>$seed){$page=get_post($original[$slug]->ID);options_migration_check($page->post_content===$seed['content'],'Unedited previous managed '.$slug.' migrates in place');}
    options_migration_check(get_option('joyrent_version')==='1.8.2','Upgrade advances current plugin version');
    options_migration_check(get_option('joyrent_settings')===$approved,'Existing private/business settings stay intact');
    foreach(['faq','faq-ru','umovy-orendy','usloviya-arendy'] as $slug)options_migration_check(str_contains(get_post($original[$slug]->ID)->post_content,$slug==='faq'||$slug==='umovy-orendy'?'перевірки паспорта':'проверки паспорта'),'Managed '.$slug.' explains conditional contract and document verification');
    $after=[];foreach($original as $slug=>$page){$after[$slug]=get_post($page->ID)->post_content;}JR_Store::upgrade();foreach($after as $slug=>$content)options_migration_check(get_post($original[$slug]->ID)->post_content===$content,'Repeated migration remains idempotent '.$slug);
    $changes=['faq'=>['post_content'=>'Owner FAQ'],'faq-ru'=>['post_title'=>'Owner title'],'umovy-orendy'=>['post_excerpt'=>'Owner excerpt'],'usloviya-arendy'=>['post_status'=>'draft'],'konfidentsiinist'=>['post_content'=>'Owner privacy'],'konfidentsialnost'=>['post_title'=>'Owner RU privacy']];$custom=[];
    foreach($old as $slug=>$seed){wp_update_post(array_merge(['ID'=>$original[$slug]->ID,'post_title'=>$seed['title'],'post_content'=>$seed['content'],'post_excerpt'=>'','post_status'=>'publish'],$changes[$slug]));$custom[$slug]=get_post($original[$slug]->ID);}
    update_option('joyrent_version','1.7.1');JR_Store::upgrade();
    foreach($custom as $slug=>$before){$page=get_post($before->ID);options_migration_check($page->post_title===$before->post_title&&$page->post_content===$before->post_content&&$page->post_excerpt===$before->post_excerpt&&$page->post_status===$before->post_status,'Owner-edited '.$slug.' is preserved');}
    options_migration_check(wc_get_orders(['limit'=>-1,'return'=>'ids'])===$orders&&get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'fields'=>'ids'])===$games,'Page upgrade does not rewrite orders or games');
    options_migration_check($mail===0,'Managed-page migration sends no email');
}finally{foreach($original as $page)wp_update_post(['ID'=>$page->ID,'post_title'=>$page->post_title,'post_content'=>$page->post_content,'post_excerpt'=>$page->post_excerpt,'post_status'=>$page->post_status]);update_option('joyrent_settings',$settings);update_option('joyrent_version',$version);}
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures,'mailAttempts'=>$mail],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
