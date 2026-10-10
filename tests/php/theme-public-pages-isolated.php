<?php
// Executes the real theme callbacks without WordPress, database, mail or network.
define('ABSPATH',__DIR__.'/');
define('OBJECT','OBJECT');
final class WP_Post { public function __construct(public int $ID,public string $post_name,public string $post_type='page',public string $post_status='publish') {} }
final class WP_Query {
    public array $values=['post__not_in'=>[777]];
    public function __construct(public bool $main=true,public bool $search=true) {}
    public function is_main_query(): bool { return $this->main; }
    public function is_search(): bool { return $this->search; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key,mixed $value): void { $this->values[$key]=$value; }
}
final class WooCommerce {}
final class JR_Settings { public static bool $indexing=false; public static function search_indexing(): bool { return self::$indexing; } }
$hooks=[]; $route='home'; $woo=''; $search=false; $not_found=false; $admin=false; $archive='';
$options=['blog_public'=>'1','woocommerce_shop_page_id'=>80,'woocommerce_cart_page_id'=>81,'woocommerce_checkout_page_id'=>82,'woocommerce_myaccount_page_id'=>83];
$posts=[];
foreach (['faq','faq-ru','umovy-orendy','usloviya-arendy','konfidentsiinist','konfidentsialnost'] as $index=>$slug) $posts[$slug]=new WP_Post($index+10,$slug);
$posts['hello-world']=new WP_Post(90,'hello-world','post');
$posts['tariff']=new WP_Post(100,'tariff','product');
$posts['sku-tariff']=new WP_Post(101,'sku-tariff','product');
$posts['owner-product']=new WP_Post(102,'owner-product','product');
$posts['article']=new WP_Post(110,'article','post');
$meta=[100=>['_joyrent_console'=>'ps5'],101=>['_sku'=>'joyrent-ps4-3']];
$scripts=(object)['queue'=>[],'registered'=>[]]; $styles=[];
function add_action($name,$callback,$priority=10,$arguments=1): void { global $hooks; $hooks[$name][$priority][]=$callback; }
function add_filter($name,$callback,$priority=10,$arguments=1): void { add_action($name,$callback,$priority,$arguments); }
function remove_action($name,$callback): void { global $removed; $removed[]="$name:$callback"; }
function callbacks(string $name): array { global $hooks; $groups=$hooks[$name] ?? []; ksort($groups); return array_merge(...array_values($groups)); }
function filtered(string $name,mixed $value,mixed ...$args): mixed { foreach (callbacks($name) as $callback) $value=$callback($value,...$args); return $value; }
function action(string $name,mixed ...$args): void { foreach (callbacks($name) as $callback) $callback(...$args); }
function is_front_page(): bool { global $route; return $route==='home'; }
function is_page($slugs): bool { global $route; return in_array($route,(array)$slugs,true); }
function is_search(): bool { global $search; return $search; }
function is_404(): bool { global $not_found; return $not_found; }
function is_author(): bool { global $archive; return $archive==='author'; }
function is_date(): bool { global $archive; return $archive==='date'; }
function is_attachment(): bool { global $archive; return $archive==='attachment'; }
function is_admin(): bool { global $admin; return $admin; }
function is_cart(): bool { global $woo; return $woo==='cart'; }
function is_checkout(): bool { global $woo; return $woo==='checkout'; }
function is_account_page(): bool { global $woo; return $woo==='account'; }
function is_shop(): bool { global $woo; return $woo==='shop'; }
function is_product(): bool { global $woo; return $woo==='product'; }
function is_product_taxonomy(): bool { global $woo; return $woo==='category'; }
function get_option($name,$default=false): mixed { global $options; return $options[$name] ?? $default; }
function get_page_by_path($slug,$output=OBJECT,$type='page'): ?WP_Post { global $posts; $post=$posts[$slug] ?? null; return $post&&$post->post_type===$type?$post:null; }
function get_post($id): ?WP_Post { global $posts; foreach ($posts as $post) if ($post->ID===$id) return $post; return null; }
function get_queried_object(): ?WP_Post { global $posts,$route; return $posts[$route] ?? null; }
function get_queried_object_id(): int { return get_queried_object()?->ID ?? 0; }
function get_post_type($id): string { return get_post($id)?->post_type ?? ''; }
function get_post_meta($id,$key,$single): mixed { global $meta; return $meta[$id][$key] ?? ''; }
function get_posts($args): array {
    global $posts;
    // Only implement the rental-marker predicate used by this isolated scenario.
    if ($args['post_type']!=='product'||count($args['meta_query'] ?? [])!==3) throw new RuntimeException('Unexpected product query');
    return array_values(array_map(fn($post)=>$post->ID,array_filter($posts,fn($post)=>$post->post_type==='product'&&joyrent_is_internal_tariff($post->ID))));
}
function home_url($path): string { return 'https://staging.example'.$path; }
function get_permalink($post): string { global $plain_permalinks; $post=is_int($post)?get_post($post):$post; return ($plain_permalinks ?? false) ? home_url('/?page_id='.$post->ID) : home_url('/'.$post->post_name.'/'); }
function add_query_arg($key,$value,$url): string { return $url.'?'.$key.'='.$value; }
function get_template_directory(): string { global $template_directory; return $template_directory ?? __DIR__.'/../../wordpress/joyrent'; }
function get_template_directory_uri(): string { return home_url('/theme'); }
function esc_attr($value): string { return htmlspecialchars((string)$value,ENT_QUOTES); }
function esc_url($value): string { return esc_attr($value); }
function wp_scripts(): object { global $scripts; return $scripts; }
function wp_dequeue_script($handle): void { global $scripts; $scripts->queue=array_values(array_diff($scripts->queue,[$handle])); }
function wp_dequeue_style($handle): void { global $styles; $styles=array_values(array_diff($styles,[$handle])); }
function wp_enqueue_style(...$arguments): void {}
function wp_enqueue_script(...$arguments): void {}
function rest_url($path): string { return home_url('/wp-json/'.$path); }
function is_user_logged_in(): bool { return false; }
function wp_add_inline_script(...$arguments): void { $GLOBALS['inline_scripts'][$arguments[0]]=$arguments[1]; }
function wp_json_encode($value,$flags=0): string { return json_encode($value,$flags); }
function check(bool $condition,string $message): void { global $count; if (!$condition) throw new RuntimeException($message); $count=($count ?? 0)+1; }
require __DIR__.'/../../wordpress/joyrent/functions.php';

