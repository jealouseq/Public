<?php
// Upgrade released 1.8.4 legal pages/contact settings without real writes or mail.
define('ABSPATH','/isolated/');
final class WooCommerce {}
final class JR_Lock {
    public static function acquire(string $name): string { return 'isolated-owner'; }
    public static function release(string $name,string $owner): void {}
}
final class WP_Post {
    public function __construct(public int $ID,public string $post_name,public string $post_title,public string $post_content,public string $post_status='publish',public string $post_excerpt='') {}
}
$options=[];$pages=[];$writes=0;$checks=0;$failures=[];
function get_option(string $key,mixed $default=false): mixed { return $GLOBALS['options'][$key]??$default; }
function update_option(string $key,mixed $value,mixed $autoload=null): bool { $GLOBALS['options'][$key]=$value;return true; }
function get_page_by_path(string $slug): ?WP_Post { return isset($GLOBALS['pages'][$slug])?clone $GLOBALS['pages'][$slug]:null; }
function wp_update_post(array $data): int { foreach($GLOBALS['pages'] as $page)if($page->ID===$data['ID']){foreach($data as $key=>$value)$page->$key=$value;$GLOBALS['writes']++;return $page->ID;}throw new LogicException('Missing fixture page'); }
function wp_insert_post(...$args): never { throw new LogicException('Upgrade unexpectedly inserts pages'); }
function is_wp_error(mixed $value): bool { return false; }
function esc_html(string $value): string { return htmlspecialchars($value,ENT_QUOTES,'UTF-8'); }
function get_posts(...$args): never { throw new LogicException('Upgrade unexpectedly accesses game database'); }
function wc_get_orders(...$args): never { throw new LogicException('Upgrade unexpectedly accesses orders'); }
function wp_mail(...$args): never { throw new LogicException('Upgrade unexpectedly sends mail'); }
function migration_check(bool $condition,string $label): void { $GLOBALS['checks']++;if(!$condition)$GLOBALS['failures'][]=$label; }
$plugin=dirname(__DIR__,2).'/wordpress/joyrent-rentals';
foreach(['settings','store'] as $file)require $plugin.'/includes/'.$file.'.php';
$current=json_decode(file_get_contents($plugin.'/data/legal-pages.json'),true,512,JSON_THROW_ON_ERROR);
$faq=json_decode(file_get_contents($plugin.'/data/faq.json'),true,512,JSON_THROW_ON_ERROR);
$neutral=json_decode(file_get_contents($plugin.'/data/page-defaults-neutral.json'),true,512,JSON_THROW_ON_ERROR);
foreach(['uk'=>['faq','Питання про оренду'],'ru'=>['faq-ru','Вопросы об аренде']] as $lang=>[$slug,$title]){$content='';foreach($faq[$lang] as $entry)$content.='<details><summary>'.esc_html($entry['question']).'</summary><p>'.esc_html($entry['answer']).'</p></details>';$current[$slug]=['title'=>$title,'content'=>$content];}
function migration_fixture(array $seeds,array $settings): void {
    $GLOBALS['pages']=[];$id=1;$GLOBALS['writes']=0;
    foreach($seeds as $slug=>$seed)$GLOBALS['pages'][$slug]=new WP_Post($id++,$slug,$seed['title'],$seed['content']);
    $GLOBALS['options']=['joyrent_version'=>'1.8.1','joyrent_settings'=>$settings,'wp_page_for_privacy_policy'=>2];
}

$released=json_decode(file_get_contents($plugin.'/data/page-seeds-1.8.4.json'),true,512,JSON_THROW_ON_ERROR);
$releasedNeutral=json_decode(file_get_contents($plugin.'/data/page-defaults-neutral-1.8.4.json'),true,512,JSON_THROW_ON_ERROR);
$settings=array_merge(JR_Settings::defaults(),['email'=>'','notification_email'=>'private@example.invalid','pickup'=>true]);
foreach([$released,$releasedNeutral] as $seeds){
    migration_fixture(array_merge($current,$seeds),$settings);$options['joyrent_version']='1.8.4';
    $ids=array_map(fn($page)=>$page->ID,$pages);JR_Store::upgrade();
    migration_check(get_option('joyrent_version')==='1.8.6','Released version advances to 1.8.6');
    foreach($released as $slug=>$_) migration_check($pages[$slug]->post_content===$current[$slug]['content']&&$pages[$slug]->ID===$ids[$slug],'Released managed '.$slug.' updated in place');
    migration_check((get_option('joyrent_settings')['email']??null)==='info@joyrent.online','Blank public email upgraded');
    migration_check(get_option('joyrent_settings')['notification_email']==='private@example.invalid','Private notification recipient preserved');
    migration_check(get_option('wp_page_for_privacy_policy')===2,'Assigned privacy policy preserved');
    $before=serialize($pages);$beforeWrites=$writes;JR_Store::upgrade();
    migration_check(serialize($pages)===$before&&$writes===$beforeWrites,'Current update is idempotent');
}
$customCity=array_merge($settings,['city'=>'Київ','city_ru'=>'Киев']);
migration_fixture(array_merge($current,$releasedNeutral),$customCity);$options['joyrent_version']='1.8.4';JR_Store::upgrade();
foreach(['umovy-orendy'=>'в Одесі','usloviya-arendy'=>'в Одессе'] as $slug=>$city) migration_check($pages[$slug]->post_content===$neutral[$slug]['content']&&!str_contains($pages[$slug]->post_content,$city),'Neutral '.$slug.' does not invent a custom shop city');
foreach(['post_title'=>'Owner title','post_content'=>'Owner content','post_excerpt'=>'Owner excerpt','post_status'=>'draft'] as $field=>$value){
    migration_fixture(array_merge($current,$released),$settings);$options['joyrent_version']='1.8.4';
    foreach($pages as $page)$page->$field=$value;$before=serialize($pages);JR_Store::upgrade();
    migration_check(serialize($pages)===$before&&$writes===0,'Owner '.$field.' is preserved');
}
foreach(['missing','custom'] as $emailCase){
    $custom=$settings;if($emailCase==='missing')unset($custom['email']);else $custom['email']='owner@example.invalid';
    migration_fixture($current,$custom);$options['joyrent_version']='1.8.4';JR_Store::upgrade();
    migration_check((get_option('joyrent_settings')['email']??null)===($emailCase==='missing'?'info@joyrent.online':'owner@example.invalid'),'Public email '.$emailCase.' migration');
}
// A reliability-only update must not replay the earlier contact/copy migration.
$owner=array_merge(JR_Settings::defaults(),['email'=>'','notification_email'=>'private@example.invalid','city'=>'Київ','pickup'=>true]);
migration_fixture(array_merge($current,$releasedNeutral),$owner);$options['joyrent_version']='1.8.5';
$beforePages=serialize($pages);$beforeSettings=$options['joyrent_settings'];JR_Store::upgrade();
migration_check(get_option('joyrent_version')==='1.8.6','Reliability update advances existing 1.8.5 install');
migration_check(serialize($pages)===$beforePages&&$writes===0,'Reliability update leaves current owner pages untouched');
migration_check($options['joyrent_settings']===$beforeSettings,'Reliability update preserves an intentionally cleared public email and private settings');
echo json_encode(['checks'=>$checks,'failures'=>$failures,'realDatabaseWrites'=>0,'realOrdersCreated'=>0,'realMailCalls'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
