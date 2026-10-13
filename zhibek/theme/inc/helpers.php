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

function zh_is_demo() { return function_exists('zhibek_is_demo') ? zhibek_is_demo() : true; }
function zh_breeds() { return function_exists('zhibek_get_breeds') ? zhibek_get_breeds() : array(); }
function zh_cats($args = array()) { return function_exists('zhibek_get_cats') ? zhibek_get_cats($args) : array(); }

function zh_wc_url($page) {
    return function_exists('wc_get_page_permalink') ? wc_get_page_permalink($page) : home_url('/' . ($page === 'myaccount' ? 'my-account' : $page) . '/');
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
        'bag' => '<path d="M5 8h14l1 13H4L5 8zM8 8V6a4 4 0 018 0v2"/>',
        'close' => '<path d="M5 5l14 14M19 5L5 19"/>',
        'whatsapp' => '<path d="M3.5 20.5l1.3-4.6A8.5 8.5 0 1112 20.5a8.4 8.4 0 01-4-1z"/><path d="M9 8.5c.3 3 2.5 5.2 5.5 6.5l1.5-1.5-2-1-1 .8c-.9-.4-1.8-1.3-2.2-2.2l.8-1-1-2z"/>',
        'telegram' => '<path d="M21 4L3 11l5.5 2L10 19l3-3.5L17.5 19 21 4z"/><path d="M8.5 13L21 4"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r=".6"/>',
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

/** Наклейка из набора (img/stickers/*.webp). */
function zh_sticker($name, $class = '') {
    $file = 'stickers/' . $name . '.webp';
    if (!zh_has_img($file)) { return ''; }
    $size = @getimagesize(get_template_directory() . '/assets/img/' . $file);
    return '<img class="sticker ' . esc_attr($class) . '" src="' . esc_url(zh_img($file)) . '" alt=""' . ($size ? ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"' : '') . ' loading="lazy" decoding="async" draggable="false" aria-hidden="true">';
}

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
    return array('url' => '', 'small' => '', 'w' => 0, 'h' => 0, 'type' => 'none', 'id' => 0);
}

/** Подписи поз для демонстрационной галереи породы. */
function zh_pose_notes() {
    return array('sit' => 'сидит', 'stand' => 'стоит', 'lie' => 'отдыхает', 'stretch' => 'потягивается', 'walk' => 'идёт по делам', 'up' => 'смотрит вверх', 'paw' => 'здоровается', 'lick' => 'умывается', 'play' => 'готов играть', 'sleep' => 'спит');
}

/**
 * Галерея породы: загруженные владельцем фотографии или, пока их нет и включён демо-режим, иллюстрации из темы.
 * Элемент: type (cutout|photo), url, small, w, h, alt, note.
 */
function zh_breed_gallery($breed) {
    $breed = get_post($breed);
    $items = array();
    if (function_exists('zh_gallery_items')) {
        $has_uploads = (bool) zh_public_thumbnail_id($breed) || (bool) get_post_meta($breed->ID, '_zhibek_gallery_ids', true);
        if ($has_uploads) {
            foreach (zh_gallery_items($breed) as $it) { $items[] = array('type' => $it['cutout'] ? 'cutout' : 'photo', 'url' => $it['preview'], 'small' => $it['preview'], 'w' => $it['w'], 'h' => $it['h'], 'alt' => $it['alt'], 'note' => $it['caption']); }
            return $items;
        }
    }
    if (!zh_is_demo()) { return array(); }
    $slug = $breed->post_name; $notes = zh_pose_notes();
    $photos = array(
        'abyssinian' => array('abyssinian-blue' => 'голубой окрас', 'abyssinian-portrait' => 'руди, портрет'),
        'bengal' => array('bengal-table' => 'браун, розетки', 'bengal-studio' => 'любопытство'),
        'siamese' => array(),
    );
    foreach (array('stretch', 'play', 'up', 'lick', 'paw', 'lie', 'walk', 'sleep') as $pose) {
        $f = 'pets/' . $slug . '-' . $pose . '.webp';
        if (!zh_has_img($f)) { continue; }
        $sz = @getimagesize(get_template_directory() . '/assets/img/' . $f);
        $sm = 'pets/' . $slug . '-' . $pose . '-sm.webp';
        $items[] = array('type' => 'cutout', 'url' => zh_img($f), 'small' => zh_has_img($sm) ? zh_img($sm) : zh_img($f), 'w' => $sz[0], 'h' => $sz[1], 'alt' => $breed->post_title . ': ' . ($notes[$pose] ?? ''), 'note' => $notes[$pose] ?? '');
    }
    foreach (($photos[$slug] ?? array()) as $file => $note) {
        $f = 'photos/' . $file . '.webp';
        if (!zh_has_img($f)) { continue; }
        $sz = @getimagesize(get_template_directory() . '/assets/img/' . $f);
        array_splice($items, 2, 0, array(array('type' => 'photo', 'url' => zh_img($f), 'small' => zh_img($f), 'w' => $sz[0], 'h' => $sz[1], 'alt' => $breed->post_title . ', ' . $note, 'note' => $note)));
    }
    return $items;
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
function zh_scene($variant, $color, $title_html, $lead, $actions_html) {
    $cfg = array(
        'bottom' => array('dir' => 'bengal-bottom', 'n' => 72, 'poster' => 60, 'alt' => 'Бенгальский кот выглядывает снизу'),
        'right' => array('dir' => 'bengal-right', 'n' => 56, 'poster' => 30, 'alt' => 'Бенгальский кот выглядывает сбоку'),
    );
    if (!isset($cfg[$variant])) { return ''; }
    $c = $cfg[$variant];
    $poster = sprintf('anim/%s/frame_%04d.webp', $c['dir'], $c['poster']);
    if (!is_file(get_template_directory() . '/assets/' . $poster)) { return ''; }
    ob_start(); ?>
    <section class="scene scene--<?php echo esc_attr($variant); ?>" data-scene data-frames="<?php echo (int) $c['n']; ?>" data-base="<?php echo esc_url(zh_asset('anim/' . $c['dir'] . '/')); ?>" style="--bg:<?php echo esc_attr($color); ?>">
        <div class="scene-sticky">
            <div class="scene-text">
                <h2 class="h2"><?php echo wp_kses($title_html, array('em' => array())); ?></h2>
                <p class="lead"><?php echo zh_t($lead); ?></p>
                <div class="scene-cta"><?php echo $actions_html; ?></div>
            </div>
            <div class="scene-cat">
                <canvas width="800" height="600" aria-hidden="true"></canvas>
                <img src="<?php echo esc_url(zh_asset($poster)); ?>" alt="<?php echo esc_attr($c['alt']); ?>" width="800" height="600" loading="lazy" decoding="async" draggable="false">
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/* ------------------------------------------------------------------ карта */

function zh_map_svg() {
    require_once get_template_directory() . '/inc/map.php';
    $map = zh_kz_map();
    $s = zh_settings();
    $active = array_filter(array_map('trim', preg_split('/[,\n]+/u', (string) $s['cities'])));
    ob_start(); ?>
    <svg class="kzmap" viewBox="0 0 <?php echo (int) $map['w']; ?> <?php echo (int) $map['h']; ?>" role="img" aria-label="Карта Казахстана с городами, куда мы привозим котов">
        <defs>
            <pattern id="kzdots" width="9" height="9" patternUnits="userSpaceOnUse"><circle cx="4.5" cy="4.5" r="1.25"/></pattern>
            <clipPath id="kzclip"><path d="<?php echo esc_attr($map['d']); ?>"/></clipPath>
        </defs>
        <g class="kz-dots" clip-path="url(#kzclip)"><rect width="<?php echo (int) $map['w']; ?>" height="<?php echo (int) $map['h']; ?>" fill="url(#kzdots)" style="fill:url(#kzdots)"/></g>
        <path class="kz-line" d="<?php echo esc_attr($map['d']); ?>"/>
        <g class="kz-cities">
            <?php foreach ($map['cities'] as $name => $xy) : if (!in_array($name, $active, true)) { continue; } ?>
                <g class="kz-city is-on" transform="translate(<?php echo (int) $xy[0]; ?> <?php echo (int) $xy[1]; ?>)"><circle class="kz-pulse" r="14"/><circle class="kz-pin" r="5"/></g>
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