check(!joyrent_search_indexing_enabled(),'Staging must default to noindex');
check(filtered('wp_robots',['index'=>true,'nofollow'=>true])===['nofollow'=>true,'noindex'=>true],'Noindex preserves existing WordPress restrictions');
check(filtered('wp_sitemaps_enabled',true)===false,'No public staging sitemap');
$headers=filtered('wp_headers',['Referrer-Policy'=>'same-origin']);
check($headers['Referrer-Policy']==='same-origin'&&$headers['X-Content-Type-Options']==='nosniff'&&$headers['X-Robots-Tag']==='noindex','Headers preserve explicit policy and staging noindex');
check(!isset($headers['Content-Security-Policy'])&&!isset($headers['Strict-Transport-Security']),'No host-dependent policies');

foreach (['home','faq','faq-ru','umovy-orendy','usloviya-arendy','konfidentsiinist','konfidentsialnost'] as $route) {
    foreach (['uk','ru'] as $requested) {
        $_GET=['lang'=>$requested]; $metadata=joyrent_page_metadata();
        $title=filtered('document_title_parts',['title'=>'Domain title','site'=>'staging.example','tagline'=>'Old tagline']);
        check(count($title)===1&&str_contains($title['title'],'JOYRENT'),'Branded title: '.$route);
        $expected=$route==='home'?home_url('/').($requested==='ru'?'?lang=ru':''):get_permalink(get_queried_object());
        check($metadata['url']===$expected,'Canonical respects native language and removes query: '.$route);
        ob_start(); foreach ($hooks['wp_head'][5] as $callback) $callback(); $html=ob_get_clean();
        check(substr_count($html,'rel="canonical"')===1&&substr_count($html,'name="description"')===1,'One canonical/description: '.$route);
        check(str_contains($html,'property="og:title"')&&str_contains($html,'name="twitter:card"')&&str_contains($html,'ps5-share.jpg'),'Existing PS5 share metadata: '.$route);
        check(substr_count($html,'hreflang=')===3,'All published language alternates: '.$route);
    }
}
$route='faq'; $posts['faq-ru']->post_status='draft';
ob_start(); foreach ($hooks['wp_head'][5] as $callback) $callback(); $html=ob_get_clean();
check(!str_contains($html,'hreflang="ru"'),'Draft translation not advertised');
$posts['faq-ru']->post_status='publish';
JR_Settings::$indexing=true; $options['blog_public']='0';
check(!joyrent_search_indexing_enabled(),'Owner setting cannot override WP discourage indexing');
$options['blog_public']='1';
check(joyrent_search_indexing_enabled()&&filtered('wp_sitemaps_enabled',true),'Owner opt-in enables normal sitemap');
check(!joyrent_noindex_request(),'Reading page allowed after opt-in');
foreach (['shop','cart','checkout','account','category'] as $woo) check(joyrent_noindex_request(),'Woo service noindex: '.$woo);
$woo='';
foreach (['tariff','sku-tariff','hello-world'] as $route) check(joyrent_noindex_request(),'Internal/default route noindex: '.$route);
$route='owner-product'; check(!joyrent_noindex_request(),'Other owner products are preserved');
$route='article'; check(!joyrent_noindex_request(),'Other owner posts are preserved');
foreach (['author','date','attachment'] as $archive) check(joyrent_noindex_request(),'Unused archive noindex: '.$archive);
$archive='';
$provider=new stdClass();
check(filtered('wp_sitemaps_add_provider',$provider,'users')===false,'Author sitemap provider removed');
check(filtered('wp_sitemaps_add_provider',$provider,'posts')===$provider,'Content sitemap provider preserved');
check(filtered('wp_sitemaps_taxonomies',['category'=>$provider,'post_tag'=>$provider,'product_cat'=>$provider,'product_tag'=>$provider,'product_shipping_class'=>$provider,'product_brand'=>$provider])===['category'=>$provider,'post_tag'=>$provider],'WP taxonomies preserved; internal storefront taxonomies excluded');
$search=true; check(joyrent_noindex_request(),'Search noindex'); $search=false;
check(filtered('wp_sitemaps_posts_query_args',['post__not_in'=>[777]],'product')['post__not_in']===[777,100,101],'Only tariff product IDs excluded from sitemap');
check(filtered('wp_sitemaps_posts_query_args',[],'post')['post__not_in']===[90],'Default post excluded without deleting it');
check(filtered('wp_sitemaps_posts_query_args',[],'page')['post__not_in']===[80,81,82,83],'Woo service pages excluded; FAQ/terms/privacy preserved');
$query=new WP_Query(); action('pre_get_posts',$query);
check($query->get('post__not_in')===[777,100,101,90,80,81,82,83],'Public search excludes only internal IDs');
$query=new WP_Query(false); action('pre_get_posts',$query); check($query->get('post__not_in')===[777],'Other queries untouched');

