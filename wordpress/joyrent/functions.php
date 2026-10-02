<?php
if (!defined('ABSPATH')) exit;
function joyrent_language(): string {
    return (isset($_GET['lang']) && $_GET['lang']==='ru') || is_page(['usloviya-arendy','konfidentsialnost']) ? 'ru' : 'uk';
}
function joyrent_home(string $anchor = ''): string {
    $url = joyrent_language()==='ru' ? add_query_arg('lang','ru',home_url('/')) : home_url('/');
    return $anchor ? $url.'#'.$anchor : $url;
}
add_filter('language_attributes', function (string $attributes): string {
    return is_front_page() || is_page(['usloviya-arendy','konfidentsialnost']) ? 'lang="'.joyrent_language().'" dir="ltr"' : $attributes;
});

add_action('after_setup_theme', function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('html5', ['search-form','gallery','caption','style','script']);
});
add_action('wp_enqueue_scripts', function (): void {
    $manifest_path = get_template_directory() . '/assets/dist/.vite/manifest.json';
    if (!file_exists($manifest_path)) return;
    $manifest = json_decode((string) file_get_contents($manifest_path), true);
    $entry = $manifest['src/main.tsx'] ?? null;
    if (!$entry) return;
    $base = get_template_directory_uri() . '/assets/dist/';
    foreach ($entry['css'] ?? [] as $index => $css) wp_enqueue_style('joyrent-' . $index, $base . $css, [], '1.1.0');
    if (!is_front_page()) return;
    wp_enqueue_script('joyrent-app', $base . $entry['file'], [], '1.1.0', true);
    $config = ['apiBase'=>rest_url('joyrent/v1'),'assetBase'=>get_template_directory_uri().'/assets','nonce'=>is_user_logged_in() ? wp_create_nonce('wp_rest') : '', 'privacyUrl'=>get_privacy_policy_url(), 'termsUrl'=>home_url('/umovy-orendy/'), 'privacyRuUrl'=>home_url('/konfidentsialnost/'), 'termsRuUrl'=>home_url('/usloviya-arendy/')];
    wp_add_inline_script('joyrent-app', 'window.JOYRENT = ' . wp_json_encode($config, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) . ';', 'before');
});
add_filter('script_loader_tag', function (string $tag, string $handle, string $src): string {
    if ($handle !== 'joyrent-app') return $tag;
    // Preserve WordPress's inline boot configuration before the module entry.
    return preg_replace('/<script\b(?=[^>]*\bsrc=)(?:\s+type=["\'][^"\']*["\'])?/', '<script type="module"', $tag);
}, 10, 3);
add_action('wp_head', function (): void {
    $description=joyrent_language()==='ru'?'JOYRENT — аренда PlayStation 5 и PlayStation 4. Выбирай консоль, даты и любимые игры.':'JOYRENT — оренда PlayStation 5 та PlayStation 4. Обирай консоль, дати та улюблені ігри.';
    echo '<meta name="theme-color" content="#08090b"><meta name="description" content="'.esc_attr($description).'">';
    if (is_front_page()) {
        echo '<link rel="alternate" hreflang="uk" href="'.esc_url(home_url('/')).'"><link rel="alternate" hreflang="ru" href="'.esc_url(add_query_arg('lang','ru',home_url('/'))).'"><link rel="alternate" hreflang="x-default" href="'.esc_url(home_url('/')).'">';
    }
    echo '<link rel="icon" type="image/webp" href="'.esc_url(get_template_directory_uri().'/assets/images/favicon.webp').'">';
});
add_filter('document_title_parts', function (array $parts): array {
    if (is_front_page()) $parts['title'] = joyrent_language()==='ru'?'JOYRENT — аренда PlayStation 5 и PlayStation 4':'JOYRENT — оренда PlayStation 5 та PlayStation 4';
    return $parts;
});
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
add_action('admin_notices', function (): void {
    if (!file_exists(get_template_directory().'/assets/dist/.vite/manifest.json') && current_user_can('manage_options')) echo '<div class="notice notice-error"><p>JOYRENT: встановіть готовий ZIP теми або виконайте npm run build у вихідному проєкті.</p></div>';
});
