<?php
if (!defined('ABSPATH')) { exit; }

require_once get_template_directory() . '/inc/localization.php';
require_once get_template_directory() . '/inc/gallery.php';
require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/content.php';

// Тексты сайта русские; язык админки определяет владелец.
add_filter('locale', function ($locale) { return is_admin() ? $locale : 'ru_RU'; });
add_filter('woocommerce_date_format', function () { return 'd.m.Y'; });

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('html5', array('search-form', 'gallery', 'caption', 'style', 'script'));
    register_nav_menus(array('primary' => 'Главное меню'));
});

/* --------------------------------------------------------------- <head> */

// Убираем лишнее из <head>: эмодзи-скрипт, oEmbed, версию WordPress.
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_shortlink_wp_head');
});

add_action('wp_head', function () {
    $s = zh_settings();
    $title = wp_get_document_title();
    $default = 'ZHIBEK — питомник абиссинских, бенгальских и сиамских кошек в Казахстане. Котята с родословной, ветпаспортом и чипом, доставка по стране.';
    $description = is_singular() && !post_password_required() ? wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 28) : $default;
    if (!$description) { $description = $default; }
    $featured = is_singular() && !post_password_required() ? zh_public_thumbnail_id(get_post()) : 0;
    $image = $featured ? wp_get_attachment_image_url($featured, 'large') : zh_img('ui/og.jpg');
    echo '<meta name="theme-color" content="#f3ece0"><meta name="color-scheme" content="light">';
    echo '<link rel="preload" href="' . esc_url(zh_asset('fonts/Onest.woff2')) . '" as="font" type="font/woff2" crossorigin>';
    if (is_front_page()) { echo '<link rel="preload" as="image" href="' . esc_url(zh_img('scenes/trio-960.webp')) . '" imagesrcset="' . esc_attr(zh_scene_srcset('trio')) . '" imagesizes="(min-width: 960px) 46vw, 92vw" fetchpriority="high">'; }
    echo '<meta name="description" content="' . esc_attr($description) . '">';
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url(zh_img('ui/favicon.svg')) . '">';
    echo '<link rel="apple-touch-icon" href="' . esc_url(zh_img('ui/apple-touch-icon.png')) . '">';
    echo '<meta property="og:site_name" content="ZHIBEK"><meta property="og:locale" content="ru_RU"><meta property="og:title" content="' . esc_attr($title) . '"><meta property="og:description" content="' . esc_attr($description) . '"><meta property="og:image" content="' . esc_url($image) . '"><meta property="og:type" content="' . (is_singular('post') ? 'article' : 'website') . '"><meta name="twitter:card" content="summary_large_image">';
    // Фон до загрузки стилей и страховка: если скрипт не загрузится, блоки с появлением всё равно станут видимыми.
    echo '<style id="zh-critical">html{background:#f3ece0}</style>';
    echo '<script>(function(d,w){var e=d.documentElement;e.classList.add("js");w.__zhT=setTimeout(function(){e.classList.add("reveal-fail")},4500)})(document,window)</script>';
    if (is_front_page()) {
        $same = array_values(array_filter(array($s['instagram'] ?? '')));
        echo '<script type="application/ld+json">' . wp_json_encode(array(
            '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'ZHIBEK', 'url' => home_url('/'),
            'description' => $default, 'logo' => zh_img('ui/favicon.svg'), 'areaServed' => 'KZ', 'sameAs' => $same,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
}, 1);

add_action('admin_notices', function () {
    if (current_user_can('activate_plugins') && !function_exists('zhibek_get_breeds')) {
        echo '<div class="notice notice-info"><p>Для полного сайта ZHIBEK активируйте плагин «ZHIBEK Cattery». Демо-контент создаётся в Настройки → ZHIBEK.</p></div>';
    }
});

/* ------------------------------------------------------- стили и скрипты */

add_action('wp_enqueue_scripts', function () {
    $u = get_template_directory_uri();
    $d = get_template_directory();
    $v = function ($rel) use ($d) { $f = $d . '/assets/' . $rel; return is_file($f) ? (string) filemtime($f) : '4.0.0'; };
    $css = array('base', 'home', 'inner');
    if (class_exists('WooCommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) { $css[] = 'shop'; }
    $prev = array();
    foreach ($css as $name) {
        wp_enqueue_style('zh-' . $name, $u . '/assets/css/' . $name . '.css', $prev, $v('css/' . $name . '.css'));
        $prev = array('zh-' . $name);
    }
    // Служебные стили блоков WordPress на страницах темы не нужны.
    if (is_front_page() || is_singular(array('zhibek_breed', 'zhibek_kitten')) || is_post_type_archive(array('zhibek_breed', 'zhibek_kitten'))) {
        wp_dequeue_style('wp-block-library'); wp_dequeue_style('classic-theme-styles'); wp_dequeue_style('global-styles');
    }
    wp_enqueue_script('zh-site', $u . '/assets/js/site.js', array(), $v('js/site.js'), array('in_footer' => true, 'strategy' => 'defer'));
    if (class_exists('WooCommerce')) {
        wp_enqueue_script('wc-cart-fragments');
        if (is_front_page()) { wp_enqueue_script('wc-add-to-cart'); }
    }
    $contacts = zh_contacts();
    $fallback = isset($contacts['whatsapp']) ? $contacts['whatsapp'][1] : (isset($contacts['telegram']) ? $contacts['telegram'][1] : (isset($contacts['email']) ? $contacts['email'][1] : (isset($contacts['phone']) ? $contacts['phone'][1] : home_url('/'))));
    wp_localize_script('zh-site', 'ZHIBEK', array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('zh_enquiry'), 'contact' => $fallback));
});

/* ---------------------------------------------------------------- запросы */

// Заголовок вкладки: на главной — полное название питомника, на остальных — «Страница — ZHIBEK».
add_filter('document_title_separator', function () { return '—'; });
add_filter('pre_get_document_title', function ($title) {
    return is_front_page() ? 'ZHIBEK — питомник абиссинских, бенгальских и сиамских кошек в Казахстане' : $title;
});

// Закрытые паролем записи не попадают в публичный каталог.
add_action('pre_get_posts', function ($query) {
    if (is_admin() || (defined('WP_CLI') && WP_CLI) || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || $query->is_singular()) { return; }
    $types = (array) $query->get('post_type');
    if (array_intersect($types, array('zhibek_breed', 'zhibek_kitten'))) { $query->set('has_password', false); }
    if ($query->is_main_query() && !is_admin() && $query->is_post_type_archive('zhibek_kitten')) {
        $query->set('posts_per_page', -1);
        if (!zh_is_demo()) { $query->set('meta_query', array(array('key' => '_zhibek_demo', 'compare' => 'NOT EXISTS'))); }
    }
});

// Неразрывные пробелы в тексте записей и страниц (только на сайте, не в админке и не в REST).
add_filter('the_content', function ($html) {
    return (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) ? $html : zh_nbsp_html($html);
}, 30);

add_filter('body_class', function ($classes) {
    $classes[] = 'zh';
    return $classes;
});

/* --------------------------------------------------------------- навигация */

function zh_default_navigation($args = array()) {
    $id = is_array($args) ? ($args['menu_id'] ?? 'primary-menu') : ($args->menu_id ?? 'primary-menu');
    $links = array(array(home_url('/breeds/'), 'Породы'), array(home_url('/kittens/'), 'Котята'), array(home_url('/#process'), 'Как забрать котёнка'), array(home_url('/#faq'), 'Вопросы'), array(home_url('/#journal'), 'Журнал'));
    if (class_exists('WooCommerce')) { $links[] = array(zh_wc_url('shop'), 'Магазин'); }
    echo '<ul class="nav-items" id="' . esc_attr($id) . '">';
    foreach ($links as $i => $l) { echo '<li style="--i:' . (int) $i . '"><a href="' . esc_url($l[0]) . '">' . esc_html($l[1]) . '</a></li>'; }
    echo '</ul>';
}

function zh_navigation($id) {
    wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'menu_class' => 'nav-items', 'menu_id' => $id, 'depth' => 1, 'fallback_cb' => 'zh_default_navigation'));
}

/* ------------------------------------------------------------- WooCommerce */

add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    $n = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
    $fragments['span.cart-count'] = '<span class="cart-count" data-n="' . $n . '">' . $n . '</span>';
    return $fragments;
});
add_filter('woocommerce_currency_symbol', function ($symbol, $currency) { return $currency === 'KZT' ? '₸' : $symbol; }, 10, 2);
add_filter('woocommerce_breadcrumb_defaults', function ($d) { $d['home'] = 'Главная'; $d['delimiter'] = ' / '; return $d; });