$route='faq'; $woo=''; $styles=['woocommerce-inline','wc-blocks-style','owner-style'];
$scripts->registered=['jquery'=>(object)['deps'=>['jquery-core','jquery-migrate']],'joyrent-app'=>(object)['deps'=>[]],'owner-parent'=>(object)['deps'=>['owner-child']],'owner-child'=>(object)['deps'=>['jquery']]];
$scripts->queue=['wc-add-to-cart','woocommerce','sourcebuster-js','wc-order-attribution','jquery','jquery-core','jquery-migrate','joyrent-app'];
// Run only the asset cleanup callback, bypassing the unrelated Vite enqueue.
foreach ($hooks['wp_enqueue_scripts'][999] as $callback) $callback();
check($scripts->queue===['joyrent-app']&&$styles===['owner-style'],'Unused Woo/jQuery assets removed only on reading page');
check(filtered('woocommerce_enqueue_styles',['woocommerce-general'=>[]])===[],'Native reading page needs no Woo styles');
$scripts->queue=['jquery','jquery-core','jquery-migrate','owner-parent'];
foreach ($hooks['wp_enqueue_scripts'][999] as $callback) $callback();
check(in_array('jquery',$scripts->queue,true),'Transitive owner script dependency preserves jQuery');
foreach (['cart','checkout','account','product','shop'] as $woo) {
    $route='home'; $scripts->queue=['wc-add-to-cart','jquery'];
    foreach ($hooks['wp_enqueue_scripts'][999] as $callback) $callback();
    check($scripts->queue===['wc-add-to-cart','jquery']&&filtered('woocommerce_enqueue_styles',['woocommerce-general'=>[]])!==[],'Actual Woo routes retain scripts/styles: '.$woo);
}
$woo=''; $route='article';
check(filtered('woocommerce_enqueue_styles',['woocommerce-general'=>[]])!==[],'Other WP routes retain Woo styling');
echo json_encode(['status'=>'passed','assertions'=>$count],JSON_PRETTY_PRINT)."\n";
