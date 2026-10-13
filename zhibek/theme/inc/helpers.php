<?php
/** Вспомогательные функции шаблонов: ассеты, иконки, данные пород и котят, карточки. */
if (!defined('ABSPATH')) { exit; }

function zh_asset($path) { return get_template_directory_uri() . '/assets/' . ltrim($path, '/'); }
function zh_img($path) { return zh_asset('img/' . ltrim($path, '/')); }

function zh_settings() {
    $defaults = array('demo' => true, 'phone' => '', 'whatsapp' => '', 'telegram' => '', 'instagram' => '', 'email' => '', 'city' => 'Казахстан', 'cities' => '');
    return function_exists('zhibek_get_settings') ? array_merge($defaults, zhibek_get_settings()) : $defaults;
}

function zh_is_demo() { return function_exists('zhibek_is_demo') ? zhibek_is_demo() : true; }
function zh_breeds() { return function_exists('zhibek_get_breeds') ? zhibek_get_breeds() : array(); }
function zh_kittens($args = array()) { return function_exists('zhibek_get_kittens') ? zhibek_get_kittens($args) : array(); }

function zh_wc_url($page) {
    return function_exists('wc_get_page_permalink') ? wc_get_page_permalink($page) : home_url('/' . ($page === 'myaccount' ? 'my-account' : $page) . '/');
}