/** Картинка товара: загруженная в WooCommerce или файл-заглушка из темы. */
function zh_product_image($product) {
    if ($product->get_image_id()) { echo wp_get_attachment_image($product->get_image_id(), 'woocommerce_thumbnail', false, array('loading' => 'lazy')); return; }
    $file = basename((string) get_post_meta($product->get_id(), '_zhibek_image', true));
    if ($file && is_file(get_template_directory() . '/assets/img/products/' . $file)) {
        echo '<img src="' . esc_url(zh_img('products/' . $file)) . '" alt="' . esc_attr($product->get_name()) . '" loading="lazy" decoding="async">';
        return;
    }
    echo wc_placeholder_img('woocommerce_thumbnail');
}
add_filter('woocommerce_product_get_image', function ($image, $product) {
    if ($product->get_image_id()) { return $image; }
    ob_start(); zh_product_image($product); return ob_get_clean();
}, 10, 2);
add_filter('woocommerce_single_product_image_thumbnail_html', function ($html, $attachment_id) {
    global $product;
    if (!$product || $attachment_id || $product->get_image_id() || !get_post_meta($product->get_id(), '_zhibek_demo', true)) { return $html; }
    ob_start(); echo '<div class="woocommerce-product-gallery__image">'; zh_product_image($product); echo '</div>'; return ob_get_clean();
}, 10, 2);

remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
add_filter('woocommerce_show_page_title', '__return_false');
add_action('woocommerce_before_main_content', function () {
    echo '<main id="main" class="shop-main">';
    if (!is_product()) {
        $title = is_shop() ? 'Магазин' : woocommerce_page_title(false);
        echo '<header class="page-head"><div class="wrap"><span class="eyebrow">Для дома</span><h1 class="h1">' . esc_html($title) . '</h1>' . (is_shop() ? '<p class="lead">Вещи для кошки и дома.</p>' : '') . '</div></header>';
    }
    echo '<div class="wrap shop-wrap">';
}, 10);
add_action('woocommerce_after_main_content', function () { echo '</div></main>'; }, 10);
