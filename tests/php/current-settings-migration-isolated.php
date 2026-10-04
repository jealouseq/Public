<?php
// Upgrade an installed 1.8.1 fixture using real migration logic and in-memory WP state.
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
$settings=array_merge(JR_Settings::defaults(),['search_indexing'=>true,'notification_email'=>'synthetic@example.invalid']);
migration_fixture($current,$settings);$before=serialize($pages);JR_Store::upgrade();
migration_check(get_option('joyrent_version')==='1.8.2','Installed 1.8.1 advances to1.8.2');
migration_check(serialize($pages)===$before&&$writes===0,'Approved current pages remain unchanged');
migration_check(get_option('joyrent_settings')===$settings&&get_option('wp_page_for_privacy_policy')===2,'Upgrade preserves complete private/admin settings and assigned policy');
$custom=array_merge($settings,['deposit_ps5'=>9000,'base_controllers'=>1,'extra_controller_fee'=>75]);
migration_fixture($current,$custom);$privacy=[$pages['konfidentsiinist'],$pages['konfidentsialnost']];JR_Store::upgrade();
foreach(['faq','faq-ru','umovy-orendy','usloviya-arendy'] as $slug)migration_check($pages[$slug]->post_content===$neutral[$slug]['content'],'Current managed '.$slug.' reconciles custom conditions during upgrade');
migration_check(get_option('joyrent_settings')===$custom,'Custom commercial and private settings stay unchanged');
migration_check($pages['konfidentsiinist']==$privacy[0]&&$pages['konfidentsialnost']==$privacy[1],'Upgrade leaves current privacy pages unchanged');
$before=serialize($pages);$beforeWrites=$writes;JR_Store::upgrade();migration_check(serialize($pages)===$before&&$writes===$beforeWrites,'Repeated current upgrade is idempotent');
foreach(['post_title'=>'Owner title','post_content'=>'Owner text','post_excerpt'=>'Owner excerpt','post_status'=>'draft'] as $field=>$value){
    migration_fixture($current,$custom);foreach($pages as $page)$page->$field=$value;$before=serialize($pages);JR_Store::upgrade();
    migration_check(serialize($pages)===$before&&$writes===0,'Upgrade preserves owner '.$field);
    migration_check(get_option('joyrent_version')==='1.8.2','Owner pages do not block version advancement '.$field);
}
echo json_encode(['checks'=>$checks,'failures'=>$failures,'realDatabaseWrites'=>0,'realOrdersCreated'=>0,'realMailCalls'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
