<?php
/** Вспомогательные функции шаблонов: ассеты, иконки, данные пород и котов, карточки. */
if (!defined('ABSPATH')) { exit; }

function zh_asset($path) { return get_template_directory_uri() . '/assets/' . ltrim($path, '/'); }
function zh_img($path) { return zh_asset('img/' . ltrim($path, '/')); }
function zh_has_img($path) { return is_file(get_template_directory() . '/assets/img/' . ltrim($path, '/')); }

function zh_settings() {
    $defaults = array('demo' => true, 'phone' => '', 'whatsapp' => '', 'telegram' => '', 'instagram' => '', 'email' => '', 'city' => 'Казахстан', 'cities' => '');
    return function_exists('zhibek_get_settings') ? array_merge($defaults, zhibek_get_settings()) : $defaults;
}

/** Документы питомника: скан, подпись и размеры для просмотра. Пусто, пока владелец не загрузил ни одного. */
function zh_documents() {
    if (!function_exists('zhibek_get_docs')) { return array(); }
    $items = array();
    foreach (zhibek_get_docs() as $doc) {
        $id = get_post_thumbnail_id($doc);
        $full = wp_get_attachment_image_src($id, 'full');
        $tile = wp_get_attachment_image_src($id, 'medium_large');
        if (!$full || !$tile) { continue; }
        $items[] = array(
            'title' => get_the_title($doc), 'text' => has_excerpt($doc) ? get_the_excerpt($doc) : '',
            'src' => $full[0], 'tile' => $tile[0], 'w' => (int) $tile[1], 'h' => (int) $tile[2],
            'alt' => trim((string) get_post_meta($id, '_wp_attachment_image_alt', true)) ?: get_the_title($doc),
        );
    }
    return $items;
}

function zh_is_demo() { return function_exists('zhibek_is_demo') ? zhibek_is_demo() : true; }
function zh_breeds() { return function_exists('zhibek_get_breeds') ? zhibek_get_breeds() : array(); }
/** Порядок в каталоге: сначала свободные, затем ожидаются, в резерве, производители и дома; внутри группы — новые выше. */
function zh_sort_cats($cats) {
    $weight = array('available' => 0, 'expected' => 1, 'reserved' => 2, 'breeder' => 3, 'home' => 4);
    $rows = array(); $i = 0;
    foreach ($cats as $cat) { $rows[] = array($cat, $weight[get_post_meta($cat->ID, '_zhibek_status', true)] ?? 0, $i++); }
    usort($rows, function ($a, $b) { return $a[1] <=> $b[1] ?: $a[2] <=> $b[2]; });
    return array_map(function ($r) { return $r[0]; }, $rows);
}
function zh_cats($args = array()) {
    if (!function_exists('zhibek_get_cats')) { return array(); }
    $limit = isset($args['numberposts']) ? (int) $args['numberposts'] : -1;
    $args['numberposts'] = -1;
    $cats = zh_sort_cats(zhibek_get_cats($args));
    return $limit > 0 ? array_slice($cats, 0, $limit) : $cats;
}

/** Нижний регистр без зависимости от mbstring. */
function zh_lower($s) { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }

/** Адрес страницы конфиденциальности или пустая строка, если страницы нет. */
function zh_privacy_url() {
    $url = get_privacy_policy_url();
    if ($url) { return $url; }
    $page = get_page_by_path('privacy');
    return $page ? get_permalink($page) : '';
}

/** Адрес страницы по её слагу или пустая строка. */
function zh_page_url($slug) {
    $page = get_page_by_path($slug);
    return $page && 'publish' === $page->post_status ? get_permalink($page) : '';
}

/** Ссылки на мессенджеры и соцсети, которые владелец указал в настройках. */
function zh_contacts() {
    $s = zh_settings();
    $out = array();
    if (!empty($s['whatsapp'])) { $out['whatsapp'] = array('WhatsApp', 'https://wa.me/' . $s['whatsapp'] . '?text=' . rawurlencode('Здравствуйте! Хочу узнать о котах ZHIBEK.')); }
    if (!empty($s['telegram'])) { $out['telegram'] = array('Telegram', 'https://t.me/' . $s['telegram']); }
    if (!empty($s['instagram'])) { $out['instagram'] = array('Instagram', $s['instagram']); }
    if (!empty($s['phone'])) { $out['phone'] = array($s['phone'], 'tel:' . preg_replace('/[^+0-9]/', '', $s['phone'])); }
    if (!empty($s['email'])) { $out['email'] = array($s['email'], 'mailto:' . $s['email']); }
    return $out;
}

