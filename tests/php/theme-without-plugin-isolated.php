<?php
// Theme bootstrap and fallback templates without Rentals or WooCommerce.
define('ABSPATH',__DIR__.'/');
$hooks=[]; $route='home'; $options=['blog_public'=>'1']; $assertions=0; $enqueued=[]; $inline=[];
$actual_theme=dirname(__DIR__,2).'/wordpress/joyrent';
$template_directory=sys_get_temp_dir().'/joyrent-theme-without-plugin-'.bin2hex(random_bytes(6));
mkdir($template_directory.'/assets/dist/.vite',0777,true);
file_put_contents($template_directory.'/assets/dist/.vite/manifest.json',json_encode(['src/main.tsx'=>['file'=>'assets/app-hash.js','css'=>['assets/app-hash.css']]]));
register_shutdown_function(function () use ($template_directory): void {
    unlink($template_directory.'/assets/dist/.vite/manifest.json');
    foreach (['/assets/dist/.vite','/assets/dist','/assets',''] as $path) rmdir($template_directory.$path);
});
function add_action($name,$callback,$priority=10,$arguments=1): void { global $hooks; $hooks[$name][$priority][]=$callback; }
function add_filter($name,$callback,$priority=10,$arguments=1): void { add_action($name,$callback,$priority,$arguments); }
function remove_action(...$arguments): void {}
function callbacks($name): array { global $hooks; $groups=$hooks[$name] ?? []; ksort($groups); return array_merge(...array_values($groups)); }
function action($name,...$arguments): void { foreach (callbacks($name) as $callback) $callback(...$arguments); }
function filtered($name,$value,...$arguments): mixed { foreach (callbacks($name) as $callback) $value=$callback($value,...$arguments); return $value; }
function is_front_page(): bool { global $route; return $route==='home'; }
function is_page($slugs): bool { global $route; return in_array($route,(array)$slugs,true); }
function is_search(): bool { return false; }
function is_404(): bool { return false; }
function is_author(): bool { return false; }
function is_date(): bool { return false; }
function is_attachment(): bool { return false; }
function is_admin(): bool { return false; }
function get_option($name,$fallback=false): mixed { global $options; return $options[$name] ?? $fallback; }
function get_page_by_path($slug): mixed { return null; }
function get_post($id): mixed { return null; }
function get_queried_object(): mixed { return null; }
function get_queried_object_id(): int { return 0; }
function home_url($path): string { return 'https://example.test/subsite'.$path; }
function add_query_arg($key,$value,$url): string { return $url.'?'.$key.'='.$value; }
function get_template_directory(): string { global $template_directory; return $template_directory; }
function get_template_directory_uri(): string { return home_url('/theme'); }
function esc_attr($value): string { return htmlspecialchars((string)$value,ENT_QUOTES); }
function esc_html($value): string { return esc_attr($value); }
function esc_url($value): string { return esc_attr($value); }
function add_theme_support(...$arguments): void {}
function wp_enqueue_style($handle,...$arguments): void { global $enqueued; $enqueued[]=$handle; }
function wp_enqueue_script($handle,...$arguments): void { global $enqueued; $enqueued[]=$handle; }
function wp_add_inline_script($handle,$content,$position): void { global $inline; $inline[$handle]=$content; }
function rest_url($path): string { return home_url('/wp-json/'.$path); }
function is_user_logged_in(): bool { return false; }
function wp_json_encode($value,$flags=0): string { return json_encode($value,$flags); }
function language_attributes(): void { echo filtered('language_attributes','lang="en-US"'); }
function bloginfo($name): void { echo 'UTF-8'; }
function body_class(): void { echo 'class="home"'; }
function wp_head(): void { action('wp_head'); }
function wp_body_open(): void {}
function wp_footer(): void {}
function wp_date($format): string { return '2026'; }
function get_header(): void { global $actual_theme; require $actual_theme.'/header.php'; }
function get_footer(): void { global $actual_theme; require $actual_theme.'/footer.php'; }
function check(bool $condition,string $message): void { global $assertions; $assertions++; if (!$condition) throw new RuntimeException($message); }
require $actual_theme.'/functions.php';
check(!class_exists('JR_Settings')&&!class_exists('WooCommerce'),'No plugin classes present');
action('after_setup_theme'); action('wp_enqueue_scripts');
check(in_array('joyrent-app',$enqueued,true),'Home module still bootstraps');
$config=json_decode(substr($inline['joyrent-app'],strlen('window.JOYRENT = '),-1),true);
check($config['termsUrl']===''&&$config['termsRuUrl']===''&&$config['privacyUrl']===''&&$config['privacyRuUrl']==='','Absent legal pages are not advertised');
check($config['faqUrl']===home_url('/')&&$config['faqRuUrl']===home_url('/').'?lang=ru','Absent FAQ links use local language home fallbacks');
check($config['homeMetadata']['ru']['url']===home_url('/').'?lang=ru','Subdirectory canonical survives plugin-free bootstrap');
check(joyrent_noindex_request(),'Missing settings remain closed to indexing');
check(filtered('wp_robots',['nofollow'=>true])===['nofollow'=>true,'noindex'=>true],'Default robots restrictions survive');
check(filtered('wp_sitemaps_enabled',true)===false,'No default public sitemap without owner opt-in');
$options['joyrent_settings']=['search_indexing'=>true];
check(joyrent_search_indexing_enabled(),'Stored owner opt-in remains honored without plugin');
$options['blog_public']='0';
check(!joyrent_search_indexing_enabled(),'WordPress privacy switch wins over stored opt-in');
$options['blog_public']='1'; unset($options['joyrent_settings']);
foreach (['uk','ru'] as $language) {
    $_GET=['lang'=>$language]; $route='home';
    ob_start(); require $actual_theme.'/front-page.php'; $html=ob_get_clean();
    check(str_contains($html,'id="joyrent-root"')&&str_contains($html,'lang="'.$language.'"'),'Plugin-free landing has valid localized bootstrap: '.$language);
    check(str_contains($html,'<noscript>')&&str_contains($html,$language==='ru'?'Включи JavaScript':'Увімкни JavaScript'),'Plugin-free landing keeps localized no-script guidance: '.$language);
    $route='owner-page';
    ob_start(); require $actual_theme.'/footer.php'; $footer=ob_get_clean();
    check(str_contains($footer,'JOYRENT')&&str_contains($footer,'#booking'),'Native footer works with no plugin settings: '.$language);
}
echo json_encode(['status'=>'passed','assertions'=>$assertions],JSON_PRETTY_PRINT)."\n";
