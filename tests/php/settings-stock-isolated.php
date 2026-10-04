<?php
// Isolated production-plugin tests. No wp-load, database, orders, mail, or network.
define('ABSPATH', '/isolated/');
if (($argv[1]??'')!=='--no-woo') { class WooCommerce {} }
class WP_Post {
    public function __construct(public int $ID, public string $post_name, public string $post_title, public string $post_content, public string $post_status='publish', public string $post_excerpt='', public string $post_type='page', public int $menu_order=0) {}
}
class WC_Product {
    public function __construct(public string $status='publish', public string $price='720', public string $stock='instock', public bool $manage=false, public int $quantity=1, public bool $backorders=false) {}
    public function get_status(): string { return $this->status; }
    public function get_price(): string { return $this->price; }
    public function is_in_stock(): bool { return $this->stock!=='outofstock'; }
    public function has_enough_stock(int $quantity): bool { return !$this->manage || $this->backorders || $this->quantity >= $quantity; }
}
$hooks=[];$sanitizers=[];$options=[];$posts=[];$post_meta=[];$products=[];$post_writes=[];$checks=0;$failures=[];$manager=true;
function add_action(string $hook,$callback,int $priority=10,int $accepted=1): void { $GLOBALS['hooks'][$hook][]=[$priority,$callback,$accepted]; }
function add_filter(...$args): void {}
function register_activation_hook(...$args): void {}
function register_deactivation_hook(...$args): void {}
function do_action(string $hook,...$args): void {
    $callbacks=$GLOBALS['hooks'][$hook]??[];usort($callbacks,fn($a,$b)=>$a[0]<=>$b[0]);
    foreach ($callbacks as [, $callback,$accepted]) if (is_callable($callback)) call_user_func_array($callback,array_slice($args,0,$accepted));
}
function register_setting(string $group,string $option,array $args): void { $GLOBALS['sanitizers'][$option]=$args['sanitize_callback']; }
function get_option(string $key,$default=false) { return $GLOBALS['options'][$key]??$default; }
function update_option(string $key,$value,...$args): bool {
    if (isset($GLOBALS['sanitizers'][$key])) $value=call_user_func($GLOBALS['sanitizers'][$key],$value);
    $exists=array_key_exists($key,$GLOBALS['options']);$old=get_option($key);if ($exists&&$old===$value) return false;
    $GLOBALS['options'][$key]=$value;
    if ($exists) do_action('update_option_'.$key,$old,$value,$key);else do_action('add_option_'.$key,$key,$value);
    return true;
}
function get_page_by_path(string $slug) { foreach ($GLOBALS['posts'] as $post) if ($post->post_type==='page'&&$post->post_name===$slug) return clone $post;return null; }
function wp_update_post(array $data): int { $post=$GLOBALS['posts'][$data['ID']];foreach ($data as $key=>$value) if ($key!=='ID') $post->$key=$value;$GLOBALS['post_writes'][]=$post->post_name;return $post->ID; }
function wp_insert_post(array $data,...$args): int { $id=count($GLOBALS['posts'])+100;$GLOBALS['posts'][$id]=new WP_Post($id,$data['post_name'],$data['post_title'],$data['post_content'],$data['post_status'],'',$data['post_type']);$GLOBALS['post_writes'][]=$data['post_name'];return $id; }
function is_wp_error($value): bool { return false; }
function get_posts(array $args): array {
    $posts=array_values(array_filter($GLOBALS['posts'],function($post)use($args){
        return $post->post_type===$args['post_type'] && (!isset($args['post_status']) || in_array($post->post_status,(array)$args['post_status'],true));
    }));
    if (($args['numberposts']??-1)>0) $posts=array_slice($posts,0,$args['numberposts']);
    return ($args['fields']??'')==='ids'?array_map(fn($post)=>$post->ID,$posts):array_map(fn($post)=>clone $post,$posts);
}
function get_post_stati(): array { return ['publish'=>'publish','draft'=>'draft','trash'=>'trash']; }
function get_post_meta(int $id,string $key='',bool $single=false) { return $GLOBALS['post_meta'][$id][$key]??($single?'':[]); }
function get_the_post_thumbnail_url(...$args): false { return false; }
function wp_strip_all_tags(string $value): string { return strip_tags($value); }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function sanitize_email(string $value): string { return filter_var($value,FILTER_VALIDATE_EMAIL)?$value:''; }
function is_email(string $value): bool { return (bool)filter_var($value,FILTER_VALIDATE_EMAIL); }
function esc_url_raw(string $value,array $schemes=[]): string { return str_starts_with($value,'https://')?$value:''; }
function esc_html($value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function esc_attr($value): string { return esc_html($value); }
function esc_url($value): string { return esc_html($value); }
function current_user_can(...$args): bool { return $GLOBALS['manager']; }
function checked($value,$expected=true,bool $echo=true): string { $html=$value==$expected?'checked="checked"':'';if ($echo) echo $html;return $html; }
function settings_fields(string $group): void {}
function submit_button(...$args): void {}
function admin_url(string $path=''): string { return '/admin/'.$path; }
function wp_nonce_field(...$args): void {}
function get_woocommerce_currency(): string { return 'UAH'; }
function wc_get_product_id_by_sku(string $sku): int { return isset($GLOBALS['products'][$sku])?array_search($sku,array_keys($GLOBALS['products']),true)+1:0; }
function wc_get_product(int $id) { return array_values($GLOBALS['products'])[$id-1]??null; }
function check(bool $value,string $label): void { $GLOBALS['checks']++;if (!$value) $GLOBALS['failures'][]=$label; }
require dirname(__DIR__,2).'/wordpress/joyrent-rentals/joyrent-rentals.php';
do_action('admin_init');
if (!class_exists('WooCommerce')) {
    check(method_exists(JR_Settings::class,'search_indexing')&&!JR_Settings::search_indexing(),'Without WooCommerce indexing defaults to false');
    $options['joyrent_settings']=JR_Settings::sanitize(array_merge(JR_Settings::defaults(),['search_indexing'=>'1','notification_email'=>'synthetic@example.invalid']));
    check(method_exists(JR_Settings::class,'search_indexing')&&JR_Settings::search_indexing(),'Without WooCommerce admin indexing opt-in remains readable');
    $public=JR_Settings::public();
    check(!array_key_exists('search_indexing',$public)&&!array_key_exists('notification_email',$public)&&!str_contains(json_encode($public),'synthetic@example.invalid'),'Without WooCommerce admin settings and private notification stay out of public settings');
    $catalog=JR_Store::catalog();
    check($catalog['acceptingRequests']===false&&$catalog['tariffs']['ps5']===[]&&$catalog['tariffs']['ps4']===[],'Without WooCommerce catalog does not accept requests');
    echo json_encode(['mode'=>'no-woocommerce','checks'=>$checks,'failures'=>$failures,'realDatabaseWrites'=>0,'realOrdersCreated'=>0,'realMailCalls'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
    exit($failures?1:0);
}

$defaults=JR_Settings::defaults();
check(($defaults['search_indexing']??null)===false,'Search indexing defaults to false');
check(method_exists(JR_Settings::class,'search_indexing')&&!JR_Settings::search_indexing(),'Search indexing getter defaults to false');
$enabled=JR_Settings::sanitize(array_merge($defaults,['search_indexing'=>'1']));
check(($enabled['search_indexing']??null)===true,'Checked indexing control sanitizes to true');
foreach ([null,false,'0','false',[]] as $value) {
    $input=$defaults;if ($value===null) unset($input['search_indexing']);else $input['search_indexing']=$value;
    check((JR_Settings::sanitize($input)['search_indexing']??null)===false,'Unchecked or malformed indexing stays false: '.get_debug_type($value));
}
$options['joyrent_settings']=$enabled;
check(method_exists(JR_Settings::class,'search_indexing')&&JR_Settings::search_indexing(),'Saved true indexing is readable');
check(!array_key_exists('search_indexing',JR_Settings::public())&&!array_key_exists('notification_email',JR_Settings::public()),'Indexing and private notification setting excluded from public settings');
ob_start();JR_Settings::page();$html=ob_get_clean();
check(str_contains($html,'name="joyrent_settings[search_indexing]"'),'Manager settings page provides indexing checkbox');
$manager=false;ob_start();JR_Settings::page();$html=ob_get_clean();$manager=true;
check($html==='','Settings checkbox remains manager-only');

$data_dir=dirname(__DIR__,2).'/wordpress/joyrent-rentals/data/';
$legal=json_decode(file_get_contents($data_dir.'legal-pages.json'),true);
$faq=json_decode(file_get_contents($data_dir.'faq.json'),true);
$neutral=json_decode(file_get_contents($data_dir.'page-defaults-neutral.json'),true);
$current=$legal;
foreach (['uk'=>['faq','Питання про оренду'],'ru'=>['faq-ru','Вопросы об аренде']] as $language=>[$slug,$title]) {
    $content='';foreach ($faq[$language] as $entry) $content.='<details><summary>'.esc_html($entry['question']).'</summary><p>'.esc_html($entry['answer']).'</p></details>';
    $current[$slug]=['title'=>$title,'content'=>$content];
}
function reset_pages(array $seeds): void {
    $GLOBALS['posts']=[];$GLOBALS['post_writes']=[];$id=1;
    foreach ($seeds as $slug=>$seed) { $GLOBALS['posts'][$id]=new WP_Post($id,$slug,$seed['title'],$seed['content']);$id++; }
}
$copy_hook=array_filter($hooks['update_option_joyrent_settings']??[],fn($entry)=>$entry[1]===[JR_Store::class,'settings_changed']&&$entry[2]===2&&is_callable($entry[1]));
check((bool)$copy_hook,'Production option update registers settings-copy callback with old/new values');
check((bool)array_filter($hooks['add_option_joyrent_settings']??[],fn($entry)=>$entry[2]===2&&is_callable($entry[1])),'Production first option save registers settings-copy callback');
reset_pages($current);unset($options['joyrent_settings']);$options['joyrent_version']='1.8.4';$options['wp_page_for_privacy_policy']=2;
update_option('joyrent_settings',array_merge($defaults,['delivery_fee'=>125]));
foreach (['faq','faq-ru','umovy-orendy','usloviya-arendy'] as $slug) check(get_page_by_path($slug)->post_content===$neutral[$slug]['content'],'First custom settings save reconciles current '.$slug.' even when version is current');
check(get_option('joyrent_version')==='1.8.4'&&get_option('wp_page_for_privacy_policy')===2,'First settings save leaves version and assigned privacy untouched');
reset_pages($current);$options['joyrent_settings']=$defaults;$options['wp_page_for_privacy_policy']=2;
$privacy_before=[];foreach (['konfidentsiinist','konfidentsialnost'] as $slug) $privacy_before[$slug]=get_page_by_path($slug);
update_option('joyrent_settings',array_merge($defaults,['deposit_ps5'=>30000]));
foreach (['faq','faq-ru','umovy-orendy','usloviya-arendy'] as $slug) check(get_page_by_path($slug)->post_content===$neutral[$slug]['content'],'Current managed '.$slug.' becomes neutral immediately on settings save');
foreach ($privacy_before as $slug=>$post) check(get_page_by_path($slug)==$post,'Settings save leaves '.$slug.' untouched');
check(get_option('wp_page_for_privacy_policy')===2,'Settings save leaves assigned privacy page untouched');
update_option('joyrent_settings',$defaults);
foreach (['faq','faq-ru','umovy-orendy','usloviya-arendy'] as $slug) check(get_page_by_path($slug)->post_content===$current[$slug]['content'],'Neutral managed '.$slug.' returns to approved copy when settings restored');

reset_pages($current);$options['joyrent_settings']=$defaults;
$posts[get_page_by_path('faq')->ID]->post_content.='<p>Owner FAQ text</p>';
$posts[get_page_by_path('faq-ru')->ID]->post_title='Owner title';
$posts[get_page_by_path('umovy-orendy')->ID]->post_excerpt='Owner excerpt';
$posts[get_page_by_path('usloviya-arendy')->ID]->post_status='draft';
$owner_before=array_map(fn($slug)=>get_page_by_path($slug),['faq','faq-ru','umovy-orendy','usloviya-arendy']);
update_option('joyrent_settings',array_merge($defaults,['base_controllers'=>1,'extra_controller_fee'=>100]));
foreach ($owner_before as $post) check(get_page_by_path($post->post_name)==$post,'Owner content/title/excerpt/draft preserved: '.$post->post_name);
reset_pages($current);$options['joyrent_settings']=$defaults;
update_option('joyrent_settings',array_merge($defaults,['search_indexing'=>true]));
check($post_writes===[],'Search-only update does not rewrite copy');

$stock_cases=[
    'missing'=>[null,false],
    'published in stock'=>[new WC_Product(),true],
    'draft'=>[new WC_Product('draft'),false],
    'empty price'=>[new WC_Product('publish',''),false],
    'zero price'=>[new WC_Product('publish','0'),false],
    'negative price'=>[new WC_Product('publish','-1'),false],
    'out of stock'=>[new WC_Product('publish','720','outofstock'),false],
    'managed zero stock'=>[new WC_Product('publish','720','instock',true,0),false],
    'managed one stock'=>[new WC_Product('publish','720','instock',true,1),true],
    'allowed backorder'=>[new WC_Product('publish','720','onbackorder',true,0,true),true],
];
foreach ($stock_cases as $label=>[$product,$expected]) check(method_exists(JR_Store::class,'requestable')&&JR_Store::requestable($product)===$expected,'Requestability respects '.$label);
$products=['joyrent-ps5-3'=>new WC_Product('publish','720','outofstock')];
$catalog=JR_Store::catalog();
check($catalog['acceptingRequests']===false&&$catalog['tariffs']['ps5']===[]&&$catalog['tariffs']['ps4']===[],'No in-stock offers disables requests and removes unavailable tariff');
$products['joyrent-ps4-3']=new WC_Product('publish','610','instock',true,1);
$catalog=JR_Store::catalog();
check($catalog['acceptingRequests']===true&&count($catalog['tariffs']['ps4'])===1&&$catalog['tariffs']['ps4'][0]['price']===610.0&&$catalog['tariffs']['ps5']===[],'Available stock preserves only the current priced offer');
$posts=[];$post_meta=[];
$games=JR_Domain::catalog()['games'];$game=$games[0];$game['available']=false;
$posts[900]=new WP_Post(900,'unverified',$game['title'],$game['description'],'publish','','joyrent_game');$post_meta[900]['_jr_game']=$game;
$published=JR_Store::catalog()['games'];
check(count($published)===1&&$published[0]['available']===false,'Published unverified game remains a catalog wish');
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
$payload=['console'=>$game['platforms'][0],'days'=>3,'startDate'=>$today,'name'=>'Synthetic Name','phone'=>'+380000000001','controllers'=>2,'gameIds'=>[$game['id']],'method'=>'delivery','address'=>'Synthetic address 1','consent'=>true];
try { $valid=JR_Domain::validate($payload,$today,$published);check($valid['gameIds']===[$game['id']],'Unverified license allows an explicit wish'); }
catch (InvalidArgumentException $e) { check(false,'Unverified license allows an explicit wish'); }
$posts[900]->post_status='draft';
check(JR_Store::catalog()['games']===[],'Draft game remains explicitly hidden');
$resident=array_values(array_filter(JR_Domain::catalog()['games'],fn($game)=>$game['id']==='resident-evil-requiem'))[0];
check($resident['genre']==='Жахи'&&$resident['eyebrow']==='Жахи','Bundled Resident Evil uses clear Ukrainian horror label');
$posts=[];$post_meta=[];
$legacy_resident=$resident;$legacy_resident['genre']='Горор';$legacy_resident['eyebrow']='Горор';
$legacy_resident['descriptionRu']='Owner Russian description';$legacy_resident['image']='owner-resident-art';$legacy_resident['owner_field']='Preserve unknown field';
$posts[901]=new WP_Post(901,'resident-evil-requiem','Owner Resident title','Owner Ukrainian description','publish','','joyrent_game');
$post_meta[901]=['_jr_game'=>$legacy_resident,'_jr_game_id'=>'resident-evil-requiem','owner_note'=>'Preserve post metadata'];
$before_posts=serialize($posts);$before_meta=serialize($post_meta);$projected=JR_Games::records()[0];
check($projected['genre']==='Жахи'&&$projected['eyebrow']==='Жахи','Existing exact legacy Resident Evil labels are corrected in the public catalog');
check($projected['title']==='Owner Resident title'&&$projected['description']==='Owner Ukrainian description'&&$projected['descriptionRu']==='Owner Russian description'&&$projected['image']==='owner-resident-art'&&$projected['owner_field']==='Preserve unknown field','Legacy label projection preserves every other author game field');
check(serialize($posts)===$before_posts&&serialize($post_meta)===$before_meta,'Reading corrected labels never writes game posts or metadata');
$post_meta[901]['_jr_game']['genre']='Owner genre';$post_meta[901]['_jr_game']['eyebrow']='Owner eyebrow';$projected=JR_Games::records()[0];
check($projected['genre']==='Owner genre'&&$projected['eyebrow']==='Owner eyebrow','Author Resident Evil labels are preserved');
$post_meta[901]['_jr_game']['id']='resident-evil-requiem-owner-901';$post_meta[901]['_jr_game']['genre']='Горор';$post_meta[901]['_jr_game']['eyebrow']='Горор';$projected=JR_Games::records()[0];
check($projected['genre']==='Горор'&&$projected['eyebrow']==='Горор','Owner duplicate labels are outside the bundled correction');
echo json_encode(['checks'=>$checks,'failures'=>$failures,'realDatabaseWrites'=>0,'realOrdersCreated'=>0,'realMailCalls'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