/* ------------------------------------------------------------- типографика */

/** Неразрывные пробелы по правилам русской типографики: предлоги и союзы не остаются в конце строки, число и единица не разрываются. */
function zh_nbsp($text) {
    $text = (string) $text;
    if ($text === '' || !function_exists('mb_strlen')) { return $text; }
    $nb = "\xC2\xA0";
    $short = 'а|без|бы|в|во|да|для|до|ж|же|за|и|из|или|к|ко|ли|на|над|не|ни|но|о|об|от|по|под|при|про|с|со|у';
    $text = preg_replace('/(^|[\s(«„"\x{2014}\x{2013}\-])(' . $short . ')\s+(?=\S)/iu', '$1$2' . $nb, $text);
    $text = preg_replace('/(\d)\s+(₸|кг|г|мм|см|нед\.|недель|недели|неделя|месяц\w*|дн\w*|лет|года?)(?![\p{L}])/iu', '$1' . $nb . '$2', $text);
    $text = preg_replace('/\s+([\x{2014}])/u', $nb . '$1', $text);
    return $text;
}

function zh_t($text) { return esc_html(zh_nbsp($text)); }

/** То же для HTML: правятся только текстовые узлы, теги и атрибуты не затрагиваются. */
function zh_nbsp_html($html) {
    $parts = preg_split('/(<[^>]*>)/u', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) { return $html; }
    foreach ($parts as $i => $part) {
        if ($part !== '' && $part[0] !== '<') { $parts[$i] = zh_nbsp($part); }
    }
    return implode('', $parts);
}

/** «в WhatsApp или Telegram» по тем мессенджерам, которые владелец указал в настройках. Пустая строка, если не указан ни один. */
function zh_messengers_phrase() {
    $c = zh_contacts(); $names = array();
    if (isset($c['whatsapp'])) { $names[] = 'WhatsApp'; }
    if (isset($c['telegram'])) { $names[] = 'Telegram'; }
    return $names ? 'в ' . implode(' или ', $names) : '';
}

/* ------------------------------------------------------------------ иконки */

