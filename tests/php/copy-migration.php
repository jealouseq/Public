<?php
// Local WordPress page-copy migration only. Private settings are not printed; no mail is sent.
require '/var/www/html/wp-load.php';
$mail=0;add_filter('pre_wp_mail',function()use(&$mail){$mail++;return true;});
$base='/var/www/html/wp-content/plugins/joyrent-rentals/data/';$old=json_decode(file_get_contents($base.'page-seeds-1.6.json'),true);$neutral=json_decode(file_get_contents($base.'page-defaults-neutral.json'),true);$next=json_decode(file_get_contents($base.'legal-pages.json'),true);$faq=json_decode(file_get_contents($base.'faq.json'),true);
foreach(['uk'=>['faq','Питання про оренду'],'ru'=>['faq-ru','Вопросы об аренде']] as $language=>[$slug,$title]){$content='';foreach($faq[$language] as $e)$content.='<details><summary>'.esc_html($e['question']).'</summary><p>'.esc_html($e['answer']).'</p></details>';$next[$slug]=['title'=>$title,'content'=>$content];}
$version=get_option('joyrent_version');$settings=get_option('joyrent_settings');$original=[];$created=[];$checks=0;$failures=[];
function copy_check(bool $value,string $label):void{global $checks,$failures;$checks++;if(!$value)$failures[]=$label;}
try {
    foreach($old as $slug=>$seed){$post=get_page_by_path($slug);if(!$post)throw new RuntimeException('Missing local seeded page '.$slug);$original[$slug]=$post;wp_update_post(['ID'=>$post->ID,'post_title'=>$seed['title'],'post_content'=>$seed['content'],'post_excerpt'=>'','post_status'=>'publish']);}
    $games=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'fields'=>'ids']);$orders=wc_get_orders(['limit'=>-1,'return'=>'ids']);
    update_option('joyrent_version','1.6.0');JR_Store::upgrade();
    foreach($next as $slug=>$seed){$post=get_page_by_path($slug);copy_check($post->ID===$original[$slug]->ID,'Existing page ID preserved '.$slug);copy_check($post->post_content===$seed['content'],'Unchanged seeded page copy updated '.$slug);}
    copy_check(get_option('joyrent_version')==='1.8.6','Copy migration advances to current version');
    copy_check(get_option('joyrent_settings')===$settings,'Owner business/private settings unchanged by copy migration');
    $before=[];
    $custom=['faq'=>['post_content'=>'Owner FAQ content'],'faq-ru'=>['post_excerpt'=>'Owner FAQ excerpt'],'umovy-orendy'=>['post_title'=>'Owner terms title'],'usloviya-arendy'=>['post_content'=>'Owner RU terms content'],'konfidentsiinist'=>['post_status'=>'draft'],'konfidentsialnost'=>['post_content'=>'Owner RU privacy content']];
    foreach($old as $slug=>$seed){$fields=array_merge(['ID'=>$original[$slug]->ID,'post_title'=>$seed['title'],'post_content'=>$seed['content'],'post_excerpt'=>'','post_status'=>'publish'],$custom[$slug]);wp_update_post($fields);$before[$slug]=get_post($original[$slug]->ID);}
    update_option('joyrent_version','1.6.0');JR_Store::upgrade();
    foreach($before as $slug=>$post){$after=get_post($post->ID);copy_check($after->post_title===$post->post_title&&$after->post_content===$post->post_content&&$after->post_excerpt===$post->post_excerpt&&$after->post_status===$post->post_status,'Custom page state preserved '.$slug);}
    copy_check(get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>-1,'fields'=>'ids'])===$games&&wc_get_orders(['limit'=>-1,'return'=>'ids'])===$orders,'Copy migration leaves games and orders unchanged');
    copy_check(str_contains($faq['uk'][2]['answer'],'без доплати')&&str_contains($faq['ru'][2]['answer'],'без доплаты'),'FAQ states free second controller in both languages');
    copy_check(str_contains($faq['uk'][4]['answer'],'7 500')&&str_contains($faq['uk'][4]['answer'],'25 000')&&str_contains($faq['ru'][4]['answer'],'7 500')&&str_contains($faq['ru'][4]['answer'],'25 000'),'FAQ states approved deposit amounts in both languages');
    copy_check(str_contains($faq['uk'][1]['answer'],'одиночної гри')&&str_contains($faq['ru'][1]['answer'],'одиночной игры'),'FAQ explains that some single-player games also need internet');
    copy_check(str_contains($next['konfidentsiinist']['content'],'Не надсилай')&&str_contains($next['konfidentsialnost']['content'],'Не отправляй'),'Privacy instructs against sensitive free-text input');
    copy_check(str_contains($next['konfidentsiinist']['content'],'Telegram або зателефонуй')&&str_contains($next['konfidentsialnost']['content'],'Telegram или позвони'),'Privacy points to approved public contact channels');
    try{JR_Domain::tariff('ps4',1);copy_check(false,'Visitor tariff error uses informal Ukrainian');}catch(InvalidArgumentException $e){copy_check($e->getMessage()==='Обери доступний тариф.'&&JR_Locale::message($e->getMessage(),'ru')==='Выбери доступный тариф.','Visitor tariff error uses informal Ukrainian');}
    copy_check(JR_Locale::message('Ця гра недоступна для обраної консолі.','ru')==='Эта игра недоступна для выбранной консоли.','Platform validation uses natural availability wording');
    $business=['faq','faq-ru','umovy-orendy','usloviya-arendy'];
    $custom_settings=array_merge((array)$settings,['deposit_ps5'=>9000,'base_controllers'=>1,'extra_controller_fee'=>75,'delivery_green_fee'=>150,'delivery_yellow_fee'=>250,'free_delivery_from'=>14]);
    update_option('joyrent_settings',$custom_settings);
    foreach($old as $slug=>$seed)wp_update_post(['ID'=>$original[$slug]->ID,'post_name'=>$slug,'post_title'=>$seed['title'],'post_content'=>$seed['content'],'post_excerpt'=>'','post_status'=>'publish']);
    update_option('joyrent_version','1.6.0');JR_Store::upgrade();
    foreach($business as $slug)copy_check(get_post($original[$slug]->ID)->post_content===$neutral[$slug]['content'],'Custom business conditions use neutral booking copy '.$slug);
    copy_check(get_option('joyrent_settings')===$custom_settings,'Custom fees/deposits/threshold and private settings preserved');
    foreach(['konfidentsiinist','konfidentsialnost'] as $slug)copy_check(get_post($original[$slug]->ID)->post_content===$next[$slug]['content'],'Privacy migrates independently of custom prices '.$slug);
    foreach(['faq','umovy-orendy'] as $slug)wp_update_post(['ID'=>$original[$slug]->ID,'post_name'=>'copy-fixture-hidden-'.$original[$slug]->ID]);
    update_option('joyrent_version','1.6.0');JR_Store::upgrade();
    foreach(['faq','umovy-orendy'] as $slug){$post=get_page_by_path($slug);$created[]=$post->ID;copy_check($post->ID!==$original[$slug]->ID&&$post->post_content===$neutral[$slug]['content'],'Missing managed page uses neutral copy for custom conditions '.$slug);wp_delete_post($post->ID,true);wp_update_post(['ID'=>$original[$slug]->ID,'post_name'=>$slug]);}
    update_option('joyrent_settings',array_merge((array)$settings,['city'=>'Owner city','city_ru'=>'Город владельца']));
    foreach($business as $slug)wp_update_post(['ID'=>$original[$slug]->ID,'post_title'=>$old[$slug]['title'],'post_content'=>$old[$slug]['content'],'post_excerpt'=>'','post_status'=>'publish']);
    update_option('joyrent_version','1.6.0');JR_Store::upgrade();
    foreach(['umovy-orendy','usloviya-arendy'] as $slug)copy_check(get_post($original[$slug]->ID)->post_content===$neutral[$slug]['content'],'Custom city prevents approved-Odessa terms claim '.$slug);
    update_option('joyrent_settings',array_merge((array)$settings,['delivery_fee'=>125]));
    foreach($business as $slug)wp_update_post(['ID'=>$original[$slug]->ID,'post_title'=>$old[$slug]['title'],'post_content'=>$old[$slug]['content'],'post_excerpt'=>'','post_status'=>'publish']);
    update_option('joyrent_version','1.6.0');JR_Store::upgrade();
    foreach(['umovy-orendy','usloviya-arendy'] as $slug)copy_check(get_post($original[$slug]->ID)->post_content===$neutral[$slug]['content'],'Custom short delivery fee prevents contradictory zone-price terms '.$slug);
    copy_check($mail===0,'Page-copy migration makes no mail attempts');
}finally{foreach($created as $id)if(get_post($id))wp_delete_post($id,true);foreach($original as $post)wp_update_post(['ID'=>$post->ID,'post_name'=>$post->post_name,'post_title'=>$post->post_title,'post_content'=>$post->post_content,'post_excerpt'=>$post->post_excerpt,'post_status'=>$post->post_status]);update_option('joyrent_version',$version);update_option('joyrent_settings',$settings);}
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures,'mailAttempts'=>$mail],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
