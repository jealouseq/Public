<?php
// Real theme callbacks/templates, isolated from WordPress, mail, database and network.
require __DIR__.'/theme-public-pages-isolated.php';
$failures=[]; $audit_count=0;
function audit_check(bool $condition,string $message): void {
    global $audit_count,$failures; $audit_count++;
    if (!$condition) $failures[]=$message;
}
function esc_html($value): string { return htmlspecialchars((string)$value,ENT_QUOTES); }
$actual_theme=dirname(__DIR__,2).'/wordpress/joyrent';
$template_directory=sys_get_temp_dir().'/joyrent-theme-php-'.bin2hex(random_bytes(6));
mkdir($template_directory.'/assets/dist/.vite',0777,true);
file_put_contents($template_directory.'/assets/dist/.vite/manifest.json',json_encode(['src/main.tsx'=>['file'=>'assets/app-hash.js','css'=>['assets/app-hash.css']]]));
register_shutdown_function(function () use ($template_directory): void {
    unlink($template_directory.'/assets/dist/.vite/manifest.json');
    if (is_file($template_directory.'/assets/images/ps5-share.jpg')) unlink($template_directory.'/assets/images/ps5-share.jpg');
    foreach (['/assets/dist/.vite','/assets/dist','/assets/images','/assets',''] as $path) if (is_dir($template_directory.$path)) rmdir($template_directory.$path);
});
$route='home'; $woo=''; $search=false; $not_found=false; $archive=''; $_GET=[];
$plain_permalinks=true;
foreach ($hooks['wp_enqueue_scripts'][10] as $callback) $callback();
$config=json_decode(substr($inline_scripts['joyrent-app'],strlen('window.JOYRENT = '),-1),true);
foreach (['termsUrl'=>'umovy-orendy','termsRuUrl'=>'usloviya-arendy','privacyRuUrl'=>'konfidentsialnost','privacyUrl'=>'konfidentsiinist','faqUrl'=>'faq','faqRuUrl'=>'faq-ru'] as $key=>$slug) {
    audit_check($config[$key]===get_permalink($posts[$slug]),'Plain permalinks resolve published page: '.$key);
}
$home_metadata=$config['homeMetadata'] ?? [];
foreach (['uk','ru'] as $language) {
    $_GET=['lang'=>$language]; $metadata=joyrent_page_metadata(); $entry=$home_metadata[$language] ?? [];
    audit_check(($entry['title'] ?? '')===$metadata['title']&&($entry['description'] ?? '')===$metadata['description'],'Bootstrap home text matches server metadata: '.$language);
    audit_check(($entry['url'] ?? '')===$metadata['url']&&($entry['locale'] ?? '')===($language==='ru'?'ru_UA':'uk_UA'),'Bootstrap home canonical/locale match server metadata: '.$language);
    ob_start(); foreach ($hooks['wp_head'][5] as $callback) $callback(); $head=ob_get_clean();
    audit_check(isset($entry['title'],$entry['description'],$entry['url'])&&str_contains($head,'property="og:title" content="'.esc_attr($entry['title']).'"')&&str_contains($head,'name="description" content="'.esc_attr($entry['description']).'"')&&str_contains($head,'rel="canonical" href="'.esc_url($entry['url']).'"'),'Bootstrap home data matches emitted head exactly: '.$language);
}
$_GET=[];
$plain_permalinks=false;
foreach ($hooks['wp_enqueue_scripts'][10] as $callback) $callback();
$config=json_decode(substr($inline_scripts['joyrent-app'],strlen('window.JOYRENT = '),-1),true);
audit_check($config['termsUrl']===get_permalink($posts['umovy-orendy']),'Pretty legal permalink remains unchanged');
$options['wp_page_for_privacy_policy']=110;
foreach ($hooks['wp_enqueue_scripts'][10] as $callback) $callback();
$config=json_decode(substr($inline_scripts['joyrent-app'],strlen('window.JOYRENT = '),-1),true);
audit_check($config['privacyUrl']===get_permalink($posts['konfidentsiinist']),'Non-page cannot replace assigned privacy policy');
$options['wp_page_for_privacy_policy']=10;
foreach ($hooks['wp_enqueue_scripts'][10] as $callback) $callback();
$config=json_decode(substr($inline_scripts['joyrent-app'],strlen('window.JOYRENT = '),-1),true);
audit_check($config['privacyUrl']===get_permalink($posts['faq']),'Published owner-assigned privacy page is preserved');
unset($options['wp_page_for_privacy_policy']);
foreach (['umovy-orendy','usloviya-arendy','konfidentsialnost'] as $slug) $posts[$slug]->post_status='draft';
foreach ($hooks['wp_enqueue_scripts'][10] as $callback) $callback();
$config=json_decode(substr($inline_scripts['joyrent-app'],strlen('window.JOYRENT = '),-1),true);
foreach (['termsUrl','termsRuUrl','privacyRuUrl'] as $key) audit_check($config[$key]==='','Draft legal page is not linked: '.$key);
foreach (['umovy-orendy','usloviya-arendy','konfidentsialnost'] as $slug) $posts[$slug]->post_status='publish';
$posts['faq-ru']->post_status='draft';
audit_check(joyrent_faq_url('ru')===get_permalink($posts['faq']),'Draft FAQ falls back to a published translation');
$posts['faq']->post_status='draft';
audit_check(joyrent_faq_url('ru')===joyrent_public_page_url('home','ru'),'No published FAQ falls back to requested-language home');
audit_check(joyrent_faq_url('uk')===joyrent_public_page_url('home','uk'),'UA FAQ home fallback is published-safe');
$posts['faq']->post_status='publish'; $route='faq';
ob_start(); require $actual_theme.'/header.php'; $header=ob_get_clean();
audit_check(!str_contains($header,'<a lang="ru"'),'Draft FAQ translation is omitted from the language switch');
audit_check(str_contains($header,'<a lang="uk"'),'Published FAQ translation stays in the language switch');
$route='home';
$posts['faq']->post_status='publish'; $posts['faq-ru']->post_status='publish';
audit_check(joyrent_faq_url('ru')===get_permalink($posts['faq-ru']),'Published RU FAQ URL remains unchanged');
JR_Settings::$indexing=true; $search=true;
$headers=filtered('wp_headers',['X-Robots-Tag'=>'noindex, nofollow, noarchive']);
audit_check($headers['X-Robots-Tag']==='noindex, nofollow, noarchive','Existing crawler restrictions remain intact');
$headers=filtered('wp_headers',['x-robots-tag'=>'nofollow']);
$robot_headers=array_filter($headers,fn($name)=>strtolower($name)==='x-robots-tag',ARRAY_FILTER_USE_KEY);
audit_check(count($robot_headers)===1,'Robots header keys merge without case duplicates');
audit_check(str_contains(implode(', ',$robot_headers),'nofollow')&&str_contains(implode(', ',$robot_headers),'noindex'),'Lowercase crawler restrictions survive noindex addition');
$headers=filtered('wp_headers',['X-Robots-Tag'=>'googlebot: nofollow','x-robots-tag'=>'noarchive']);
$robot_headers=array_filter($headers,fn($name)=>strtolower($name)==='x-robots-tag',ARRAY_FILTER_USE_KEY);
audit_check(count($robot_headers)===1&&str_contains(implode(', ',$robot_headers),'googlebot: nofollow')&&str_contains(implode(', ',$robot_headers),'noarchive')&&str_contains(implode(', ',$robot_headers),'noindex'),'Case duplicate and bot-specific directives are all retained');
$generic_part=explode('googlebot:',implode(', ',$robot_headers))[0];
audit_check(str_contains($generic_part,'noindex')&&str_contains($generic_part,'noarchive'),'Global directives remain outside an existing bot-specific scope');
$headers=filtered('wp_headers',['X-Robots-Tag'=>'googlebot: noarchive, noindex']);
audit_check(str_starts_with($headers['X-Robots-Tag'],'noindex, '),'Bot-scoped noindex cannot replace the global noindex policy');
$headers=filtered('wp_headers',['X-Robots-Tag'=>'max-snippet: 0, googlebot: noarchive, noindex','x-robots-tag'=>'unavailable_after: 25 Jun 2030 15:00:00 PST']);
$generic_part=explode('googlebot:',$headers['X-Robots-Tag'])[0];
audit_check(str_contains($generic_part,'noindex')&&str_contains($generic_part,'max-snippet: 0')&&str_contains($generic_part,'unavailable_after: 25 Jun 2030 15:00:00 PST'),'Parameterized global directives retain their scope and date text');
$search=false;
audit_check(filtered('wp_headers',['X-Robots-Tag'=>'nofollow'])['X-Robots-Tag']==='nofollow','Indexable pages retain owner robots header unchanged');
// Validate published Open Graph dimensions against the actual existing image.
$template_directory_actual=$template_directory; $template_directory=$actual_theme; $route='faq'; $_GET=[];
ob_start(); foreach ($hooks['wp_head'][5] as $callback) $callback(); $head=ob_get_clean();
[$width,$height]=getimagesize($actual_theme.'/assets/images/ps5-share.jpg');
audit_check(str_contains($head,'property="og:image:width" content="'.$width.'"')&&str_contains($head,'property="og:image:height" content="'.$height.'"'),'Open Graph dimensions match the existing JPEG');
$template_directory=$template_directory_actual;
mkdir($template_directory.'/assets/images',0777,true);
// A valid 1x1 PNG makes sure metadata follows dimensions rather than a constant.
file_put_contents($template_directory.'/assets/images/ps5-share.jpg',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aXcAAAAASUVORK5CYII='));
ob_start(); foreach ($hooks['wp_head'][5] as $callback) $callback(); $head=ob_get_clean();
audit_check(str_contains($head,'property="og:image:width" content="1"')&&str_contains($head,'property="og:image:height" content="1"'),'Open Graph dimensions track regenerated artwork');
$native_ids=[]; $native_index=0; $native_titles=[110=>'Owner article',102=>'Owner second article']; $native_bodies=[110=>'<p>Owner content stays intact.</p>',102=>'<p>Second owner content.</p>'];
$singular=false; $pagination=[]; $search_query='';
function language_attributes(): void { echo 'lang="uk"'; }
function bloginfo($name): void { echo 'UTF-8'; }
function wp_head(): void {}
function body_class(): void { echo 'class="native"'; }
function wp_body_open(): void {}
function get_header(): void {}
function get_footer(): void {}
function have_posts(): bool { global $native_ids,$native_index; return $native_index<count($native_ids); }
function the_post(): void { global $native_index; $native_index++; }
function get_the_ID(): int { global $native_ids,$native_index; return $native_ids[$native_index-1]; }
function the_title(): void { global $native_titles; echo $native_titles[get_the_ID()]; }
function the_content(): void { global $native_bodies; echo $native_bodies[get_the_ID()]; }
function post_class(): void { echo 'class="owner-post"'; }
function is_singular(): bool { global $singular; return $singular; }
function the_archive_title($before='',$after=''): void { echo $before.'Owner archive title'.$after; }
function get_search_query($escaped=true): string { global $search_query; return $escaped?esc_attr($search_query):$search_query; }
function the_posts_pagination($args=[]): void { global $pagination; $pagination[]=$args; echo '<nav class="navigation pagination"><a href="?paged=2">2</a></nav>'; }
function native_html(string $template): string { global $native_index,$pagination; $native_index=0; $pagination=[]; ob_start(); require $template; return ob_get_clean(); }
$index=$actual_theme.'/index.php';
$route='missing'; $not_found=true; $search=false; $_GET=['lang'=>'uk'];
$html=native_html($index);
audit_check(str_contains(strip_tags($html),'Сторінку не знайдено'),'404 has a visible UA explanation');
audit_check(str_contains($html,'href="'.esc_url(joyrent_home()).'"'),'404 has a working home action');
$_GET=['lang'=>'ru']; $html=native_html($index);
audit_check(str_contains(strip_tags($html),'Страница не найдена'),'404 has a visible RU explanation');
$not_found=false; $search=true; $route='search'; $search_query='"><script>alert(1)</script>'; $_GET=['lang'=>'uk'];
$html=native_html($index);
audit_check(str_contains(strip_tags($html),'Нічого не знайдено'),'Empty search explains the missing results');
audit_check(str_contains($html,esc_html($search_query))&&!str_contains($html,'<script>'),'Search query is escaped in its visible heading');
$_GET=['lang'=>'ru']; $html=native_html($index);
audit_check(str_contains(strip_tags($html),'Ничего не найдено'),'Empty search has a RU explanation');
$search=false; $route='article'; $native_ids=[110,102]; $singular=false; $_GET=['lang'=>'uk'];
$html=native_html($index);
foreach ($native_ids as $id) audit_check(str_contains($html,'href="'.esc_url(get_permalink($id)).'"'),'Archive title links to owner post: '.$id);
audit_check(substr_count($html,'<h2')===2,'Archive post headings use a listing hierarchy');
audit_check(str_contains($html,'<h1>Owner archive title</h1>'),'Archive retains its primary listing heading');
audit_check(count($pagination)===1&&str_contains($html,'?paged=2'),'Archive exposes native pagination');
audit_check(str_contains($html,$native_bodies[110])&&str_contains($html,$native_bodies[102]),'Archive preserves owner content');
$singular=true; $native_ids=[110]; $html=native_html($index);
audit_check(substr_count($html,'<h1')===1&&substr_count($html,'<h2')===0,'Singular owner page keeps its primary heading');
audit_check($pagination===[]&&str_contains($html,$native_bodies[110]),'Singular content remains intact without archive navigation');
echo json_encode(['status'=>$failures?'failed':'passed','assertions'=>$audit_count,'failures'=>$failures],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