function zh_arrow($class = '') {
    return '<svg class="arr ' . esc_attr($class) . '" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12h15M13 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function zh_arrow_up_right() {
    return '<svg class="arr" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 17L17 7M8 7h9v9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/** Нарисованная от руки изогнутая стрелка для пометок. */
function zh_hand_arrow($class = '') {
    return '<svg class="hand-arrow ' . esc_attr($class) . '" viewBox="0 0 80 60" fill="none" aria-hidden="true"><path d="M4 6C22 2 52 8 62 40" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/><path d="M50 36l12 8 6-14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function zh_icon($name) {
    $paths = array(
        'close' => '<path d="M5 5l14 14M19 5L5 19"/>',
        'whatsapp' => '<path d="M3.5 20.5l1.3-4.6A8.5 8.5 0 1112 20.5a8.4 8.4 0 01-4-1z"/><path d="M9 8.5c.3 3 2.5 5.2 5.5 6.5l1.5-1.5-2-1-1 .8c-.9-.4-1.8-1.3-2.2-2.2l.8-1-1-2z"/>',
        'telegram' => '<path d="M21 4L3 11l5.5 2L10 19l3-3.5L17.5 19 21 4z"/><path d="M8.5 13L21 4"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r=".6"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M4 7l8 6 8-6"/>',
        'handoff' => '<circle cx="6" cy="12" r="3"/><circle cx="18" cy="12" r="3"/><path d="M9 12h6"/>',
        'pin' => '<path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'doc' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
        'passport' => '<rect x="5" y="3" width="14" height="18" rx="2.5"/><circle cx="12" cy="10" r="3"/><path d="M9 16h6"/>',
        'chip' => '<rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 11h4M10 13.5h4M9 3v4M15 3v4M9 17v4M15 17v4M3 9h4M3 15h4M17 9h4M17 15h4"/>',
        'tree' => '<rect x="3" y="9.5" width="5" height="5" rx="1.2"/><rect x="16" y="3.5" width="5" height="5" rx="1.2"/><rect x="16" y="15.5" width="5" height="5" rx="1.2"/><path d="M8 12h3.5V6H16M11.5 12V18H16"/>',
        'route' => '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3 3 0 000-6H9a3 3 0 010-6h6.5"/>',
        'camera' => '<rect x="3" y="7" width="18" height="13" rx="3"/><circle cx="12" cy="13.5" r="3.5"/><path d="M8 7l1.5-3h5L16 7"/>',
    );
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Знак: радужка с вертикальным зрачком. */
function zh_mark($class = 'mark', $accent = true) {
    return '<svg class="' . esc_attr($class) . '" viewBox="0 0 48 48" fill="none" aria-hidden="true"><circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2.4"/><path d="M24 8c6.6 6.4 6.6 25.6 0 32-6.6-6.4-6.6-25.6 0-32z" fill="' . ($accent ? 'var(--clay)' : 'currentColor') . '"/></svg>';
}

/** Щель-зрачок, заменяющая букву I в слове ZHIBEK. */
function zh_pupil_i() {
    return '<svg class="wm-i" viewBox="0 0 10 60" aria-hidden="true"><path d="M5 0c6 18 6 42 0 60C-1 42-1 18 5 0z" fill="currentColor"/></svg>';
}

function zh_wordmark($class = 'wm') {
    return '<span class="' . esc_attr($class) . '" role="img" aria-label="ZHIBEK"><span aria-hidden="true">ZH</span>' . zh_pupil_i() . '<span aria-hidden="true">BEK</span></span>';
}

function zh_brand() { return zh_mark('mark') . zh_wordmark('wm'); }

/* ------------------------------------------------------------ породы */

/** Цвет породы: насыщенный тон, на котором кот «загорается». Для новой породы берётся поле «Цвет» из карточки. */
function zh_breed_color($post) {
    $post = $post ? get_post($post) : null;
    $slug = $post ? $post->post_name : '';
    $defaults = array('abyssinian' => '#2c4a3b', 'bengal' => '#6a2230', 'siamese' => '#2a46a0');
    $color = $defaults[$slug] ?? '#3b2f28';
    if ($post) {
        $custom = (string) get_post_meta($post->ID, '_zhibek_accent', true);
        // Светлые цвета прежней версии (пастель) не подходят под светлый текст на панели: берём цвет породы из темы.
        if (preg_match('/^#[0-9a-f]{6}$/i', $custom)) {
            $lum = (0.2126 * hexdec(substr($custom, 1, 2)) + 0.7152 * hexdec(substr($custom, 3, 2)) + 0.0722 * hexdec(substr($custom, 5, 2))) / 255;
            if ($lum < 0.42 || !isset($defaults[$slug])) { $color = $custom; }
        }
    }
    return $color;
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

/** Оценки характера: строки «Активность: 5» → [[«Активность», 5], …]. */
function zh_breed_scores($post) {
    $raw = (string) get_post_meta(get_post($post)->ID, '_zhibek_scores', true);
    $out = array();
    foreach (preg_split('/\R+/u', $raw) as $line) {
        $parts = explode(':', trim($line), 2);
        if (count($parts) === 2 && trim($parts[0]) !== '') { $out[] = array(trim($parts[0]), max(1, min(5, (int) $parts[1]))); }
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
 * type=cutout — вырезанный кот на прозрачном фоне (файл темы или загруженный PNG/WebP с отметкой), type=photo — обычная фотография.
 * small — лёгкая версия для карточек.
 */
function zh_media($post, $size = 'large') {
    $post = get_post($post);
    $thumb = zh_public_thumbnail_id($post);
    if ($thumb) {
        $src = wp_get_attachment_image_src($thumb, $size);
        if ($src) {
            $small = wp_get_attachment_image_src($thumb, 'medium_large');
            $cut = (bool) get_post_meta($post->ID, '_zhibek_cutout', true);
            return array('url' => $src[0], 'small' => $small ? $small[0] : $src[0], 'w' => $src[1], 'h' => $src[2], 'type' => $cut ? 'cutout' : 'photo', 'id' => $thumb);
        }
    }
    $file = basename((string) get_post_meta($post->ID, '_zhibek_image', true));
    if ($file && zh_has_img('pets/' . $file)) {
        $size_info = @getimagesize(get_template_directory() . '/assets/img/pets/' . $file);
        $sm = preg_replace('/\.webp$/', '-sm.webp', $file);
        return array('url' => zh_img('pets/' . $file), 'small' => zh_has_img('pets/' . $sm) ? zh_img('pets/' . $sm) : zh_img('pets/' . $file), 'w' => $size_info ? $size_info[0] : 0, 'h' => $size_info ? $size_info[1] : 0, 'type' => 'cutout', 'id' => 0);
    }
    // В демо-режиме у базовых пород картинка находится по слагу, даже если в записи остался старый путь к файлу.
    if ($post->post_type === 'zhibek_breed' && zh_is_demo() && zh_has_img('pets/' . $post->post_name . '-sit.webp')) {
        $file = $post->post_name . '-sit.webp';
        $size_info = @getimagesize(get_template_directory() . '/assets/img/pets/' . $file);
        return array('url' => zh_img('pets/' . $file), 'small' => zh_has_img('pets/' . $post->post_name . '-sit-sm.webp') ? zh_img('pets/' . $post->post_name . '-sit-sm.webp') : zh_img('pets/' . $file), 'w' => $size_info ? $size_info[0] : 0, 'h' => $size_info ? $size_info[1] : 0, 'type' => 'cutout', 'id' => 0);
    }
    return array('url' => '', 'small' => '', 'w' => 0, 'h' => 0, 'type' => 'none', 'id' => 0);
}

/** Точки на сидящем коте (в % от изображения) для пометок: глаза, шерсть, вес, окрас. */
function zh_callout_points($slug) {
    $p = array(
        'abyssinian' => array('eyes' => array(70, 20), 'coat' => array(36, 62), 'weight' => array(62, 82), 'color' => array(46, 40)),
        'bengal' => array('eyes' => array(82, 20), 'coat' => array(54, 62), 'weight' => array(72, 84), 'color' => array(62, 46)),
        'siamese' => array('eyes' => array(26, 20), 'coat' => array(48, 58), 'weight' => array(38, 82), 'color' => array(14, 44)),
    );
    return $p[$slug] ?? array();
}

/* ------------------------------------------------------------ коты */

function zh_status_label($status) {
    $labels = array('available' => 'Свободен', 'reserved' => 'В резерве', 'expected' => 'Ожидается', 'breeder' => 'Производитель', 'home' => 'В новой семье', 'demo' => 'Свободен');
    return $labels[$status] ?? '';
}

function zh_plural($n, $one, $few, $many) {
    $n = abs((int) $n) % 100; $n1 = $n % 10;
    if ($n > 10 && $n < 20) { return $many; }
    if ($n1 > 1 && $n1 < 5) { return $few; }
    if ($n1 === 1) { return $one; }
    return $many;
}

/** Возраст по дате рождения: «9 недель», «3 месяца», «2 года». */
function zh_age($birth) {
    if (!$birth) { return ''; }
    try { $d = new DateTimeImmutable($birth, wp_timezone()); } catch (Exception $e) { return ''; }
    $now = new DateTimeImmutable('now', wp_timezone());
    if ($d > $now) { return ''; }
    $days = (int) $now->diff($d)->days;
    if ($days < 14) { return $days . ' ' . zh_plural($days, 'день', 'дня', 'дней'); }
    if ($days < 100) { $w = (int) floor($days / 7); return $w . ' ' . zh_plural($w, 'неделя', 'недели', 'недель'); }
    if ($days < 730) { $m = (int) floor($days / 30.4); return $m . ' ' . zh_plural($m, 'месяц', 'месяца', 'месяцев'); }
    $y = (int) floor($days / 365.25);
    return $y . ' ' . zh_plural($y, 'год', 'года', 'лет');
}

function zh_date_ru($ymd) {
    if (!$ymd) { return ''; }
    $ts = strtotime($ymd . ' 12:00:00');
    if (!$ts) { return $ymd; }
    $months = array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря');
    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

/** Котёнок или взрослый: задано вручную или определяется по дате рождения (младше года — котёнок). */
function zh_cat_kind($post) {
    $post = get_post($post);
    $manual = (string) get_post_meta($post->ID, '_zhibek_kind', true);
    if (in_array($manual, array('kitten', 'adult'), true)) { return $manual; }
    $birth = (string) get_post_meta($post->ID, '_zhibek_birth', true);
    if ($birth) {
        $ts = strtotime($birth . ' 12:00:00');
        if ($ts && $ts > strtotime('-1 year')) { return 'kitten'; }
    }
    return 'adult';
}

function zh_cat_data($post) {
    $post = get_post($post);
    $breed = zh_public_breed(get_post_meta($post->ID, '_zhibek_breed', true));
    $status = (string) get_post_meta($post->ID, '_zhibek_status', true);
    $price = (string) get_post_meta($post->ID, '_zhibek_price', true);
    $kind = zh_cat_kind($post);
    return array(
        'id' => $post->ID, 'title' => get_the_title($post), 'url' => get_permalink($post),
        'breed' => $breed, 'breed_name' => $breed ? $breed->post_title : '', 'breed_slug' => $breed ? $breed->post_name : '', 'color_hex' => zh_breed_color($breed),
        'status' => $status, 'status_label' => zh_status_label($status),
        'kind' => $kind, 'kind_label' => $kind === 'kitten' ? 'Котёнок' : 'Взрослый',
        'sex' => trim((string) get_post_meta($post->ID, '_zhibek_sex', true)), 'color' => trim((string) get_post_meta($post->ID, '_zhibek_color', true)),
        'birth' => (string) get_post_meta($post->ID, '_zhibek_birth', true), 'sire' => trim((string) get_post_meta($post->ID, '_zhibek_sire', true)), 'dam' => trim((string) get_post_meta($post->ID, '_zhibek_dam', true)),
        'price' => $price !== '' ? number_format((int) $price, 0, ',', ' ') . ' ₸' : '',
        'demo' => (bool) get_post_meta($post->ID, '_zhibek_demo', true), 'subtitle' => (string) get_post_meta($post->ID, '_zhibek_subtitle', true),
    );
}

/** Карточка кота: арка цвета породы, кот выступает за её верхний край. */
function zh_cat_card($post, $i = 0) {
    $k = zh_cat_data($post);
    $m = zh_media($post, 'large');
    $age = zh_age($k['birth']);
    $meta = array_filter(array($k['sex'], $age));
    $tilt = array(-2.2, 1.6, -1.2, 2.4);
    ob_start(); ?>
    <article class="ccard" data-breed="<?php echo esc_attr($k['breed_slug']); ?>" data-kind="<?php echo esc_attr($k['kind']); ?>" data-status="<?php echo esc_attr($k['status']); ?>" style="--bg:<?php echo esc_attr($k['color_hex']); ?>;--tilt:<?php echo esc_attr($tilt[$i % 4]); ?>deg;--vt:c<?php echo (int) $k['id']; ?>">
        <a class="ccard-link" href="<?php echo esc_url($k['url']); ?>">
            <span class="ccard-media" data-type="<?php echo esc_attr($m['type']); ?>">
                <span class="ccard-arch" aria-hidden="true"></span>
                <?php if ($m['url']) : ?>
                    <img src="<?php echo esc_url($m['small']); ?>" alt="<?php echo esc_attr($k['title'] . ($k['breed_name'] ? ', ' . zh_lower($k['breed_name']) . ' кошка' : '')); ?>"<?php if ($m['w'] && $m['h']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> loading="lazy" decoding="async" draggable="false">
                <?php endif; ?>
                <span class="ccard-badges">
                    <?php if ($k['status_label']) : ?><span class="chip" data-status="<?php echo esc_attr($k['status']); ?>"><?php echo esc_html($k['status_label']); ?></span><?php endif; ?>
                    <?php if ($k['demo'] && zh_is_demo()) : ?><span class="chip chip-demo">Пример</span><?php endif; ?>
                </span>
            </span>
            <span class="ccard-body">
                <span class="ccard-name"><?php echo esc_html($k['title']); ?></span>
                <span class="ccard-meta"><?php echo esc_html($k['kind_label'] . ($k['breed_name'] ? ' · ' . zh_lower($k['breed_name']) : '')); ?><?php if ($meta) : ?><br><?php echo esc_html(implode(' · ', $meta)); ?><?php endif; ?></span>
                <?php if ($k['price']) : ?><span class="ccard-price"><?php echo esc_html($k['price']); ?></span><?php endif; ?>
            </span>
        </a>
    </article>
    <?php
    return ob_get_clean();
}

function zh_render_cat_cards($posts) {
    foreach (array_values($posts) as $i => $p) { echo zh_cat_card($p, $i); }
}

/** Фильтры каталога: порода и возраст. Показываются только те, по которым есть коты. */
function zh_cat_filters($breeds, $cats, $by_breed = true) {
    $b_count = array(); $kind_count = array('kitten' => 0, 'adult' => 0);
    foreach ($cats as $c) {
        $b = (int) get_post_meta($c->ID, '_zhibek_breed', true); $b_count[$b] = ($b_count[$b] ?? 0) + 1;
        $kind_count[zh_cat_kind($c)]++;
    }
    echo '<div class="filters" data-filters>';
    if ($by_breed) {
        echo '<div class="filter-group" role="group" aria-label="Порода"><button class="filter is-active" type="button" data-group="breed" data-value="all" aria-pressed="true">Все породы</button>';
        foreach ($breeds as $b) {
            if (empty($b_count[$b->ID])) { continue; }
            echo '<button class="filter" type="button" data-group="breed" data-value="' . esc_attr($b->post_name) . '" aria-pressed="false" style="--bg:' . esc_attr(zh_breed_color($b)) . '"><i></i>' . esc_html($b->post_title) . ' <sup>' . (int) $b_count[$b->ID] . '</sup></button>';
        }
        echo '</div>';
    }
    echo '<div class="filter-group" role="group" aria-label="Возраст"><button class="filter is-active" type="button" data-group="kind" data-value="all" aria-pressed="true">Все</button>';
    if ($kind_count['kitten']) { echo '<button class="filter" type="button" data-group="kind" data-value="kitten" aria-pressed="false">Котята <sup>' . (int) $kind_count['kitten'] . '</sup></button>'; }
    if ($kind_count['adult']) { echo '<button class="filter" type="button" data-group="kind" data-value="adult" aria-pressed="false">Взрослые <sup>' . (int) $kind_count['adult'] . '</sup></button>'; }
    echo '</div></div>';
}

function zh_render_animal_media($post) {
    if (function_exists('zh_render_gallery')) { zh_render_gallery($post); }
    $video = function_exists('zh_gallery_video') ? zh_gallery_video($post) : null;
    if ($video) {
        echo '<div class="animal-video"><video controls playsinline preload="metadata"' . (!empty($video['poster']) ? ' poster="' . esc_url($video['poster']) . '"' : '') . '><source src="' . esc_url($video['src']) . '" type="' . esc_attr($video['mime']) . '">Ваш браузер не поддерживает видео.</video></div>';
    }
}


/* ------------------------------------------------- пустое состояние и сцены */

/** Пустое состояние каталога: кот выглядывает из-за края карточки. */
function zh_empty_cats($title_html, $text, $topic = 'Ближайшие помёты') {
    $img = zh_has_img('pets/peek-side.webp') ? zh_img('pets/peek-side.webp') : '';
    ob_start(); ?>
    <div class="empty-cats" data-reveal>
        <?php if ($img) : ?><img class="empty-peek" src="<?php echo esc_url($img); ?>" alt="" width="936" height="1000" loading="lazy" decoding="async" draggable="false"><?php endif; ?>
        <div class="empty-copy">
            <h3 class="h2"><?php echo wp_kses($title_html, array('em' => array())); ?></h3>
            <p><?php echo zh_t($text); ?></p>
            <button class="btn btn-primary" type="button" data-enquire="<?php echo esc_attr($topic); ?>"><span>Написать нам</span><?php echo zh_arrow(); ?></button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Сцена с анимацией кота, привязанной к прокрутке (кадры в theme/assets/anim).
 * variant: bottom — кот выглядывает снизу, right — из-за правого края.
 */
function zh_scene($variant, $color, $title_html, $lead, $actions_html, $extra = array()) {
    $cfg = array(
        'bottom' => array('dir' => 'bengal-bottom', 'alt' => 'Бенгальский кот выглядывает снизу', 'breaks' => '14,24,33'),
        'right' => array('dir' => 'bengal-right', 'alt' => 'Бенгальский кот выглядывает сбоку', 'breaks' => '12,23'),
    );
    if (!isset($cfg[$variant])) { return ''; }
    $c = $cfg[$variant];
    // Число кадров считаем по файлам; постер — последний кадр (поза, в которой кот остаётся в конце прокрутки).
    $frames = glob(get_template_directory() . '/assets/anim/' . $c['dir'] . '/frame_*.webp');
    if (!$frames) { return ''; }
    $c['n'] = count($frames);
    $poster = 'anim/' . $c['dir'] . '/' . basename(end($frames));
    // Расширенная сцена: подпись «кто тут спрятался», факты о породе и рукописные пометки, которые меняются вместе с позой кота.
    $rich = !empty($extra['facts']) || !empty($extra['notes']);
    $facts = $extra['facts'] ?? array(); $notes = $extra['notes'] ?? array();
    ob_start(); ?>
    <section class="scene scene--<?php echo esc_attr($variant); ?><?php echo $rich ? ' scene--rich' : ''; ?>" data-scene data-breed="bengal" data-frames="<?php echo (int) $c['n']; ?>" data-base="<?php echo esc_url(zh_asset('anim/' . $c['dir'] . '/')); ?>"<?php echo $rich ? ' data-breaks="' . esc_attr($c['breaks']) . '"' : ''; ?> style="--bg:<?php echo esc_attr($color); ?>">
        <div class="scene-sticky">
            <?php if ($rich) : ?>
            <div class="scene-in">
                <div class="scene-text">
                    <?php if (!empty($extra['hook'])) : ?><span class="hand scene-hook"><?php echo esc_html($extra['hook']); ?></span><?php endif; ?>
                    <h2 class="h2"><?php echo wp_kses($title_html, array('em' => array())); ?></h2>
                    <p class="lead"><?php echo zh_t($lead); ?></p>
                    <?php if ($facts) : ?>
                    <dl class="scene-facts"><?php foreach ($facts as $f) : ?><div><dt><?php echo esc_html($f[0]); ?></dt><dd><?php echo zh_t($f[1]); ?></dd></div><?php endforeach; ?></dl>
                    <?php endif; ?>
                    <div class="scene-cta"><?php echo $actions_html; ?></div>
                </div>
                <div class="scene-stage">
                    <div class="scene-cat">
                        <canvas width="800" height="600" aria-hidden="true"></canvas>
                        <img src="<?php echo esc_url(zh_asset($poster)); ?>" alt="<?php echo esc_attr($c['alt']); ?>" width="800" height="600" loading="lazy" decoding="async" draggable="false">
                    </div>
                    <?php if ($notes) : ?>
                    <div class="scene-notes" aria-hidden="true"><?php foreach ($notes as $i => $note) : ?><p class="scene-note"><?php echo zh_hand_arrow(); ?><span><?php echo esc_html($note); ?></span></p><?php endforeach; ?></div>
                    <ol class="scene-steps" aria-hidden="true"><?php foreach ($notes as $note) { echo '<li></li>'; } ?></ol>
                    <?php endif; ?>
                </div>
            </div>
            <?php else : ?>
            <div class="scene-text">
                <h2 class="h2"><?php echo wp_kses($title_html, array('em' => array())); ?></h2>
                <p class="lead"><?php echo zh_t($lead); ?></p>
                <div class="scene-cta"><?php echo $actions_html; ?></div>
            </div>
            <div class="scene-cat">
                <canvas width="800" height="600" aria-hidden="true"></canvas>
                <img src="<?php echo esc_url(zh_asset($poster)); ?>" alt="<?php echo esc_attr($c['alt']); ?>" width="800" height="600" loading="lazy" decoding="async" draggable="false">
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ карта */

/**
 * Карта доставки: контур Казахстана из точек, города из «Настройки → ZHIBEK» и тонкая сеть связей между ближайшими городами.
 * Центра нет: питомник не привязан к одному городу.
 */
function zh_map_svg() {
    require_once get_template_directory() . '/inc/map.php';
    $map = zh_kz_map();
    $coords = $map['cities'];
    $on = array(); foreach (zh_map_city_list() as $name) { if (isset($coords[$name])) { $on[$name] = $coords[$name]; } }
    $W = (int) $map['w']; $H = (int) $map['h'];
    $pct = function ($xy) use ($W, $H) { return 'left:' . round($xy[0] / $W * 100, 2) . '%;top:' . round($xy[1] / $H * 100, 2) . '%'; };
    ob_start(); ?>
    <div class="map-stage">
        <svg class="kzmap" viewBox="0 0 <?php echo $W; ?> <?php echo $H; ?>" role="img" aria-label="Карта Казахстана: возим котов в любую точку страны, частые направления отмечены точками">
            <defs>
                <pattern id="kzdots" width="9" height="9" patternUnits="userSpaceOnUse"><circle cx="4.5" cy="4.5" r="1.25"/></pattern>
                <clipPath id="kzclip"><path d="<?php echo esc_attr($map['d']); ?>"/></clipPath>
            </defs>
            <g class="kz-dots" clip-path="url(#kzclip)"><rect width="<?php echo $W; ?>" height="<?php echo $H; ?>" fill="url(#kzdots)" style="fill:url(#kzdots)"/></g>
            <path class="kz-line" d="<?php echo esc_attr($map['d']); ?>"/>
            <g class="kz-cities">
                <?php foreach ($on as $name => $xy) : ?>
                    <g class="kz-city" data-city="<?php echo esc_attr($name); ?>" transform="translate(<?php echo (int) $xy[0]; ?> <?php echo (int) $xy[1]; ?>)"><circle class="kz-hit" r="22"/><circle class="kz-pin" r="5"/></g>
                <?php endforeach; ?>
            </g>
            <g class="kz-drop" aria-hidden="true"><circle class="kz-drop-ring" r="16"/><circle class="kz-drop-pin" r="6"/></g>
        </svg>
        <?php foreach ($on as $name => $xy) : ?>
            <span class="kz-label" data-city="<?php echo esc_attr($name); ?>" style="<?php echo $pct($xy); ?>"><?php echo esc_html($name); ?></span>
        <?php endforeach; ?>
        <span class="kz-drop-label" aria-hidden="true">Довезём и сюда</span>
        <p class="map-hint" aria-hidden="true">Нажмите на любое место на карте</p>
    </div>
    <?php
    return ob_get_clean();
}

function zh_map_city_list() {
    $s = zh_settings();
    return array_values(array_filter(array_map('trim', preg_split('/[,\n]+/u', (string) $s['cities']))));
}

/* ----------------------------------------------------------------- журнал */

function zh_journal_url() {
    $cat = get_category_by_slug('journal');
    if ($cat) { return get_category_link($cat); }
    return get_option('page_for_posts') ? get_permalink(get_option('page_for_posts')) : home_url('/');
}

/** Время чтения в минутах (около 180 слов в минуту). */
function zh_read_minutes($post = null) {
    $post = get_post($post);
    $text = wp_strip_all_tags((string) $post->post_content);
    $words = preg_match_all('/[\p{L}\p{N}]+/u', $text);
    return max(1, (int) ceil($words / 180));
}

/** Заголовок архива без служебных слов («Рубрика:», «Метка:») и без разметки. */
function zh_archive_title() {
    if (is_search()) { return 'Поиск'; }
    if (is_category() || is_tag() || is_tax()) { return single_term_title('', false); }
    if (is_author()) { return get_the_author(); }
    $title = wp_strip_all_tags(get_the_archive_title());
    return trim(preg_replace('/^[^:]+:\s*/u', '', $title));
}

/** Наклейка для статьи: одна и та же у одной записи, разные у разных. */

/**
 * Поза кота из набора темы (img/pets/{порода}-{поза}.webp) или основное изображение породы.
 * Если у породы загружено своё фото, позы не используются: показывается только оно.
 */
function zh_pose($breed, $pose) {
    $breed = get_post($breed);
    $main = zh_media($breed);
    if ($main['id']) { return $main['url']; }
    $file = 'pets/' . $breed->post_name . '-' . $pose . '.webp';
    return zh_has_img($file) ? zh_img($file) : $main['url'];
}
