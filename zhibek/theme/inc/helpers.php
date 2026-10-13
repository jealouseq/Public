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
    if (!empty($s['whatsapp'])) { $out['whatsapp'] = array('WhatsApp', 'https://wa.me/' . $s['whatsapp'] . '?text=' . rawurlencode('Здравствуйте! Хочу узнать о котятах ZHIBEK.')); }
    if (!empty($s['telegram'])) { $out['telegram'] = array('Telegram', 'https://t.me/' . $s['telegram']); }
    if (!empty($s['instagram'])) { $out['instagram'] = array('Instagram', $s['instagram']); }
    if (!empty($s['phone'])) { $out['phone'] = array($s['phone'], 'tel:' . preg_replace('/[^+0-9]/', '', $s['phone'])); }
    if (!empty($s['email'])) { $out['email'] = array($s['email'], 'mailto:' . $s['email']); }
    return $out;
}

/** Студийный кадр сцены с адаптивными размерами (640, 960 и полный). Файлы: name-640.webp, name-960.webp, name.webp, 1536×1024. */
function zh_scene_srcset($name) {
    return zh_img('scenes/' . $name . '-640.webp') . ' 640w, ' . zh_img('scenes/' . $name . '-960.webp') . ' 960w, ' . zh_img('scenes/' . $name . '.webp') . ' 1536w';
}

