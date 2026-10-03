<?php
if (!defined('ABSPATH')) exit;
function joyrent_language(): string {
    if (is_page(['faq','umovy-orendy','konfidentsiinist'])) return 'uk';
    if (is_page(['faq-ru','usloviya-arendy','konfidentsialnost'])) return 'ru';
    return (isset($_GET['lang']) && $_GET['lang']==='ru') || is_page(['usloviya-arendy','konfidentsialnost','faq-ru']) ? 'ru' : 'uk';
}
function joyrent_home(string $anchor = ''): string {
    $url = joyrent_language()==='ru' ? add_query_arg('lang','ru',home_url('/')) : home_url('/');
    return $anchor ? $url.'#'.$anchor : $url;
}
function joyrent_faq_url(string $language): string {
    $page=get_page_by_path($language==='ru'?'faq-ru':'faq');
    return $page ? get_permalink($page) : home_url($language==='ru'?'/faq-ru/':'/faq/');
}
function joyrent_privacy_url(): string {
    $assigned_id=(int)get_option('wp_page_for_privacy_policy');
    $assigned=$assigned_id>0?get_post($assigned_id):null;
    if ($assigned&&$assigned->post_type==='page'&&$assigned->post_status==='publish') return get_permalink($assigned);
    $fallback=get_page_by_path('konfidentsiinist');
    return $fallback&&$fallback->post_status==='publish'?get_permalink($fallback):'';
}
add_filter('language_attributes', function (string $attributes): string {
    return is_front_page() || is_page(['usloviya-arendy','konfidentsialnost','umovy-orendy','konfidentsiinist','faq','faq-ru']) ? 'lang="'.joyrent_language().'" dir="ltr"' : $attributes;
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
    foreach ($entry['css'] ?? [] as $index => $css) wp_enqueue_style('joyrent-' . $index, $base . $css, [], null);
    if (!is_front_page()) return;
    // Hashed filenames handle cache busting. A query would give lazy chunks a
    // second URL for the entry module and execute its React bootstrap again.
    wp_enqueue_script('joyrent-app', $base . $entry['file'], [], null, true);
    $config = ['apiBase'=>rest_url('joyrent/v1'),'assetBase'=>get_template_directory_uri().'/assets','nonce'=>is_user_logged_in() ? wp_create_nonce('wp_rest') : '', 'privacyUrl'=>joyrent_privacy_url(), 'termsUrl'=>home_url('/umovy-orendy/'), 'privacyRuUrl'=>home_url('/konfidentsialnost/'), 'termsRuUrl'=>home_url('/usloviya-arendy/'), 'faqUrl'=>joyrent_faq_url('uk'), 'faqRuUrl'=>joyrent_faq_url('ru')];
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
    if (is_page(['faq','faq-ru'])) echo '<link rel="alternate" hreflang="uk" href="'.esc_url(joyrent_faq_url('uk')).'"><link rel="alternate" hreflang="ru" href="'.esc_url(joyrent_faq_url('ru')).'">';
    if (is_front_page()) {
        echo '<link rel="alternate" hreflang="uk" href="'.esc_url(home_url('/')).'"><link rel="alternate" hreflang="ru" href="'.esc_url(add_query_arg('lang','ru',home_url('/'))).'"><link rel="alternate" hreflang="x-default" href="'.esc_url(home_url('/')).'">';
        // Match the React picture exactly: preload only the active viewport's image.
        $media_path=get_template_directory().'/assets/images/hero-media.json';
        $media=is_readable($media_path)?json_decode((string)file_get_contents($media_path),true):null;
        $image_base=get_template_directory_uri().'/assets/images/';
        foreach (['desktop','mobile'] as $mode) {
            if (empty($media[$mode]['sources'])) continue;
            $image=$media[$mode];
            $sources=array_map(fn(array $source): string => $image_base.$source['file'].' '.$source['width'].'w',$image['sources']);
            echo '<link rel="preload" as="image" href="'.esc_url($image_base.$image['fallback']).'" imagesrcset="'.esc_attr(implode(', ',$sources)).'" imagesizes="'.esc_attr($image['sizes']).'" media="'.esc_attr($media[$mode.'Media']).'" fetchpriority="high">';
        }
        // Latin + Cyrillic are both used in the headline; early fonts prevent reflow.
        $manifest_path=get_template_directory().'/assets/dist/.vite/manifest.json';
        $manifest=is_readable($manifest_path)?json_decode((string)file_get_contents($manifest_path),true):[];
        foreach (['latin','cyrillic'] as $script) {
            $key='node_modules/@fontsource-variable/unbounded/files/unbounded-'.$script.'-wght-normal.woff2';
            if (!empty($manifest[$key]['file'])) echo '<link rel="preload" as="font" type="font/woff2" crossorigin href="'.esc_url(get_template_directory_uri().'/assets/dist/'.$manifest[$key]['file']).'">';
        }
    }
    echo '<link rel="icon" type="image/webp" href="'.esc_url(get_template_directory_uri().'/assets/images/favicon.webp').'">';
});
add_filter('document_title_parts', function (array $parts): array {
    if (is_front_page()) $parts['title'] = joyrent_language()==='ru'?'JOYRENT — аренда PlayStation 5 и PlayStation 4':'JOYRENT — оренда PlayStation 5 та PlayStation 4';
    return $parts;
});
add_filter('woocommerce_enqueue_styles', '__return_empty_array');
// The rental landing uses its own REST flow and has no WooCommerce cart widgets.
// Keep WooCommerce assets on checkout/account/shop routes and for other consumers.
add_action('wp_enqueue_scripts', function (): void {
    if (!class_exists('WooCommerce') || !is_front_page() || is_cart() || is_checkout() || is_account_page()) return;
    foreach (['wc-add-to-cart', 'woocommerce', 'wc-cart-fragments', 'wc-order-attribution', 'sourcebuster-js', 'wc-jquery-blockui', 'wc-js-cookie'] as $handle) wp_dequeue_script($handle);
}, 99);
add_action('admin_notices', function (): void {
    if (!file_exists(get_template_directory().'/assets/dist/.vite/manifest.json') && current_user_can('manage_options')) echo '<div class="notice notice-error"><p>JOYRENT: встановіть готовий ZIP теми або виконайте npm run build у вихідному проєкті.</p></div>';
});