/** Нижний регистр и строчная первая буква без зависимости от mbstring. */
function zh_lower($s) { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }
function zh_lcfirst($s) {
    if (!function_exists('mb_substr')) { return $s; }
    return mb_strtolower(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
}

function zh_privacy_url() { return get_privacy_policy_url() ?: home_url('/privacy/'); }

/** Ссылки на мессенджеры и соцсети, которые владелец указал в настройках. */
function zh_contacts() {
    $s = zh_settings();
    $out = array();
    if (!empty($s['whatsapp'])) { $out['whatsapp'] = array('WhatsApp', 'https://wa.me/' . $s['whatsapp'] . '?text=' . rawurlencode('Здравствуйте! Хочу узнать о котятах ZHIBEK.')); }
    if (!empty($s['telegram'])) { $out['telegram'] = array('Telegram', 'https://t.me/' . $s['telegram']); }
    if (!empty($s['instagram'])) { $out['instagram'] = array('Instagram', $s['instagram']); }
    if (!empty($s['phone'])) { $out['phone'] = array($s['phone'], 'tel:' . preg_replace('/[^+0-9]/', '', $s['phone'])); }
    if (!empty($s['email'])) { $out['email'] = array($s['email'], 'mailto:' . $s['email']); }
    return $out;
}

/* ------------------------------------------------------------------ иконки */

function zh_arrow($class = '') {
    return '<svg class="arr ' . esc_attr($class) . '" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12h15M13 6l6 6-6 6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function zh_icon($name) {
    $paths = array(
        'bag' => '<path d="M5 8h14l1 13H4L5 8zM8 8V6a4 4 0 018 0v2"/>',
        'menu' => '<path d="M3 8h18M3 16h18"/>',
        'close' => '<path d="M5 5l14 14M19 5L5 19"/>',
        'heart' => '<path d="M12 20S3 14.5 3 8.5a4.5 4.5 0 019-1 4.5 4.5 0 019 1c0 6-9 11.5-9 11.5z"/>',
        'whatsapp' => '<path d="M3.5 20.5l1.3-4.6A8.5 8.5 0 1112 20.5a8.4 8.4 0 01-4-1z"/><path d="M9 8.5c.3 3 2.5 5.2 5.5 6.5l1.5-1.5-2-1-1 .8c-.9-.4-1.8-1.3-2.2-2.2l.8-1-1-2z"/>',
        'telegram' => '<path d="M21 4L3 11l5.5 2L10 19l3-3.5L17.5 19 21 4z"/><path d="M8.5 13L21 4"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r=".6"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'doc' => '<path d="M6 3h8l4 4v14H6V3z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'chip' => '<rect x="7" y="7" width="10" height="10" rx="1.5"/><path d="M10 3v4M14 3v4M10 17v4M14 17v4M3 10h4M3 14h4M17 10h4M17 14h4"/>',
        'contract' => '<path d="M6 3h9l3 3v15H6V3z"/><path d="M9 9h6M9 13h6M9 17h3"/>',
        'gift' => '<rect x="4" y="9" width="16" height="11" rx="1.5"/><path d="M3 9h18v3H3zM12 9v11M12 9c-3 0-5-1-5-3a2 2 0 014 0c0 1 1 3 1 3zm0 0c3 0 5-1 5-3a2 2 0 00-4 0c0 1-1 3-1 3z"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4V5z"/><path d="M8 9.5h8M8 12.5h5"/>',
        'sound' => '<path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16.5 8.5a5 5 0 010 7"/>',
        'pin' => '<path d="M12 21s7-6 7-11.5A7 7 0 005 9.5C5 15 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
    );
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Знак: радужка с вертикальным зрачком. */
function zh_mark($class = 'mark') {
    return '<svg class="' . esc_attr($class) . '" viewBox="0 0 48 48" fill="none" aria-hidden="true"><circle cx="24" cy="24" r="22.5" stroke="currentColor" stroke-width="1.1"/><circle cx="24" cy="24" r="16.5" stroke="currentColor" stroke-opacity=".45" stroke-width=".9"/><path d="M24 9.5c5.6 5.6 5.6 23.4 0 29-5.6-5.6-5.6-23.4 0-29z" fill="currentColor"/></svg>';
}

/** Щель-зрачок, заменяющая букву I в слове ZHIBEK. */
function zh_pupil_i() {
    return '<svg class="wm-i" viewBox="0 0 10 60" aria-hidden="true"><path d="M5 0c6 18 6 42 0 60C-1 42-1 18 5 0z" fill="currentColor"/></svg>';
}

/** Название питомника: ZH + зрачок + BEK. */
function zh_wordmark($class = 'wm') {
    return '<span class="' . esc_attr($class) . '" role="img" aria-label="ZHIBEK"><span aria-hidden="true">ZH</span>' . zh_pupil_i() . '<span aria-hidden="true">BEK</span></span>';
}

function zh_brand() { return zh_mark('mark') . zh_wordmark('wm'); }

/* ------------------------------------------------------------ породы/котята */

/** Оформление «мира» породы: цвета и тип декоративного слоя. Для новых пород берутся цвета из полей админки. */
function zh_breed_world($post) {
    $post = get_post($post);
    $slug = $post ? $post->post_name : '';
    $worlds = array(
        'abyssinian' => array('kind' => 'sun', 'accent' => '#d28c45', 'glow' => '#f7b960', 'bg1' => '#2a140a', 'bg2' => '#7a431b', 'eye' => '#e0a63a'),
        'bengal' => array('kind' => 'wild', 'accent' => '#c8962f', 'glow' => '#e0b04a', 'bg1' => '#0c0803', 'bg2' => '#2a1c07', 'eye' => '#a6cb5e'),
        'siamese' => array('kind' => 'moon', 'accent' => '#9fb6dd', 'glow' => '#cfdcf5', 'bg1' => '#070d1d', 'bg2' => '#1b3158', 'eye' => '#5a98e0'),
    );
    $w = $worlds[$slug] ?? array('kind' => 'soft', 'accent' => '#d4b06a', 'glow' => '#f1dba5', 'bg1' => '#0d0b09', 'bg2' => '#2b2216', 'eye' => '#d4b06a');
    if ($post) {
        $accent = get_post_meta($post->ID, '_zhibek_accent', true);
        $eye = get_post_meta($post->ID, '_zhibek_eye', true);
        if ($accent) { $w['accent'] = $accent; }
        if ($eye) { $w['eye'] = $eye; }
    }
    return $w;
}

/** Факты породы: строки «Название: значение» → [[название, значение], …]. */
function zh_breed_facts($post) {
    $raw = (string) get_post_meta(get_post($post)->ID, '_zhibek_facts', true);
    $out = array();
    foreach (preg_split('/\R+/u', $raw) as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $parts = explode(':', $line, 2);
        $out[] = count($parts) === 2 ? array(trim($parts[0]), trim($parts[1])) : array('', $line);
    }
    return $out;
}

function zh_traits($post) {
    return array_values(array_filter(array_map('trim', explode(',', (string) get_post_meta(get_post($post)->ID, '_zhibek_traits', true)))));
}

function zh_public_breed($id) {
    $post = $id ? get_post(absint($id)) : null;
    return $post && $post->post_type === 'zhibek_breed' && $post->post_status === 'publish' && !$post->post_password ? $post : null;
}

function zh_public_thumbnail_id($post) {
    $id = get_post_thumbnail_id($post);
    return $id && function_exists('zh_gallery_attachment_is_public') && zh_gallery_attachment_is_public($id) ? $id : 0;
}

/**
 * Основное изображение записи.
 * type=cutout — вырезанный кот на прозрачном фоне (файлы темы или загруженный PNG/WebP с отметкой), type=photo — обычная фотография.
 */
function zh_media($post, $size = 'large') {
    $post = get_post($post);
    $thumb = zh_public_thumbnail_id($post);
    if ($thumb) {
        $src = wp_get_attachment_image_src($thumb, $size);
        if ($src) {
            $cut = (bool) get_post_meta($post->ID, '_zhibek_cutout', true);
            return array('url' => $src[0], 'w' => $src[1], 'h' => $src[2], 'type' => $cut ? 'cutout' : 'photo', 'id' => $thumb);
        }
    }
    $file = basename((string) get_post_meta($post->ID, '_zhibek_image', true));
    if ($file && is_file(get_template_directory() . '/assets/img/cats/' . $file)) {
        $size_info = @getimagesize(get_template_directory() . '/assets/img/cats/' . $file);
        return array('url' => zh_img('cats/' . $file), 'w' => $size_info ? $size_info[0] : 0, 'h' => $size_info ? $size_info[1] : 0, 'type' => 'cutout', 'id' => 0);
    }
    return array('url' => '', 'w' => 0, 'h' => 0, 'type' => 'none', 'id' => 0);
}

function zh_media_img($post, $class = '', $size = 'large', $lazy = true) {
    $m = zh_media($post, $size);
    if (!$m['url']) { return; }
    $attrs = ' class="' . esc_attr($class) . '" src="' . esc_url($m['url']) . '" alt="' . esc_attr(get_the_title($post)) . '"';
    if ($m['w'] && $m['h']) { $attrs .= ' width="' . (int) $m['w'] . '" height="' . (int) $m['h'] . '"'; }
    $attrs .= ($lazy ? ' loading="lazy"' : '') . ' decoding="async" draggable="false"';
    echo '<img' . $attrs . '>';
}

function zh_status_label($status) {
    $labels = array('available' => 'Свободен', 'reserved' => 'В резерве', 'expected' => 'Ожидается', 'home' => 'В новой семье', 'demo' => 'Свободен');
    return $labels[$status] ?? '';
}

function zh_plural($n, $one, $few, $many) {
    $n = abs((int) $n) % 100; $n1 = $n % 10;
    if ($n > 10 && $n < 20) { return $many; }
    if ($n1 > 1 && $n1 < 5) { return $few; }
    if ($n1 === 1) { return $one; }
    return $many;
}

/** Возраст по дате рождения: «9 недель», «3 месяца». */
function zh_age($birth) {
    if (!$birth) { return ''; }
    try { $d = new DateTimeImmutable($birth, wp_timezone()); } catch (Exception $e) { return ''; }
    $now = new DateTimeImmutable('now', wp_timezone());
    $days = (int) $now->diff($d)->days;
    if ($d > $now) { return ''; }
    if ($days < 14) { return $days . ' ' . zh_plural($days, 'день', 'дня', 'дней'); }
    if ($days < 100) { $w = (int) floor($days / 7); return $w . ' ' . zh_plural($w, 'неделя', 'недели', 'недель'); }
    $m = (int) floor($days / 30.4);
    return $m . ' ' . zh_plural($m, 'месяц', 'месяца', 'месяцев');
}

function zh_date_ru($ymd) {
    if (!$ymd) { return ''; }
    $ts = strtotime($ymd . ' 12:00:00');
    if (!$ts) { return $ymd; }
    $months = array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function zh_kitten_data($post) {
    $post = get_post($post);
    $breed = zh_public_breed(get_post_meta($post->ID, '_zhibek_breed', true));
    $status = (string) get_post_meta($post->ID, '_zhibek_status', true);
    $price = (string) get_post_meta($post->ID, '_zhibek_price', true);
    return array(
        'id' => $post->ID, 'title' => get_the_title($post), 'url' => get_permalink($post),
        'breed' => $breed, 'breed_name' => $breed ? $breed->post_title : '', 'world' => $breed ? zh_breed_world($breed) : zh_breed_world(null),
        'status' => $status, 'status_label' => zh_status_label($status),
        'sex' => trim((string) get_post_meta($post->ID, '_zhibek_sex', true)), 'color' => trim((string) get_post_meta($post->ID, '_zhibek_color', true)),
        'birth' => (string) get_post_meta($post->ID, '_zhibek_birth', true), 'sire' => trim((string) get_post_meta($post->ID, '_zhibek_sire', true)), 'dam' => trim((string) get_post_meta($post->ID, '_zhibek_dam', true)),
        'price' => $price !== '' ? number_format((int) $price, 0, ',', ' ') . ' ₸' : '',
        'demo' => (bool) get_post_meta($post->ID, '_zhibek_demo', true), 'subtitle' => (string) get_post_meta($post->ID, '_zhibek_subtitle', true),
    );
}

/** Карточка котёнка: наклон 3D, цветной «мир» породы, вырезанный кот или фото. */
function zh_kitten_card($post, $i = 0) {
    $k = zh_kitten_data($post);
    $m = zh_media($post, 'large');
    $w = $k['world'];
    $age = zh_age($k['birth']);
    $meta = array_filter(array($k['sex'], $k['color'], $age));
    $style = '--accent:' . esc_attr($w['accent']) . ';--bg1:' . esc_attr($w['bg1']) . ';--bg2:' . esc_attr($w['bg2']) . ';--glow:' . esc_attr($w['glow']);
    ob_start(); ?>
    <article class="kcard" data-breed="<?php echo (int) ($k['breed'] ? $k['breed']->ID : 0); ?>" data-status="<?php echo esc_attr($k['status']); ?>" data-sex="<?php echo esc_attr($k['sex']); ?>" data-color="<?php echo esc_attr($k['color']); ?>" style="<?php echo $style; ?>">
        <a class="kcard-link" href="<?php echo esc_url($k['url']); ?>" data-cursor="Открыть" data-kitten="<?php echo (int) $k['id']; ?>">
            <span class="kcard-media" data-type="<?php echo esc_attr($m['type']); ?>">
                <span class="kcard-glow" aria-hidden="true"></span>
                <?php if ($m['url']) : ?>
                    <img class="kcard-img" src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($k['title'] . ($k['breed_name'] ? ' — ' . zh_lower($k['breed_name']) . ' кошка' : '')); ?>"<?php if ($m['w'] && $m['h']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> loading="lazy" decoding="async" draggable="false">
                <?php endif; ?>
                <span class="kcard-badges">
                    <?php if ($k['status_label']) : ?><span class="chip chip-status" data-status="<?php echo esc_attr($k['status']); ?>"><?php echo esc_html($k['status_label']); ?></span><?php endif; ?>
                    <?php if ($k['demo'] && zh_is_demo()) : ?><span class="chip chip-demo">Пример</span><?php endif; ?>
                </span>
            </span>
            <span class="kcard-body">
                <span class="kcard-breed"><?php echo esc_html($k['breed_name'] ?: 'ZHIBEK'); ?></span>
                <span class="kcard-name"><?php echo esc_html($k['title']); ?></span>
                <?php if ($meta) : ?><span class="kcard-meta"><?php echo esc_html(implode(' · ', $meta)); ?></span><?php endif; ?>
            </span>
        </a>
        <button class="kcard-save" type="button" data-save="<?php echo (int) $k['id']; ?>" aria-pressed="false" aria-label="Сохранить: <?php echo esc_attr($k['title']); ?>"><?php echo zh_icon('heart'); ?></button>
    </article>
    <?php
    return ob_get_clean();
}

function zh_render_kitten_cards($posts) {
    foreach ($posts as $i => $p) { echo zh_kitten_card($p, $i); }
}

/** Фильтры каталога: породы + «сохранённые». */
function zh_kitten_filters($breeds, $kittens) {
    $counts = array();
    foreach ($kittens as $k) { $b = (int) get_post_meta($k->ID, '_zhibek_breed', true); $counts[$b] = ($counts[$b] ?? 0) + 1; }
    echo '<div class="filters" role="group" aria-label="Фильтр котят"><button class="filter is-active" type="button" data-filter="all" aria-pressed="true">Все <sup>' . count($kittens) . '</sup></button>';
    foreach ($breeds as $b) {
        echo '<button class="filter" type="button" data-filter="' . (int) $b->ID . '" data-slug="' . esc_attr($b->post_name) . '" aria-pressed="false">' . esc_html($b->post_title) . ' <sup>' . (int) ($counts[$b->ID] ?? 0) . '</sup></button>';
    }
    echo '<button class="filter filter-saved" type="button" data-filter="saved" aria-pressed="false">' . zh_icon('heart') . ' Сохранённые <sup class="saved-count">0</sup></button></div>';
}

function zh_render_animal_media($post) {
    if (function_exists('zh_render_gallery')) { zh_render_gallery($post); }
    $video = function_exists('zh_gallery_video') ? zh_gallery_video($post) : null;
    if ($video) {
        echo '<div class="animal-video"><video controls playsinline preload="metadata"' . (!empty($video['poster']) ? ' poster="' . esc_url($video['poster']) . '"' : '') . '><source src="' . esc_url($video['src']) . '" type="' . esc_attr($video['mime']) . '">Ваш браузер не поддерживает видео.</video></div>';
    }
}

/* ------------------------------------------------------------------ карта */

function zh_map_svg() {
    require_once get_template_directory() . '/inc/map.php';
    $map = zh_kz_map();
    $s = zh_settings();
    $active = array_filter(array_map('trim', preg_split('/[,\n]+/u', (string) $s['cities'])));
    ob_start(); ?>
    <svg class="kzmap" viewBox="0 0 <?php echo (int) $map['w']; ?> <?php echo (int) $map['h']; ?>" role="img" aria-label="Карта Казахстана с городами, куда мы привозим котят">
        <defs>
            <pattern id="kzdots" width="9" height="9" patternUnits="userSpaceOnUse"><circle cx="4.5" cy="4.5" r="1.25"/></pattern>
            <clipPath id="kzclip"><path d="<?php echo esc_attr($map['d']); ?>"/></clipPath>
        </defs>
        <path class="kz-fill" d="<?php echo esc_attr($map['d']); ?>"/>
        <rect class="kz-dots" width="<?php echo (int) $map['w']; ?>" height="<?php echo (int) $map['h']; ?>" fill="url(#kzdots)" clip-path="url(#kzclip)"/>
        <path class="kz-line" d="<?php echo esc_attr($map['d']); ?>" pathLength="1"/>
        <g class="kz-cities">
            <?php foreach ($map['cities'] as $name => $xy) : $on = in_array($name, $active, true); ?>
                <g class="kz-city<?php echo $on ? ' is-on' : ''; ?>" data-city="<?php echo esc_attr($name); ?>" transform="translate(<?php echo (int) $xy[0]; ?> <?php echo (int) $xy[1]; ?>)">
                    <circle class="kz-pulse" r="6"/><circle class="kz-pin" r="3.2"/>
                    <text class="kz-label" x="10" y="4"><?php echo esc_html($name); ?></text>
                </g>
            <?php endforeach; ?>
        </g>
    </svg>
    <?php
    return ob_get_clean();
}

function zh_map_city_list() {
    $s = zh_settings();
    return array_values(array_filter(array_map('trim', preg_split('/[,\n]+/u', (string) $s['cities']))));
}

/* ------------------------------------------------- врата: эмблема на створке */

/** Половина эмблемы-радужки для левой ('l') или правой ('r') створки. Зрачок-щель лежит на стыке створок. */
function zh_door_emblem($side) {
    $cx = 150; $cy = 150;
    $vb = $side === 'l' ? '0 0 150 300' : '150 0 150 300';
    $ticks = ''; $rays = '';
    for ($i = 0; $i < 72; $i++) {
        $a = deg2rad($i * 5); $long = $i % 6 === 0; $r1 = $long ? 124 : 130; $r2 = 138;
        $ticks .= sprintf('M%.1f %.1fL%.1f %.1f', $cx + cos($a) * $r1, $cy + sin($a) * $r1, $cx + cos($a) * $r2, $cy + sin($a) * $r2);
    }
    for ($i = 0; $i < 60; $i++) {
        $a = deg2rad($i * 6 + 1.5); $r1 = 72; $r2 = $i % 2 ? 100 : 112;
        $rays .= sprintf('M%.1f %.1fL%.1f %.1f', $cx + cos($a) * $r1, $cy + sin($a) * $r1, $cx + cos($a) * $r2, $cy + sin($a) * $r2);
    }
    $id = 'dl' . $side;
    return '<svg class="door-emblem door-emblem-' . $side . '" viewBox="' . $vb . '" fill="none" aria-hidden="true"><defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f5dfa6"/><stop offset=".5" stop-color="#fff3d1"/><stop offset="1" stop-color="#d9a64e"/></linearGradient></defs>'
        . '<g stroke="currentColor" stroke-linecap="round"><circle cx="150" cy="150" r="147" stroke-width="1"/><circle cx="150" cy="150" r="141" stroke-opacity=".4" stroke-width=".7"/>'
        . '<path d="' . $ticks . '" stroke-opacity=".55" stroke-width=".7"/><circle cx="150" cy="150" r="118" stroke-opacity=".7" stroke-width=".8"/>'
        . '<path d="' . $rays . '" stroke-opacity=".5" stroke-width=".7"/><circle cx="150" cy="150" r="66" stroke-width="1"/></g>'
        . '<path d="M150 50C196 100 196 200 150 250 104 200 104 100 150 50Z" fill="url(#' . $id . ')"/>'
        . '<path d="M150 50C196 100 196 200 150 250 104 200 104 100 150 50Z" stroke="currentColor" stroke-width="1.2"/></svg>';
}

/* ------------------------------------------------------- родословная (пример) */

function zh_pedigree_demo() {
    return array(
        0 => array(array('Алтын', 'Абиссинская кошка, руди', 'Котёнок')),
        1 => array(array('Aston of Zhibek', 'Абиссинский кот, руди', 'Отец'), array('Miura of Zhibek', 'Абиссинская кошка, соррель', 'Мать')),
        2 => array(array('Ramses', 'Кот, руди', 'Дед по отцу'), array('Nefer', 'Кошка, руди', 'Бабушка по отцу'), array('Amun', 'Кот, соррель', 'Дед по матери'), array('Safira', 'Кошка, фавн', 'Бабушка по матери')),
        3 => array(array('Horus', 'Кот, руди', 'Прадед'), array('Isis', 'Кошка, руди', 'Прабабушка'), array('Anubis', 'Кот, руди', 'Прадед'), array('Nubia', 'Кошка, соррель', 'Прабабушка'), array('Osiris', 'Кот, соррель', 'Прадед'), array('Kleo', 'Кошка, руди', 'Прабабушка'), array('Seth', 'Кот, фавн', 'Прадед'), array('Hathor', 'Кошка, фавн', 'Прабабушка')),
    );
}

/** SVG-схема: котёнок слева, предки справа; линии рисуются при прокрутке (pathLength=1). */
function zh_pedigree_svg() {
    $data = zh_pedigree_demo();
    $xs = array(78, 318, 548, 770);
    $rs = array(40, 27, 20, 13);
    $ys = array();
    $n3 = count($data[3]);
    for ($i = 0; $i < $n3; $i++) { $ys[3][$i] = 44 + $i * (492 / ($n3 - 1)); }
    for ($g = 2; $g >= 0; $g--) {
        foreach ($data[$g] as $i => $_) { $ys[$g][$i] = ($ys[$g + 1][2 * $i] + $ys[$g + 1][2 * $i + 1]) / 2; }
    }
    $kitten = zh_img('cats/k-abyssinian.webp');
    $edges = ''; $nodes = '';
    for ($g = 0; $g < 3; $g++) {
        foreach ($data[$g] as $i => $_) {
            foreach (array(2 * $i, 2 * $i + 1) as $j) {
                $x0 = $xs[$g] + $rs[$g]; $y0 = $ys[$g][$i]; $x1 = $xs[$g + 1] - $rs[$g + 1]; $y1 = $ys[$g + 1][$j]; $xm = ($x0 + $x1) / 2;
                $edges .= sprintf('<path class="pe" data-g="%d" data-from="%d-%d" data-to="%d-%d" pathLength="1" d="M%.1f %.1fC%.1f %.1f %.1f %.1f %.1f %.1f"/>', $g + 1, $g, $i, $g + 1, $j, $x0, $y0, $xm, $y0, $xm, $y1, $x1, $y1);
            }
        }
    }
    foreach ($data as $g => $row) {
        foreach ($row as $i => $nd) {
            $x = $xs[$g]; $y = $ys[$g][$i]; $r = $rs[$g]; $is_sire = $i % 2 === 0;
            $nodes .= sprintf('<g class="pn pn-g%d%s" tabindex="0" role="button" data-id="%d-%d" data-g="%d" data-name="%s" data-meta="%s" data-rel="%s" transform="translate(%.1f %.1f)" aria-label="%s, %s">',
                $g, $g > 0 ? ($is_sire ? ' is-sire' : ' is-dam') : ' is-root', $g, $i, $g, esc_attr($nd[0]), esc_attr($nd[1]), esc_attr($nd[2]), $x, $y, esc_attr($nd[0]), esc_attr($nd[2]));
            $nodes .= '<circle class="pn-halo" r="' . ($r + 7) . '"/><circle class="pn-ring" r="' . $r . '"/>';
            if ($g === 0) {
                $nodes .= '<clipPath id="pk"><circle r="' . ($r - 3) . '"/></clipPath><image href="' . esc_url($kitten) . '" x="-14.5" y="-36" width="97" height="176" clip-path="url(#pk)" preserveAspectRatio="xMidYMid slice" transform="translate(-12 2)"/>';
            } else {
                $s = $r * .5;
                $nodes .= '<path class="pn-eye" d="M0 ' . (-$s) . 'C' . ($s * .55) . ' ' . (-$s * .4) . ' ' . ($s * .55) . ' ' . ($s * .4) . ' 0 ' . $s . ' ' . (-$s * .55) . ' ' . ($s * .4) . ' ' . (-$s * .55) . ' ' . (-$s * .4) . ' 0 ' . (-$s) . 'Z"/>';
            }
            $label_y = $g === 0 ? $r + 22 : -$r - 9;
            $nodes .= '<text class="pn-name" y="' . $label_y . '" text-anchor="middle">' . esc_html($nd[0]) . '</text></g>';
        }
    }
    return '<svg class="pedigree-svg" viewBox="0 0 860 580" role="group" aria-label="Пример родословной на три поколения предков"><g class="pe-lines" fill="none">' . $edges . '</g><g class="pe-nodes">' . $nodes . '</g></svg>';
}