function zh_scene_img($name, $alt, $sizes, $attrs = '') {
    return '<img src="' . esc_url(zh_img('scenes/' . $name . '-960.webp')) . '" srcset="' . esc_attr(zh_scene_srcset($name)) . '" sizes="' . esc_attr($sizes) . '" width="1536" height="1024" alt="' . esc_attr($alt) . '" decoding="async" ' . $attrs . '>';
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

/** Экранированный текст с неразрывными пробелами. */
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

function zh_icon($name) {
    $paths = array(
        'bag' => '<path d="M5 8h14l1 13H4L5 8zM8 8V6a4 4 0 018 0v2"/>',
        'close' => '<path d="M5 5l14 14M19 5L5 19"/>',
        'whatsapp' => '<path d="M3.5 20.5l1.3-4.6A8.5 8.5 0 1112 20.5a8.4 8.4 0 01-4-1z"/><path d="M9 8.5c.3 3 2.5 5.2 5.5 6.5l1.5-1.5-2-1-1 .8c-.9-.4-1.8-1.3-2.2-2.2l.8-1-1-2z"/>',
        'telegram' => '<path d="M21 4L3 11l5.5 2L10 19l3-3.5L17.5 19 21 4z"/><path d="M8.5 13L21 4"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r=".6"/>',
        'doc' => '<path d="M6 3h8l4 4v14H6V3z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'chip' => '<rect x="7" y="7" width="10" height="10" rx="1.5"/><path d="M10 3v4M14 3v4M10 17v4M14 17v4M3 10h4M3 14h4M17 10h4M17 14h4"/>',
        'contract' => '<path d="M6 3h9l3 3v15H6V3z"/><path d="M9 9h6M9 13h6M9 17h3"/>',
        'gift' => '<rect x="4" y="9" width="16" height="11" rx="1.5"/><path d="M3 9h18v3H3zM12 9v11M12 9c-3 0-5-1-5-3a2 2 0 014 0c0 1 1 3 1 3zm0 0c3 0 5-1 5-3a2 2 0 00-4 0c0 1-1 3-1 3z"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4V5z"/><path d="M8 9.5h8M8 12.5h5"/>',
    );
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Знак: радужка с вертикальным зрачком. */
function zh_mark($class = 'mark', $accent = true) {
    return '<svg class="' . esc_attr($class) . '" viewBox="0 0 48 48" fill="none" aria-hidden="true"><circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2.2"/><path d="M24 8c6.6 6.4 6.6 25.6 0 32-6.6-6.4-6.6-25.6 0-32z" fill="' . ($accent ? '#a3481f' : 'currentColor') . '"/></svg>';
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

/** Цвет фона карточек породы. Для новых пород берётся «Фирменный цвет» из карточки породы. */
function zh_breed_color($post) {
    $post = $post ? get_post($post) : null;
    $slug = $post ? $post->post_name : '';
    $defaults = array('abyssinian' => '#b9c7b0', 'bengal' => '#e0c088', 'siamese' => '#a9bdd4');
    $color = $defaults[$slug] ?? '#d9cdb8';
    if ($post) {
        $custom = (string) get_post_meta($post->ID, '_zhibek_accent', true);
        if (preg_match('/^#[0-9a-f]{6}$/i', $custom) && !isset($defaults[$slug])) { $color = $custom; }
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
        'breed' => $breed, 'breed_name' => $breed ? $breed->post_title : '', 'breed_slug' => $breed ? $breed->post_name : '', 'color_hex' => zh_breed_color($breed),
        'status' => $status, 'status_label' => zh_status_label($status),
        'sex' => trim((string) get_post_meta($post->ID, '_zhibek_sex', true)), 'color' => trim((string) get_post_meta($post->ID, '_zhibek_color', true)),
        'birth' => (string) get_post_meta($post->ID, '_zhibek_birth', true), 'sire' => trim((string) get_post_meta($post->ID, '_zhibek_sire', true)), 'dam' => trim((string) get_post_meta($post->ID, '_zhibek_dam', true)),
        'price' => $price !== '' ? number_format((int) $price, 0, ',', ' ') . ' ₸' : '',
        'demo' => (bool) get_post_meta($post->ID, '_zhibek_demo', true), 'subtitle' => (string) get_post_meta($post->ID, '_zhibek_subtitle', true),
    );
}

/** Карточка котёнка: цветной фон породы, вырезанный кот или фото. */
function zh_kitten_card($post) {
    $k = zh_kitten_data($post);
    $m = zh_media($post, 'large');
    $age = zh_age($k['birth']);
    $meta = array_filter(array($k['sex'], $k['color'], $age));
    ob_start(); ?>
    <article class="kcard" data-breed="<?php echo (int) ($k['breed'] ? $k['breed']->ID : 0); ?>" data-status="<?php echo esc_attr($k['status']); ?>">
        <a class="kcard-link" href="<?php echo esc_url($k['url']); ?>">
            <span class="kcard-media" data-type="<?php echo esc_attr($m['type']); ?>" style="--bg:<?php echo esc_attr($k['color_hex']); ?>;--vt:k<?php echo (int) $k['id']; ?>">
                <?php if ($m['url']) : ?>
                    <img src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($k['title'] . ($k['breed_name'] ? ', ' . zh_lower($k['breed_name']) . ' кошка' : '')); ?>"<?php if ($m['w'] && $m['h']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> loading="lazy" decoding="async" draggable="false">
                <?php endif; ?>
                <span class="kcard-badges">
                    <?php if ($k['status_label']) : ?><span class="chip" data-status="<?php echo esc_attr($k['status']); ?>"><?php echo esc_html($k['status_label']); ?></span><?php endif; ?>
                    <?php if ($k['demo'] && zh_is_demo()) : ?><span class="chip chip-demo">Пример</span><?php endif; ?>
                </span>
            </span>
            <span class="kcard-body">
                <span class="kcard-breed"><?php echo esc_html($k['breed_name'] ?: 'ZHIBEK'); ?></span>
                <span class="kcard-name"><?php echo esc_html($k['title']); ?></span>
                <?php if ($meta) : ?><span class="kcard-meta"><?php echo esc_html(implode(' · ', $meta)); ?></span><?php endif; ?>
                <?php if ($k['price']) : ?><span class="kcard-price"><?php echo esc_html($k['price']); ?></span><?php endif; ?>
            </span>
        </a>
    </article>
    <?php
    return ob_get_clean();
}

function zh_render_kitten_cards($posts) {
    foreach ($posts as $p) { echo zh_kitten_card($p); }
}

/** Фильтры каталога по породам. */
function zh_kitten_filters($breeds, $kittens) {
    $counts = array();
    foreach ($kittens as $k) { $b = (int) get_post_meta($k->ID, '_zhibek_breed', true); $counts[$b] = ($counts[$b] ?? 0) + 1; }
    echo '<div class="filters" role="group" aria-label="Порода"><button class="filter is-active" type="button" data-filter="all" aria-pressed="true">Все <sup>' . count($kittens) . '</sup></button>';
    foreach ($breeds as $b) {
        if (empty($counts[$b->ID])) { continue; }
        echo '<button class="filter" type="button" data-filter="' . (int) $b->ID . '" data-slug="' . esc_attr($b->post_name) . '" aria-pressed="false">' . esc_html($b->post_title) . ' <sup>' . (int) $counts[$b->ID] . '</sup></button>';
    }
    echo '</div>';
}

/**
 * Панель породы: цветной фон, название, подпись, факты и кот на вырезанном фоне.
 * $hero = true — шапка страницы породы (заголовок h1, без закрепления при прокрутке).
 */
function zh_breed_panel($b, $i = 0, $total = 1, $hero = false, $heading = 'h3') {
    $m = zh_media($b);
    $facts = array_slice(zh_breed_facts($b), 0, 4);
    $name_l = zh_lower($b->post_title);
    $tag = $hero ? 'h1' : $heading;
    $kittens_url = add_query_arg('breed', $b->post_name, home_url('/kittens/'));
    ob_start(); ?>
    <article class="panel<?php echo $hero ? ' panel--hero' : ''; ?>" data-breed="<?php echo esc_attr($b->post_name); ?>" style="--i:<?php echo (int) $i; ?>;--p-bg:<?php echo esc_attr(zh_breed_color($b)); ?>">
        <div class="panel-copy">
            <div>
                <?php if ($hero) : ?>
                    <a class="back" href="<?php echo esc_url(home_url('/breeds/')); ?>"><?php echo zh_arrow('arr-back'); ?><span>Все породы</span></a>
                <?php else : ?>
                    <span class="panel-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?> / <?php echo esc_html(sprintf('%02d', $total)); ?></span>
                <?php endif; ?>
                <<?php echo $tag; ?> class="panel-title"><?php echo esc_html($b->post_title); ?></<?php echo $tag; ?>>
                <p class="panel-sub"><?php echo zh_t(get_post_meta($b->ID, '_zhibek_subtitle', true)); ?></p>
            </div>
            <?php if ($facts) : ?><dl class="panel-facts"><?php foreach ($facts as $f) : ?><div><dt><?php echo zh_t($f[0]); ?></dt><dd><?php echo zh_t($f[1]); ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
            <div class="panel-cta">
                <a class="btn btn-primary" href="<?php echo esc_url($kittens_url); ?>"><span>Котята: <?php echo esc_html($name_l); ?></span><?php echo zh_arrow(); ?></a>
                <?php if ($hero) : ?>
                    <button class="btn btn-outline" type="button" data-enquire="<?php echo esc_attr($b->post_title); ?>"><span>Задать вопрос</span></button>
                <?php else : ?>
                    <a class="btn btn-outline" href="<?php echo esc_url(get_permalink($b)); ?>"><span>О породе</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="panel-art"<?php echo $hero ? '' : ' aria-hidden="true"'; ?>>
            <?php if ($m['url']) : ?><img src="<?php echo esc_url($m['url']); ?>" alt="<?php echo $hero ? esc_attr($b->post_title . ' кошка') : ''; ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?><?php echo $hero ? ' fetchpriority="high"' : ' loading="lazy"'; ?> decoding="async" draggable="false"><?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
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
        <g class="kz-dots" clip-path="url(#kzclip)"><rect width="<?php echo (int) $map['w']; ?>" height="<?php echo (int) $map['h']; ?>" fill="url(#kzdots)" style="fill:url(#kzdots)"/></g>
        <path class="kz-line" d="<?php echo esc_attr($map['d']); ?>"/>
        <g class="kz-cities">
            <?php foreach ($map['cities'] as $name => $xy) : $on = in_array($name, $active, true); if (!$on) { continue; } ?>
                <g class="kz-city<?php echo $on ? ' is-on' : ''; ?>" transform="translate(<?php echo (int) $xy[0]; ?> <?php echo (int) $xy[1]; ?>)">
                    <circle class="kz-pulse" r="14"/><circle class="kz-pin" r="5"/>
                    <text class="kz-label" x="11" y="4"><?php echo esc_html($name); ?></text>
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
