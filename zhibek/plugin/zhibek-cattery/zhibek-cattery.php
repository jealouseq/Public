<?php
/**
 * Plugin Name: ZHIBEK Cattery
 * Description: Породы, котята, заявки и демо-контент для сайта питомника ZHIBEK. WooCommerce используется только для товаров магазина.
 * Version: 2.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: ZHIBEK
 * Text Domain: zhibek-cattery
 * License: GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

/** Public theme interfaces. */
function zhibek_default_settings() {
    return array(
        'demo' => true, 'phone' => '', 'whatsapp' => '', 'telegram' => '', 'instagram' => '', 'email' => '',
        'city' => 'Казахстан', 'cities' => 'Алматы, Астана, Шымкент, Караганда, Актобе, Атырау, Павлодар, Усть-Каменогорск, Костанай, Актау',
    );
}

function zhibek_get_settings() {
    $saved = get_option('zhibek_settings', array());
    $settings = array_merge(zhibek_default_settings(), is_array($saved) ? $saved : array());
    $settings['demo'] = in_array($settings['demo'], array(true, 1, '1', 'on', 'yes', 'true'), true);
    return $settings;
}

function zhibek_is_demo() {
    return (bool) zhibek_get_settings()['demo'];
}

function zhibek_get_breeds($args = array()) {
    return get_posts(array_merge(array('post_type' => 'zhibek_breed', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC'), $args));
}

function zhibek_get_kittens($args = array()) {
    $defaults = array('post_type' => 'zhibek_kitten', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC');
    // Sample cards disappear from the public catalogue as soon as the owner switches demo mode off.
    if (!zhibek_is_demo()) {
        $defaults['meta_query'] = array(array('key' => '_zhibek_demo', 'compare' => 'NOT EXISTS'));
    }
    return get_posts(array_merge($defaults, $args));
}

/** Every public field of breeds and kittens; the theme reads them through get_post_meta(). */
function zhibek_meta_keys() {
    return array(
        '_zhibek_subtitle', '_zhibek_traits', '_zhibek_image', '_zhibek_status', '_zhibek_sex', '_zhibek_color', '_zhibek_birth', '_zhibek_breed',
        '_zhibek_facts', '_zhibek_eye', '_zhibek_accent', '_zhibek_fit', '_zhibek_note', '_zhibek_sire', '_zhibek_dam', '_zhibek_price', '_zhibek_cutout',
    );
}

function zhibek_register_content() {
    register_post_type('zhibek_breed', array(
        'labels' => array('name' => 'Породы', 'singular_name' => 'Порода', 'add_new_item' => 'Добавить породу', 'edit_item' => 'Редактировать породу'),
        'public' => true, 'show_in_rest' => true, 'has_archive' => true,
        'rewrite' => array('slug' => 'breeds', 'with_front' => false),
        'menu_icon' => 'dashicons-pets', 'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields'),
    ));
    register_post_type('zhibek_kitten', array(
        'labels' => array('name' => 'Котята', 'singular_name' => 'Котёнок', 'add_new_item' => 'Добавить котёнка', 'edit_item' => 'Редактировать котёнка'),
        'public' => true, 'show_in_rest' => true, 'has_archive' => true,
        'rewrite' => array('slug' => 'kittens', 'with_front' => false),
        'menu_icon' => 'dashicons-heart', 'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'),
    ));
    register_taxonomy('zhibek_breed_type', 'zhibek_kitten', array(
        'labels' => array('name' => 'Породы котят', 'singular_name' => 'Порода котёнка'),
        'public' => true, 'show_ui' => false, 'show_in_rest' => true,
        'rewrite' => array('slug' => 'kitten-breed', 'with_front' => false), 'hierarchical' => false,
    ));
    register_post_type('zhibek_enquiry', array(
        'labels' => array('name' => 'Заявки ZHIBEK', 'singular_name' => 'Заявка', 'edit_item' => 'Просмотр заявки'),
        'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
        'show_ui' => true, 'show_in_rest' => false, 'has_archive' => false, 'rewrite' => false,
        'query_var' => false, 'menu_icon' => 'dashicons-email-alt', 'supports' => array('title'),
        'map_meta_cap' => false,
        'capabilities' => array(
            'edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options',
            'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options',
            'edit_private_posts' => 'manage_options', 'edit_published_posts' => 'manage_options', 'create_posts' => 'do_not_allow',
        ),
    ));
    foreach (array('zhibek_breed', 'zhibek_kitten') as $type) {
        foreach (zhibek_meta_keys() as $key) {
            register_post_meta($type, $key, array(
                'type' => '_zhibek_breed' === $key ? 'integer' : 'string', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => 'zhibek_sanitize_content_meta',
                'auth_callback' => function ($allowed, $meta_key, $post_id) { return current_user_can('edit_post', $post_id); },
            ));
        }
    }
}
add_action('init', 'zhibek_register_content');

/** Never allow an enquiry to become public, including through an admin update. */
function zhibek_force_private_enquiry($data) {
    if (isset($data['post_type']) && 'zhibek_enquiry' === $data['post_type'] && 'trash' !== $data['post_status']) {
        $data['post_status'] = 'private';
    }
    return $data;
}
add_filter('wp_insert_post_data', 'zhibek_force_private_enquiry');

function zhibek_activate() {
    add_option('zhibek_settings', zhibek_default_settings());
    zhibek_register_content();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'zhibek_activate');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

/** Count characters rather than UTF-8 bytes on installations without mbstring. */
function zhibek_text_length($value) {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value, $matches);
}

function zhibek_text_limit($value, $limit) {
    if (function_exists('mb_substr')) { return mb_substr($value, 0, $limit, 'UTF-8'); }
    return preg_match('/^.{0,' . (int) $limit . '}/us', $value, $match) ? $match[0] : '';
}

/** Validate before persistence; oversized fields are rejected instead of silently truncated. */
function zhibek_validate_enquiry($input) {
    if (!is_array($input)) {
        return new WP_Error('invalid_input', 'Проверьте поля формы.');
    }
    $limits = array('name' => 100, 'contact' => 180, 'city' => 100, 'breed' => 100, 'message' => 2000, 'website' => 200);
    $clean = array();
    foreach ($limits as $field => $limit) {
        $value = isset($input[$field]) ? $input[$field] : '';
        if (!is_string($value) && !is_numeric($value)) {
            return new WP_Error('invalid_input', 'Проверьте поля формы.');
        }
        $value = (string) $value;
        if (zhibek_text_length($value) > $limit) {
            return new WP_Error('field_too_long', 'Одно из полей слишком длинное. Сообщение — до 2000 символов.');
        }
        if ('website' === $field && '' !== trim($value)) {
            return new WP_Error('spam', 'Не удалось отправить форму. Обновите страницу и попробуйте ещё раз.');
        }
        $clean[$field] = 'message' === $field ? sanitize_textarea_field($value) : sanitize_text_field($value);
    }
    if ('' !== trim($clean['website'])) {
        return new WP_Error('spam', 'Не удалось отправить форму. Обновите страницу и попробуйте ещё раз.');
    }
    if (!isset($input['consent']) || !in_array($input['consent'], array(true, 1, '1', 'on', 'yes', 'true'), true)) {
        return new WP_Error('consent_required', 'Подтвердите согласие на обработку данных заявки.');
    }
    if (zhibek_text_length($clean['name']) < 2) {
        return new WP_Error('name_required', 'Укажите имя — минимум 2 символа.');
    }
    $contact = $clean['contact'];
    $raw_contact = isset($input['contact']) ? trim((string) $input['contact']) : '';
    $digits = preg_replace('/\D/', '', $contact);
    $parentheses = preg_replace('/\([0-9\s]+\)/', '', $contact);
    $is_phone = !preg_match('/[\x00-\x1F\x7F]/', $raw_contact)
        && preg_match('/^\+?[0-9().\s-]+$/D', $contact)
        && !preg_match('/[()]/', $parentheses)
        && strlen($digits) >= 7 && strlen($digits) <= 15
        && preg_match('/[1-9]/', $digits)
        && ('+' !== substr($contact, 0, 1) || '0' !== substr($digits, 0, 1));
    if (preg_match('/[\x00-\x1F\x7F]/', $raw_contact)) {
        return new WP_Error('contact_required', 'Укажите действующий email или телефон с кодом страны.');
    }
    if (!is_email($contact) && !$is_phone) {
        return new WP_Error('contact_required', 'Укажите действующий email или телефон с кодом страны.');
    }
    if (is_email($contact)) {
        $clean['contact'] = sanitize_email($contact);
    }
    $clean['consent'] = true;
    unset($clean['website']);
    return $clean;
}

/** Use the direct peer address; forwarded headers cannot bypass the limit. No IP is saved in the enquiry. */
function zhibek_enquiry_rate_limit() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $key = 'zhibek_rate_' . hash_hmac('sha256', $ip, wp_salt('nonce'));
    $lock = $key . '_lock';
    $now = time();
    if (!add_option($lock, $now + 30, '', false)) {
        // The unique option key serializes concurrent requests even without a persistent object cache.
        global $wpdb;
        $expired = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $lock, $now));
        if ($expired) { wp_cache_delete($lock, 'options'); }
        if (!$expired || !add_option($lock, $now + 30, '', false)) {
            return new WP_Error('rate_limit', 'Форма уже отправляется. Подождите немного и попробуйте снова.');
        }
    }
    try {
        $count = (int) get_transient($key);
        if ($count >= 5) {
            return new WP_Error('rate_limit', 'Слишком много попыток. Попробуйте снова через 15 минут.');
        }
        set_transient($key, $count + 1, 15 * MINUTE_IN_SECONDS);
    } finally {
        delete_option($lock);
    }
    return true;
}

/** Storage success and email success are deliberately separate. */
function zhibek_store_enquiry($data) {
    $content = "Имя: {$data['name']}\nКонтакт: {$data['contact']}\nГород: {$data['city']}\nПорода: {$data['breed']}\n\n{$data['message']}\n\nСогласие на обработку данных: предоставлено.";
    $id = wp_insert_post(wp_slash(array(
        'post_type' => 'zhibek_enquiry', 'post_status' => 'private',
        'post_title' => 'Заявка — ' . $data['name'] . ' — ' . current_time('Y-m-d H:i'),
        'post_content' => $content,
        'meta_input' => array(
            '_zhibek_name' => $data['name'], '_zhibek_contact' => $data['contact'], '_zhibek_city' => $data['city'],
            '_zhibek_breed' => $data['breed'], '_zhibek_message' => $data['message'],
            '_zhibek_consent_at' => current_time('mysql', true), '_zhibek_email_sent' => '0',
            '_zhibek_enquiry_status' => 'new',
        ),
    )), true);
    if (is_wp_error($id) || !$id) {
        return new WP_Error('save_failed', 'Заявка не сохранена. Попробуйте ещё раз позднее.');
    }
    $recipient = sanitize_email((string) get_option('admin_email'));
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    if (is_email($data['contact'])) {
        $headers[] = 'Reply-To: ' . sanitize_email($data['contact']);
    }
    $sent = false;
    if (is_email($recipient)) {
        try {
            $sent = (bool) wp_mail($recipient, 'Новая заявка ZHIBEK №' . $id, $content, $headers);
        } catch (Throwable $exception) {
            $sent = false;
        }
    }
    update_post_meta($id, '_zhibek_email_sent', $sent ? '1' : '0');
    return array(
        'enquiry_id' => (int) $id, 'email_sent' => $sent, 'saved' => true,
        'message' => $sent ? 'Заявка сохранена и отправлена владельцу питомника.' : 'Заявка сохранена, но письмо владельцу отправить не удалось. Он сможет прочитать её в панели сайта.',
    );
}

function zhibek_handle_enquiry() {
    if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
        wp_send_json_error(array('message' => 'Используйте POST для отправки формы.'), 405);
    }
    $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zh_enquiry')) {
        wp_send_json_error(array('message' => 'Сессия формы истекла. Обновите страницу.'), 403);
    }
    $rate = zhibek_enquiry_rate_limit();
    if (is_wp_error($rate)) {
        wp_send_json_error(array('message' => $rate->get_error_message()), 429);
    }
    $data = zhibek_validate_enquiry(wp_unslash($_POST));
    if (is_wp_error($data)) {
        wp_send_json_error(array('message' => $data->get_error_message()), 422);
    }
    $result = zhibek_store_enquiry($data);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 500);
    }
    wp_send_json_success($result);
}
/** Свежий nonce для формы: на сайтах с кэшем страниц встроенный в HTML nonce может устареть. */
function zhibek_fresh_nonce() {
    nocache_headers();
    wp_send_json_success(array('nonce' => wp_create_nonce('zh_enquiry')));
}
add_action('wp_ajax_zhibek_nonce', 'zhibek_fresh_nonce');
add_action('wp_ajax_nopriv_zhibek_nonce', 'zhibek_fresh_nonce');
add_action('wp_ajax_zhibek_enquiry', 'zhibek_handle_enquiry');
add_action('wp_ajax_nopriv_zhibek_enquiry', 'zhibek_handle_enquiry');

/** Settings remain empty until the owner supplies real contacts. */
function zhibek_sanitize_settings($input) {
    $input = is_array($input) ? $input : array();
    $get = function ($field) use ($input) { return isset($input[$field]) && is_scalar($input[$field]) ? (string) $input[$field] : ''; };
    $cities = array_filter(array_map('trim', preg_split('/[,\n]+/u', $get('cities'))));
    $cities = array_slice(array_unique($cities), 0, 24);
    return array(
        'demo' => isset($input['demo']) && in_array($input['demo'], array(true, 1, '1', 'on'), true),
        'phone' => zhibek_text_limit(sanitize_text_field($get('phone')), 80),
        'whatsapp' => zhibek_text_limit(preg_replace('/[^0-9]/', '', $get('whatsapp')), 15),
        'telegram' => zhibek_text_limit(preg_replace('/[^A-Za-z0-9_]/', '', ltrim(trim($get('telegram')), '@')), 40),
        'instagram' => esc_url_raw($get('instagram'), array('https', 'http')),
        'email' => sanitize_email($get('email')),
        'city' => zhibek_text_limit(sanitize_text_field($get('city')), 100),
        'cities' => zhibek_text_limit(implode(', ', array_map('sanitize_text_field', $cities)), 600),
    );
}

function zhibek_register_settings() {
    register_setting('zhibek_settings_group', 'zhibek_settings', array('sanitize_callback' => 'zhibek_sanitize_settings', 'type' => 'array', 'show_in_rest' => false));
}
add_action('admin_init', 'zhibek_register_settings');
function zhibek_settings_menu() {
    add_options_page('ZHIBEK', 'ZHIBEK', 'manage_options', 'zhibek-settings', 'zhibek_settings_page');
}
add_action('admin_menu', 'zhibek_settings_menu');
function zhibek_settings_page() {
    if (!current_user_can('manage_options')) { return; }
    $settings = zhibek_get_settings();
    $notice_key = 'zhibek_import_notice_' . get_current_user_id();
    $notice = get_transient($notice_key);
    if (is_array($notice)) { delete_transient($notice_key); }
    ?>
    <div class="wrap"><h1>ZHIBEK</h1>
    <?php if (is_array($notice) && isset($notice['message'], $notice['level'])) : ?>
        <div class="notice notice-<?php echo esc_attr(in_array($notice['level'], array('success', 'warning', 'error'), true) ? $notice['level'] : 'info'); ?> is-dismissible"><p><?php echo esc_html($notice['message']); ?></p></div>
    <?php endif; ?>
    <?php if ($settings['demo']) : ?>
        <div class="notice notice-warning inline"><p><strong>Демо-режим включён.</strong> Котята, родословная, товары и фотографии на сайте — примеры. Перед запуском добавьте настоящих котят и свои фотографии, укажите контакты ниже и выключите демо-режим.</p></div>
    <?php endif; ?>
    <?php if (!class_exists('WooCommerce')) : ?>
        <div class="notice notice-info inline"><p><strong>WooCommerce не активен.</strong> Импорт создаст породы, котят, статьи и страницы. Чтобы появился магазин, установите и активируйте WooCommerce и повторите импорт.</p></div>
    <?php elseif ('KZT' !== get_option('woocommerce_currency')) : ?>
        <div class="notice notice-info inline"><p>Магазин сохраняет выбранную вами валюту. Для цен в тенге выберите KZT в <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings')); ?>">основных настройках WooCommerce</a> до импорта товаров.</p></div>
    <?php endif; ?>
    <form action="options.php" method="post">
        <?php settings_fields('zhibek_settings_group'); ?>
        <table class="form-table" role="presentation">
            <tr><th scope="row">Демонстрационный режим</th><td><label><input type="checkbox" name="zhibek_settings[demo]" value="1" <?php checked($settings['demo']); ?>> Показывать демо-котят и пометку «пример»</label><p class="description">Когда вы добавите настоящих котят, выключите режим — демо-карточки исчезнут с сайта.</p></td></tr>
            <tr><th scope="row"><label for="zhibek-whatsapp">WhatsApp (номер, только цифры)</label></th><td><input id="zhibek-whatsapp" class="regular-text" name="zhibek_settings[whatsapp]" value="<?php echo esc_attr($settings['whatsapp']); ?>" maxlength="15" placeholder="77011234567" inputmode="numeric"></td></tr>
            <tr><th scope="row"><label for="zhibek-telegram">Telegram (имя пользователя)</label></th><td><input id="zhibek-telegram" class="regular-text" name="zhibek_settings[telegram]" value="<?php echo esc_attr($settings['telegram']); ?>" maxlength="40" placeholder="zhibek_cattery"></td></tr>
            <tr><th scope="row"><label for="zhibek-email">Email для посетителей</label></th><td><input id="zhibek-email" type="email" class="regular-text" name="zhibek_settings[email]" value="<?php echo esc_attr($settings['email']); ?>" maxlength="120"></td></tr>
            <tr><th scope="row"><label for="zhibek-cities">Города на карте</label></th><td><textarea id="zhibek-cities" class="large-text" rows="3" name="zhibek_settings[cities]"><?php echo esc_textarea($settings['cities']); ?></textarea><p class="description">Города через запятую, куда вы передаёте или привозите котят. Подсвечиваются на карте Казахстана; доступны: Алматы, Астана, Шымкент, Караганда, Актобе, Атырау, Актау, Павлодар, Усть-Каменогорск, Семей, Костанай, Петропавловск, Кокшетау, Уральск, Кызылорда, Туркестан, Тараз, Талдыкорган.</p></td></tr>
            <tr><th scope="row"><label for="zhibek-phone">Телефон</label></th><td><input id="zhibek-phone" class="regular-text" name="zhibek_settings[phone]" value="<?php echo esc_attr($settings['phone']); ?>" maxlength="80" autocomplete="tel"></td></tr>
            <tr><th scope="row"><label for="zhibek-city">Город</label></th><td><input id="zhibek-city" class="regular-text" name="zhibek_settings[city]" value="<?php echo esc_attr($settings['city']); ?>" maxlength="100"></td></tr>
            <tr><th scope="row"><label for="zhibek-instagram">Instagram URL</label></th><td><input id="zhibek-instagram" type="url" class="regular-text" name="zhibek_settings[instagram]" value="<?php echo esc_attr($settings['instagram']); ?>" placeholder="https://www.instagram.com/your-account/"></td></tr>
        </table>
        <?php submit_button('Сохранить'); ?>
    </form>
    <hr><h2>Демонстрационный контент</h2>
    <p>Создаёт три породы, три карточки котят-примеров, три статьи журнала и страницы «Конфиденциальность» и «Доставка и оплата». При активном WooCommerce добавляет четыре товара-примера.</p>
    <p>Повторный запуск ничего не дублирует и не затирает ваши правки. Платёжные шлюзы не включаются.</p>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="zhibek_import_demo">
        <?php wp_nonce_field('zhibek_import_demo', 'zhibek_import_nonce'); ?>
        <?php submit_button('Создать демонстрационный контент', 'secondary', 'submit', false); ?>
    </form></div>
    <?php
}

/** Dashboard alternative to WP-CLI; activation never imports content. */
function zhibek_handle_demo_import() {
    if (!current_user_can('manage_options')) {
        wp_die('Импорт доступен только администратору сайта.', 'ZHIBEK', array('response' => 403));
    }
    if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
        wp_die('Используйте кнопку импорта в настройках ZHIBEK.', 'ZHIBEK', array('response' => 405));
    }
    $nonce = isset($_POST['zhibek_import_nonce']) && is_string($_POST['zhibek_import_nonce']) ? sanitize_text_field(wp_unslash($_POST['zhibek_import_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'zhibek_import_demo')) {
        wp_die('Сессия импорта истекла. Откройте настройки ZHIBEK и попробуйте снова.', 'ZHIBEK', array('response' => 403));
    }
    try {
        $result = zhibek_seed_content();
        $notice = array(
            'level' => $result['woocommerce'] ? 'success' : 'warning',
            'message' => 'Демо-контент создан. Ваши правки сохранены, дубликатов нет. ' . ($result['woocommerce'] ? 'Товары-примеры добавлены в WooCommerce.' : 'WooCommerce не активен, поэтому товаров нет: активируйте его и повторите импорт.'),
        );
    } catch (Throwable $exception) {
        $notice = array('level' => 'error', 'message' => 'Не удалось завершить импорт. Уже созданные записи сохранены. Проверьте работу WordPress и повторите импорт: существующий контент не будет продублирован.');
    }
    set_transient('zhibek_import_notice_' . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('options-general.php?page=zhibek-settings'));
    exit;
}
add_action('admin_post_zhibek_import_demo', 'zhibek_handle_demo_import');
add_action('admin_post_nopriv_zhibek_import_demo', 'zhibek_handle_demo_import');

function zhibek_add_meta_boxes() {
    foreach (array('zhibek_breed', 'zhibek_kitten') as $type) {
        add_meta_box('zhibek-details', 'Данные ZHIBEK', 'zhibek_details_meta_box', $type, 'normal', 'high');
    }
    add_meta_box('zhibek-enquiry', 'Содержание заявки', 'zhibek_enquiry_meta_box', 'zhibek_enquiry', 'normal', 'high');
}
add_action('add_meta_boxes', 'zhibek_add_meta_boxes');

function zhibek_details_meta_box($post) {
    wp_nonce_field('zhibek_save_meta', 'zhibek_meta_nonce');
    $is_breed = 'zhibek_breed' === $post->post_type;
    $text = array('_zhibek_subtitle' => $is_breed ? 'Подпись (одна строка под названием)' : 'Подпись');
    if ($is_breed) {
        $text['_zhibek_traits'] = 'Характер — три-четыре слова через запятую';
    } else {
        $text += array('_zhibek_sex' => 'Пол', '_zhibek_color' => 'Окрас', '_zhibek_birth' => 'Дата рождения', '_zhibek_sire' => 'Отец (имя)', '_zhibek_dam' => 'Мать (имя)', '_zhibek_price' => 'Цена в тенге (только цифры, можно оставить пустым)');
    }
    $text['_zhibek_image'] = 'Файл-заглушка из темы (необязательно)';
    $cutout_checked = get_post_meta($post->ID, '_zhibek_cutout', true) ? ' checked' : '';
    echo '<p>Основную фотографию загрузите в блоке «Изображение записи». Для вырезанного на прозрачном фоне кота главная страница использует тот же файл — лучше всего подходит PNG/WebP с прозрачностью. Не указывайте вымышленные факты о животном.</p>';
    foreach ($text as $key => $label) {
        $type = '_zhibek_birth' === $key ? 'date' : 'text';
        echo '<p><label for="' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br><input class="widefat" id="' . esc_attr($key) . '" name="zhibek_meta[' . esc_attr($key) . ']" type="' . esc_attr($type) . '" value="' . esc_attr(get_post_meta($post->ID, $key, true)) . '"></p>';
    }
    echo '<p><label><input type="checkbox" name="zhibek_meta[_zhibek_cutout]" value="1"' . $cutout_checked . '> <strong>Главное фото вырезано на прозрачном фоне</strong> (PNG или WebP с прозрачностью). Тогда сайт поставит животное на фирменный фон породы. Обычная фотография показывается как есть.</label></p>';
    if ($is_breed) {
        $areas = array(
            '_zhibek_facts' => array('Факты (по одному на строку: «Название: значение»)', "Вес: 3,5–5 кг\nШерсть: короткая, шелковистая"),
            '_zhibek_fit' => array('Кому подойдёт (одно-два предложения)', ''),
            '_zhibek_note' => array('Что нужно знать (одно-два предложения)', ''),
        );
        foreach ($areas as $key => $meta) {
            echo '<p><label for="' . esc_attr($key) . '"><strong>' . esc_html($meta[0]) . '</strong></label><br><textarea class="widefat" rows="4" id="' . esc_attr($key) . '" name="zhibek_meta[' . esc_attr($key) . ']" placeholder="' . esc_attr($meta[1]) . '">' . esc_textarea(get_post_meta($post->ID, $key, true)) . '</textarea></p>';
        }
        foreach (array('_zhibek_eye' => 'Цвет глаз породы (для анимации взгляда), например #d9a441', '_zhibek_accent' => 'Фирменный цвет породы на сайте, например #c98a4b') as $key => $label) {
            echo '<p><label for="' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br><input class="regular-text" id="' . esc_attr($key) . '" name="zhibek_meta[' . esc_attr($key) . ']" type="text" maxlength="7" placeholder="#rrggbb" value="' . esc_attr(get_post_meta($post->ID, $key, true)) . '"></p>';
        }
    } else {
        echo '<p><label for="_zhibek_status"><strong>Статус</strong></label><br><select name="zhibek_meta[_zhibek_status]" id="_zhibek_status">';
        foreach (array('available' => 'Свободен', 'reserved' => 'В резерве', 'expected' => 'Ожидается', 'home' => 'В новой семье') as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected(get_post_meta($post->ID, '_zhibek_status', true), $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p><p><label for="_zhibek_breed"><strong>Порода</strong></label><br><select name="zhibek_meta[_zhibek_breed]" id="_zhibek_breed"><option value="0">Не выбрана</option>';
        foreach (zhibek_get_breeds(array('post_status' => array('publish', 'draft', 'pending', 'private', 'future'))) as $breed) {
            if ('publish' !== $breed->post_status && !current_user_can('edit_post', $breed->ID)) { continue; }
            $label = $breed->post_title;
            if ('publish' !== $breed->post_status) {
                $status = get_post_status_object($breed->post_status);
                $label .= ' — ' . ($status ? $status->label : $breed->post_status);
            }
            echo '<option value="' . esc_attr($breed->ID) . '" ' . selected(get_post_meta($post->ID, '_zhibek_breed', true), $breed->ID, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';
    }
    if (get_post_meta($post->ID, '_zhibek_demo', true)) {
        echo '<p><strong>Это демонстрационная запись.</strong> Она показывается на сайте, пока включён демо-режим (Настройки → ZHIBEK). Для реального котёнка создайте новую запись.</p>';
    }
}

function zhibek_enquiry_meta_box($post) {
    if (!current_user_can('manage_options')) { return; }
    wp_nonce_field('zhibek_save_enquiry', 'zhibek_enquiry_nonce');
    echo '<p><label for="zhibek-enquiry-status"><strong>Обработка заявки</strong></label><br><select id="zhibek-enquiry-status" name="zhibek_enquiry_status">';
    foreach (zhibek_enquiry_statuses() as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected(zhibek_enquiry_status($post->ID), $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';
    $contact = get_post_meta($post->ID, '_zhibek_contact', true);
    echo '<p><strong>Контакт:</strong> ' . zhibek_enquiry_contact_link($contact) . '</p>';
    echo '<pre style="white-space:pre-wrap">' . esc_html($post->post_content) . '</pre>';
    echo '<p><strong>Письмо владельцу:</strong> ' . (get_post_meta($post->ID, '_zhibek_email_sent', true) ? 'отправлено через wp_mail (доставку нужно проверить)' : 'не отправлено; заявка сохранена') . '</p>';
    echo '<p><strong>Согласие получено (UTC):</strong> ' . esc_html(get_post_meta($post->ID, '_zhibek_consent_at', true)) . '</p>';
}

/** Processing belongs to the private enquiry, separate from WordPress publication status. */
function zhibek_enquiry_statuses() {
    return array('new' => 'Новая', 'in_progress' => 'В работе', 'closed' => 'Закрыта');
}

function zhibek_enquiry_status($post_id) {
    $status = get_post_meta($post_id, '_zhibek_enquiry_status', true);
    return is_string($status) && isset(zhibek_enquiry_statuses()[$status]) ? $status : 'new';
}

function zhibek_enquiry_contact_link($contact) {
    $contact = is_scalar($contact) ? (string) $contact : '';
    if (is_email($contact)) {
        return '<a href="' . esc_url('mailto:' . $contact) . '">' . esc_html($contact) . '</a>';
    }
    $digits = preg_replace('/\D/', '', $contact);
    if (strlen($digits) >= 7 && strlen($digits) <= 15) {
        return '<a href="' . esc_url('tel:' . ('+' === substr($contact, 0, 1) ? '+' : '') . $digits) . '">' . esc_html($contact) . '</a>';
    }
    return esc_html($contact);
}

function zhibek_save_enquiry_status($post_id, $post) {
    if ('zhibek_enquiry' !== $post->post_type || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('manage_options')) { return; }
    $nonce = isset($_POST['zhibek_enquiry_nonce']) && is_string($_POST['zhibek_enquiry_nonce']) ? sanitize_text_field(wp_unslash($_POST['zhibek_enquiry_nonce'])) : '';
    $status = isset($_POST['zhibek_enquiry_status']) && is_string($_POST['zhibek_enquiry_status']) ? sanitize_key(wp_unslash($_POST['zhibek_enquiry_status'])) : '';
    if (!wp_verify_nonce($nonce, 'zhibek_save_enquiry') || !isset(zhibek_enquiry_statuses()[$status])) { return; }
    update_post_meta($post_id, '_zhibek_enquiry_status', $status);
}
add_action('save_post', 'zhibek_save_enquiry_status', 10, 2);

function zhibek_enquiry_columns($columns) {
    return array_merge(array('cb' => isset($columns['cb']) ? $columns['cb'] : '', 'title' => 'Заявка'), array(
        'zhibek_contact' => 'Контакт', 'zhibek_city' => 'Город', 'zhibek_breed' => 'Интерес',
        'zhibek_enquiry_status' => 'Обработка', 'zhibek_email' => 'Письмо',
        'date' => isset($columns['date']) ? $columns['date'] : 'Дата',
    ));
}
add_filter('manage_zhibek_enquiry_posts_columns', 'zhibek_enquiry_columns');

function zhibek_enquiry_column($column, $post_id) {
    if (!current_user_can('manage_options')) { return; }
    if ('zhibek_contact' === $column) {
        echo zhibek_enquiry_contact_link(get_post_meta($post_id, '_zhibek_contact', true));
    } elseif (in_array($column, array('zhibek_city', 'zhibek_breed'), true)) {
        echo esc_html(get_post_meta($post_id, '_' . $column, true));
    } elseif ('zhibek_enquiry_status' === $column) {
        echo esc_html(zhibek_enquiry_statuses()[zhibek_enquiry_status($post_id)]);
    } elseif ('zhibek_email' === $column) {
        echo '1' === get_post_meta($post_id, '_zhibek_email_sent', true) ? 'Отправлено' : 'Не отправлено';
    }
}
add_action('manage_zhibek_enquiry_posts_custom_column', 'zhibek_enquiry_column', 10, 2);

function zhibek_sync_breed_term($breed_id) {
    $post = get_post($breed_id);
    if (!$post || 'zhibek_breed' !== $post->post_type) { return 0; }
    if ('publish' !== $post->post_status) {
        zhibek_remove_breed_term($breed_id);
        return 0;
    }
    $term_id = (int) get_post_meta($breed_id, '_zhibek_taxonomy_term', true);
    $term = $term_id ? get_term($term_id, 'zhibek_breed_type') : false;
    if ($term && !is_wp_error($term)) {
        $result = wp_update_term($term_id, 'zhibek_breed_type', array('name' => $post->post_title, 'slug' => $post->post_name));
    } else {
        $existing = term_exists($post->post_name, 'zhibek_breed_type');
        $result = $existing ? $existing : wp_insert_term($post->post_title, 'zhibek_breed_type', array('slug' => $post->post_name));
    }
    if (is_wp_error($result)) { return 0; }
    $term_id = is_array($result) ? (int) $result['term_id'] : (int) $result;
    update_post_meta($breed_id, '_zhibek_taxonomy_term', $term_id);
    update_term_meta($term_id, '_zhibek_breed_id', (int) $breed_id);
    return $term_id;
}

/** The same metadata rules apply to the admin form, REST requests and programmatic updates. */
function zhibek_sanitize_content_meta($value, $key) {
    if (!is_scalar($value)) { return '_zhibek_breed' === $key ? 0 : ''; }
    if ('_zhibek_breed' === $key) {
        if (!preg_match('/^\d+$/D', (string) $value)) { return 0; }
        $id = absint($value);
        return $id && 'zhibek_breed' === get_post_type($id) && !in_array(get_post_status($id), array('trash', 'auto-draft'), true) ? $id : 0;
    }
    if ('_zhibek_cutout' === $key) { return $value ? '1' : ''; }
    if (in_array($key, array('_zhibek_facts', '_zhibek_fit', '_zhibek_note'), true)) {
        return sanitize_textarea_field((string) $value);
    }
    $value = sanitize_text_field((string) $value);
    if (in_array($key, array('_zhibek_eye', '_zhibek_accent'), true)) {
        return preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? strtolower($value) : '';
    }
    if ('_zhibek_price' === $key) {
        $digits = preg_replace('/\D/', '', $value);
        return strlen($digits) <= 9 ? $digits : '';
    }
    if ('_zhibek_status' === $key) {
        return in_array($value, array('available', 'reserved', 'home', 'expected', 'demo'), true) ? $value : 'demo';
    }
    if ('_zhibek_image' === $key) {
        $value = sanitize_file_name(wp_basename($value));
        return preg_match('/\.(?:webp|png|jpe?g|avif)$/i', $value) ? $value : '';
    }
    if ('_zhibek_birth' === $key && '' !== $value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }
    return $value;
}

function zhibek_sync_kitten_breed($post) {
    $post_id = is_object($post) ? $post->ID : (int) $post;
    if ('zhibek_kitten' !== get_post_type($post_id)) { return; }
    $breed_id = (int) get_post_meta($post_id, '_zhibek_breed', true);
    $term_id = $breed_id ? zhibek_sync_breed_term($breed_id) : 0;
    wp_set_object_terms($post_id, $term_id ? array($term_id) : array(), 'zhibek_breed_type');
}
add_action('rest_after_insert_zhibek_kitten', 'zhibek_sync_kitten_breed');

function zhibek_kitten_breed_meta_changed($meta_id, $post_id, $key) {
    if ('_zhibek_breed' === $key && 'zhibek_kitten' === get_post_type($post_id)) {
        zhibek_sync_kitten_breed($post_id);
    }
}
add_action('added_post_meta', 'zhibek_kitten_breed_meta_changed', 10, 3);
add_action('updated_post_meta', 'zhibek_kitten_breed_meta_changed', 10, 3);
add_action('deleted_post_meta', 'zhibek_kitten_breed_meta_changed', 10, 3);

function zhibek_remove_breed_term($breed_id) {
    $term_id = (int) get_post_meta($breed_id, '_zhibek_taxonomy_term', true);
    if ($term_id && (int) get_term_meta($term_id, '_zhibek_breed_id', true) === (int) $breed_id) {
        wp_delete_term($term_id, 'zhibek_breed_type');
    }
    delete_post_meta($breed_id, '_zhibek_taxonomy_term');
}

function zhibek_breed_kittens($breed_id) {
    return get_posts(array('post_type' => 'zhibek_kitten', 'post_status' => array_keys(get_post_stati()), 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_zhibek_breed', 'meta_value' => (int) $breed_id));
}

function zhibek_breed_status_changed($new_status, $old_status, $post) {
    if ('zhibek_breed' !== $post->post_type || $new_status === $old_status) { return; }
    zhibek_sync_breed_term($post->ID);
    foreach (zhibek_breed_kittens($post->ID) as $kitten_id) { zhibek_sync_kitten_breed($kitten_id); }
}
add_action('transition_post_status', 'zhibek_breed_status_changed', 10, 3);

function zhibek_deleted_breed($post_id, $post) {
    if ('zhibek_breed' !== $post->post_type) { return; }
    foreach (zhibek_breed_kittens($post_id) as $kitten_id) { update_post_meta($kitten_id, '_zhibek_breed', 0); }
    zhibek_remove_breed_term($post_id);
}
add_action('before_delete_post', 'zhibek_deleted_breed', 10, 2);

function zhibek_save_content_meta($post_id, $post) {
    if (!in_array($post->post_type, array('zhibek_breed', 'zhibek_kitten'), true) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { return; }
    if ('zhibek_breed' === $post->post_type) { zhibek_sync_breed_term($post_id); }
    if (!isset($_POST['zhibek_meta_nonce']) || !is_string($_POST['zhibek_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zhibek_meta_nonce'])), 'zhibek_save_meta') || !current_user_can('edit_post', $post_id)) { return; }
    $input = isset($_POST['zhibek_meta']) && is_array($_POST['zhibek_meta']) ? wp_unslash($_POST['zhibek_meta']) : array();
    update_post_meta($post_id, '_zhibek_cutout', isset($input['_zhibek_cutout']) ? '1' : '');
    foreach (array_diff(zhibek_meta_keys(), array('_zhibek_status', '_zhibek_breed', '_zhibek_cutout')) as $key) {
        if (!array_key_exists($key, $input) || !is_scalar($input[$key])) { continue; }
        $value = zhibek_sanitize_content_meta($input[$key], $key);
        update_post_meta($post_id, $key, $value);
    }
    if ('zhibek_kitten' === $post->post_type) {
        foreach (array('_zhibek_status', '_zhibek_breed') as $key) {
            if (!array_key_exists($key, $input) || !is_scalar($input[$key])) { continue; }
            update_post_meta($post_id, $key, zhibek_sanitize_content_meta($input[$key], $key));
        }
    }
}
add_action('save_post', 'zhibek_save_content_meta', 10, 2);

/** Seed keys make repeat runs harmless and preserve edits made in the admin. */
function zhibek_seed_find($key, $type) {
    $ids = get_posts(array('post_type' => $type, 'post_status' => array('publish', 'draft', 'private', 'pending', 'future', 'trash'), 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_zhibek_seed', 'meta_value' => $key));
    return $ids ? (int) $ids[0] : 0;
}

function zhibek_seed_post($key, $post, $meta = array()) {
    $existing = zhibek_seed_find($key, $post['post_type']);
    if ($existing) { return $existing; }
    $post['post_status'] = 'publish';
    $post['meta_input'] = array_merge($meta, array('_zhibek_seed' => $key));
    $id = wp_insert_post(wp_slash($post), true);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    return (int) $id;
}

function zhibek_native_shop_blocks($blocks, $block_name, $shortcode) {
    foreach ($blocks as &$block) {
        if ($block_name === $block['blockName']) {
            $block = array('blockName' => 'core/shortcode', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => $shortcode, 'innerContent' => array($shortcode));
        } elseif (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = zhibek_native_shop_blocks($block['innerBlocks'], $block_name, $shortcode);
        }
    }
    unset($block);
    return $blocks;
}

/** Translate Woo's unmodified English defaults without rewriting owner legal copy. */
function zhibek_localize_privacy_text($text, $type) {
    $defaults = array(
        'checkout' => array(
            'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our [privacy_policy].',
            'Ваши персональные данные используются для обработки заказа, работы с сайтом и других целей, описанных в документе «[privacy_policy]».',
        ),
        'registration' => array(
            'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our [privacy_policy].',
            'Ваши персональные данные используются для работы с сайтом, управления доступом к личному кабинету и других целей, описанных в документе «[privacy_policy]».',
        ),
    );
    return isset($defaults[$type]) && $defaults[$type][0] === $text ? $defaults[$type][1] : $text;
}
add_filter('woocommerce_get_privacy_policy_text', 'zhibek_localize_privacy_text', 10, 2);

/** Only exact factory markup is eligible; translated stock headings can vary between installation and import. */
function zhibek_is_pristine_shop_content($content, $default_content, $page) {
    if (trim($content) === trim($default_content)) { return true; }
    if ('cart' !== $page) { return false; }
    $english = 'You may be interested in&hellip;';
    $headings = array_unique(array($english, 'Обратите внимание&hellip;', __($english, 'woocommerce')));
    $tokens = array();
    foreach ($headings as $heading) {
        if ('' !== $heading) { $tokens[] = '>' . $heading . '</h2>'; }
    }
    $canonical = '>' . $english . '</h2>';
    return trim(str_replace($tokens, $canonical, $content)) === trim(str_replace($tokens, $canonical, $default_content));
}

/** Localize new or verified factory pages; owner titles and categories stay intact. */
function zhibek_localize_shop_labels($default_page_ids = array()) {
    $pages = array(
        'shop' => array('Shop', 'Магазин'), 'cart' => array('Cart', 'Корзина'),
        'checkout' => array('Checkout', 'Оформление заказа'), 'myaccount' => array('My account', 'Личный кабинет'),
    );
    foreach ($pages as $page => $labels) {
        $id = wc_get_page_id($page);
        $post = $id > 0 ? get_post($id) : null;
        if ($post && in_array($id, $default_page_ids, true) && $labels[0] === $post->post_title) {
            wp_update_post(array('ID' => $id, 'post_title' => $labels[1]));
        }
    }
}

function zhibek_setup_woocommerce() {
    if (!class_exists('WooCommerce') || !class_exists('WC_Install')) { return false; }
    $created_pages = array();
    $default_pages = array();
    $record_page = function ($page_id) use (&$created_pages) { $created_pages[] = (int) $page_id; };
    $record_defaults = function ($pages) use (&$default_pages) { $default_pages = $pages; return $pages; };
    add_action('woocommerce_page_created', $record_page);
    add_filter('woocommerce_create_pages', $record_defaults, PHP_INT_MAX);
    try { WC_Install::create_pages(); }
    finally {
        remove_action('woocommerce_page_created', $record_page);
        remove_filter('woocommerce_create_pages', $record_defaults, PHP_INT_MAX);
    }
    $default_page_ids = $created_pages;
    foreach ($default_pages as $page => $template) {
        $id = wc_get_page_id($page);
        $post = $id > 0 ? get_post($id) : null;
        if ($post && isset($template['content']) && zhibek_is_pristine_shop_content($post->post_content, $template['content'], $page)) {
            $default_page_ids[] = (int) $id;
        }
    }
    foreach (array('cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]', 'myaccount' => '[woocommerce_my_account]') as $page => $shortcode) {
        $id = wc_get_page_id($page);
        $post = $id > 0 ? get_post($id) : null;
        $is_native_default = $post && in_array(trim($post->post_content), array($shortcode, '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->'), true);
        $is_default = $post && ($is_native_default || in_array((int) $id, $default_page_ids, true));
        if (!$post || (!in_array($id, $created_pages, true) && !$is_default)) { continue; }
        $default_page_ids[] = (int) $id;
        if ($post && '' === trim($post->post_content)) {
            wp_update_post(array('ID' => $id, 'post_content' => $shortcode));
        } elseif ($post && has_block('woocommerce/' . $page, $post->post_content)) {
            $blocks = zhibek_native_shop_blocks(parse_blocks($post->post_content), 'woocommerce/' . $page, $shortcode);
            wp_update_post(array('ID' => $id, 'post_content' => wp_slash(serialize_blocks($blocks))));
        }
    }
    zhibek_localize_shop_labels(array_unique($default_page_ids));
    // Gateways, shipping and merchant credentials require the owner's real configuration.
    return true;
}

function zhibek_seed_content() {
    add_option('zhibek_settings', zhibek_default_settings());
    $breeds = array(
        'abyssinian' => array(
            'title' => 'Абиссинская', 'order' => 1,
            'subtitle' => 'Тикированная шерсть, большие уши, бесконечное любопытство.',
            'traits' => 'Подвижная, любопытная, привязчивая, ловкая',
            'facts' => "Вес: 3,5–5 кг\nШерсть: короткая, тикированная\nОкрасы: руди, соррель, голубой, фавн\nГлаза: золото, мёд, зелень",
            'fit' => 'Вам нужна кошка-компаньон, которая участвует в каждом деле: от разбора шкафов до работы за ноутбуком.',
            'note' => 'Любит высоту и движение. Дому нужны когтеточки, полки и ежедневные игры.',
            'eye' => '#d9a238', 'accent' => '#c9803c', 'image' => 'abyssinian.webp',
            'content' => "Короткая плотная шерсть абиссинской кошки окрашена тикингом: каждый волос несёт две-три тёмные полоски, поэтому мех переливается, как нагретый песок. Тело гибкое и мускулистое, уши крупные, глаза обведены тёмной линией.\n\nАбиссинцы активны, умны и любопытны. Они сопровождают человека по квартире, открывают дверцы, проверяют каждую коробку и любят наблюдать за домом с высоты. Голос у них тихий.\n\nУход за шерстью простой: достаточно расчёсывать раз в неделю. Главное для абиссинки — движение, игры и внимание. Одиночество в пустой квартире на целый день ей не подойдёт.",
        ),
        'bengal' => array(
            'title' => 'Бенгальская', 'order' => 2,
            'subtitle' => 'Леопардовый рисунок и характер, которому нужен простор.',
            'traits' => 'Энергичная, умная, игривая, любит воду',
            'facts' => "Вес: 4–7 кг\nШерсть: короткая, плотная, с блеском\nРисунок: розетки или мрамор\nОкрасы: браун, снежные, серебристые",
            'fit' => 'Вы готовы играть каждый день и любите кошек с яркой индивидуальностью.',
            'note' => 'Нужны высокие комплексы, головоломки и защита на окнах и балконе. Скуки бенгал не прощает.',
            'eye' => '#9ec35b', 'accent' => '#b8862b', 'image' => 'bengal.webp',
            'content' => "Бенгальская кошка — крупная, мускулистая, с пятнистой шерстью. Рисунок бывает розеточным (кольца, как у леопарда) или мраморным. Шерсть короткая, плотная, с характерным блеском.\n\nБенгалы энергичны, умны и любознательны. Многие любят воду, учатся приносить игрушки и открывать двери. Им нужны высокие комплексы для лазания, активные игры и занятия для ума.\n\nШерсть почти не требует ухода. Зато требуют внимания игры: бенгал, которому скучно, быстро находит себе занятие сам. Окна и балкон должны быть защищены сеткой.",
        ),
        'siamese' => array(
            'title' => 'Сиамская', 'order' => 3,
            'subtitle' => 'Голубые глаза, шёлковая шерсть и привычка разговаривать.',
            'traits' => 'Общительная, голосистая, преданная, ласковая',
            'facts' => "Вес: 3–5 кг\nШерсть: короткая, шелковистая\nОкрас: колор-пойнт — сил, шоколад, блю, лилак\nГлаза: насыщенно-голубые",
            'fit' => 'Вы часто бываете дома, любите разговаривать с питомцем и хотите кошку, которая выбирает вас.',
            'note' => 'Плохо переносит одиночество. Если вы много работаете вне дома, подумайте о паре кошек.',
            'eye' => '#4f8fd6', 'accent' => '#8fa6cf', 'image' => 'siamese.webp',
            'content' => "Классическая сиамская кошка — светлое тело и тёмные отметины на морде, ушах, лапах и хвосте. Глаза миндалевидные, ярко-голубые. Шерсть короткая, плотно прилегающая, шелковистая.\n\nСиамцы одни из самых общительных. Они привязываются к семье, «разговаривают» характерным громким голосом и ходят за хозяином по дому. Хорошо ладят с другими кошками.\n\nШерсть ухода почти не требует. Требуется общение: сиамская кошка тяжело переносит долгие часы без людей и без игры.",
        ),
    );
    $breed_ids = array();
    foreach ($breeds as $slug => $b) {
        $breed_ids[$slug] = zhibek_seed_post('breed-' . $slug, array('post_type' => 'zhibek_breed', 'post_title' => $b['title'], 'post_name' => $slug, 'menu_order' => $b['order'], 'post_content' => $b['content'], 'post_excerpt' => $b['subtitle']), array(
            '_zhibek_subtitle' => $b['subtitle'], '_zhibek_traits' => $b['traits'], '_zhibek_facts' => $b['facts'], '_zhibek_fit' => $b['fit'], '_zhibek_note' => $b['note'],
            '_zhibek_eye' => $b['eye'], '_zhibek_accent' => $b['accent'], '_zhibek_image' => $b['image'],
        ));
        zhibek_sync_breed_term($breed_ids[$slug]);
    }
    $kittens = array(
        'abyssinian' => array('Алтын', 'Девочка', 'Руди', '2026-08-11', 'available', 'Aston of Zhibek', 'Miura of Zhibek', 'Подвижная и любопытная девочка. Уже сама находит самое высокое место в комнате.'),
        'bengal' => array('Барс', 'Мальчик', 'Браун, розетки', '2026-08-02', 'available', 'Raja of Zhibek', 'Kali of Zhibek', 'Крупный активный мальчик с выраженным рисунком. Любит гоняться за любой игрушкой на верёвочке.'),
        'siamese' => array('Шолпан', 'Девочка', 'Сил-пойнт', '2026-08-19', 'reserved', 'Siam of Zhibek', 'Luna of Zhibek', 'Спокойная, общительная девочка с яркими голубыми глазами.'),
    );
    foreach ($kittens as $slug => $k) {
        $id = zhibek_seed_post('kitten-' . $slug, array(
            'post_type' => 'zhibek_kitten', 'post_title' => $k[0], 'post_name' => 'kitten-' . $slug,
            'post_content' => $k[7],
            'post_excerpt' => $k[7],
        ), array('_zhibek_subtitle' => $k[7], '_zhibek_image' => 'k-' . $slug . '.webp', '_zhibek_status' => $k[4], '_zhibek_sex' => $k[1], '_zhibek_color' => $k[2], '_zhibek_birth' => $k[3], '_zhibek_sire' => $k[5], '_zhibek_dam' => $k[6], '_zhibek_breed' => $breed_ids[$slug], '_zhibek_demo' => '1'));
        zhibek_sync_kitten_breed($id);
    }
    $privacy = zhibek_seed_post('page-privacy', array('post_type' => 'page', 'post_title' => 'Конфиденциальность', 'post_name' => 'privacy', 'post_content' => '<p><strong>Оператор данных:</strong> владелец сайта ZHIBEK. Реквизиты (ИП или ТОО, БИН/ИИН, адрес, email) владелец указывает здесь перед запуском.</p><h2>Какие данные мы получаем</h2><p>Через форму заявки — имя, телефон или email, город, интересующая порода и текст сообщения. Через магазин — данные, необходимые для оформления и доставки заказа.</p><h2>Зачем</h2><p>Чтобы ответить на заявку, согласовать знакомство с котёнком и выполнить заказ. Данные не передаются третьим лицам, кроме служб доставки и платёжного сервиса при оформлении заказа.</p><h2>Где хранятся</h2><p>Заявки сохраняются в закрытом разделе панели сайта и доступны только администратору. Сохранение происходит только после вашего согласия в форме.</p><h2>Cookie</h2><p>Магазин использует технические cookie для корзины и оформления заказа. Рекламных и аналитических cookie на сайте по умолчанию нет.</p><h2>Ваши права</h2><p>Вы можете запросить уточнение или удаление своих данных, написав по контактам, указанным на сайте.</p>'));
    if (!(int) get_option('wp_page_for_privacy_policy') && 'publish' === get_post_status($privacy)) { update_option('wp_page_for_privacy_policy', $privacy); }
    zhibek_seed_post('page-order-terms', array('post_type' => 'page', 'post_title' => 'Доставка и оплата', 'post_name' => 'order-terms', 'post_content' => '<h2>Котята</h2><p>Котята не продаются через корзину. Сначала вы знакомитесь с питомником и котёнком, затем обсуждаете условия, документы и способ передачи. Передать котёнка можно лично или привезти в ваш город — маршрут и стоимость согласуются отдельно.</p><h2>Товары магазина</h2><p>Доставка по Казахстану службой доставки. Сроки и стоимость рассчитываются при оформлении заказа. Оплата — способами, подключёнными в магазине.</p><h2>Возврат</h2><p>Условия возврата товаров владелец указывает здесь перед запуском магазина.</p>'));
    $category = term_exists('journal', 'category');
    if (!$category) { $category = wp_insert_term('Журнал', 'category', array('slug' => 'journal')); }
    $journal_category = !is_wp_error($category) ? (is_array($category) ? (int) $category['term_id'] : (int) $category) : 0;
    $articles = array(
        'choose-breed' => array('Какую породу выбрать: абиссинская, бенгальская или сиамская', 'Три характера и три образа жизни. Короткое сравнение без обещаний, которые не может выполнить ни одна порода.', '<p>Все три породы короткошёрстные, активные и ориентированы на человека. Различаются они темпом, голосом и тем, что им нужно от дома.</p><h2>Абиссинская — для тех, кто любит быть в центре дел</h2><p>Подвижная и любопытная кошка. Ходит за хозяином по квартире, любит высоту и наблюдение. Голос тихий, шерсть не требует сложного ухода.</p><h2>Бенгальская — для тех, кто готов играть</h2><p>Самая энергичная из трёх. Ей нужны высокие комплексы, ежедневные игры и защита на окнах и балконе. Если дом пуст большую часть дня, подумайте о второй кошке или о другой породе.</p><h2>Сиамская — для тех, кто любит разговаривать</h2><p>Очень общительная и голосистая. Привязывается к людям и тяжело переносит одиночество. Лучше всего чувствует себя в семье, где кто-то часто бывает дома.</p><h2>Что спросить у заводчика</h2><p>Кто родители котёнка и когда их обследовали. Как и где он рос. Какие документы вы получите. Что входит в стоимость. Можно ли обратиться за советом после переезда.</p>'),
        'first-days' => array('Первые дни кошки в новом доме', 'Адаптация начинается со спокойной комнаты и понятного распорядка.', '<p>Подготовьте тихую комнату до приезда кошки: поставьте лоток, воду, привычный корм и укрытие. Разнесите лоток и места еды. Проверьте окна, балкон и предметы, которые можно проглотить. Откидное окно без защиты опасно: кошка может застрять в щели.</p><h2>Дайте время освоиться</h2><p>Откройте переноску и позвольте кошке выйти самостоятельно. Не вытаскивайте её из укрытия и не знакомьте сразу со всеми гостями. Сядьте рядом, говорите спокойно и предложите игру, когда она проявит интерес. Некоторые кошки исследуют комнату быстро, другим нужно больше времени.</p><h2>Меняйте привычки постепенно</h2><p>Уточните прежний корм и наполнитель. Резкая смена питания может вызвать расстройство пищеварения, поэтому переход обсуждают с ветеринарным врачом. Если в доме есть другие животные, знакомство проводят постепенно, начиная с раздельных помещений и обмена запахами.</p><h2>Наблюдайте за состоянием</h2><p>Запишите, ест ли кошка, пьёт ли и пользуется ли лотком. При выраженной вялости, повторной рвоте, затруднённом дыхании или отказе от еды обратитесь к ветеринарному врачу. Статья даёт общие ориентиры и не заменяет диагностику.</p>'),
        'safe-play' => array('Игра и безопасная среда для домашней кошки', 'Короткие занятия, устойчивые поверхности и отдых помогают организовать день.', '<p>Домашней кошке нужны возможности двигаться, прятаться, точить когти и наблюдать. Начните с устойчивой когтеточки, укрытия и удобного места у защищённого окна. Комплекс для лазания закрепите так, чтобы он не мог опрокинуться.</p><h2>Играйте вместе</h2><p>Проводите короткие занятия с игрушкой-удочкой, позволяя кошке преследовать и ловить её. Подбирайте темп по возрасту и состоянию животного. Не используйте руки как игрушку: привычка кусать кожу мешает безопасному общению.</p><h2>Убирайте опасные предметы</h2><p>Ленты, нитки и игрушки с легко отрывающимися деталями храните вне доступа после игры. Не оставляйте удочку без присмотра. Проверьте растения на токсичность для кошек и уберите бытовую химию. Игрушки регулярно осматривайте и заменяйте при повреждении.</p><h2>Чередуйте занятия и отдых</h2><p>Предлагайте несколько игрушек по очереди вместо постоянного большого набора. Кормовые головоломки вводите постепенно, учитывая дневную порцию питания. Дайте кошке тихое место для сна. При изменении активности или поведения обсудите состояние с ветеринарным врачом.</p>'),
    );
    foreach ($articles as $slug => $article) {
        $existing = zhibek_seed_find('journal-' . $slug, 'post');
        $id = zhibek_seed_post('journal-' . $slug, array('post_type' => 'post', 'post_title' => $article[0], 'post_name' => $slug, 'post_excerpt' => $article[1], 'post_content' => $article[2]));
        if ($journal_category && !$existing) { wp_set_post_categories($id, array($journal_category), true); }
    }
    $woocommerce = zhibek_setup_woocommerce();
    if ($woocommerce && class_exists('WC_Product_Simple')) {
        $products = array(
            'house' => array('Домик-лежанка из фанеры', '19900', 'furniture', 'Мебель', 'Берёзовая фанера, съёмная подушка из букле.'),
            'bowl' => array('Керамическая миска на дубовой подставке', '6900', 'care', 'Уход', 'Широкая миска, не сдвигается по полу.'),
            'food' => array('Сухой корм для взрослых кошек, 2 кг', '8900', 'food', 'Питание', 'Состав и назначение корма укажите в описании товара.'),
            'toy' => array('Игрушка-дразнилка с перьями', '4900', 'care', 'Уход', 'Деревянная ручка, шнур с шариком из сизаля и перьями.'),
        );
        foreach ($products as $slug => $item) {
            if (zhibek_seed_find('product-' . $slug, 'product')) { continue; }
            $category = term_exists($item[2], 'product_cat');
            if (!$category) { $category = wp_insert_term($item[3], 'product_cat', array('slug' => $item[2])); }
            if (is_wp_error($category)) { throw new RuntimeException($category->get_error_message()); }
            $category_id = is_array($category) ? (int) $category['term_id'] : (int) $category;
            $product = new WC_Product_Simple();
            $product->set_name($item[0]); $product->set_slug('demo-' . $slug); $product->set_status('publish');
            $product->set_regular_price($item[1]); $product->set_tax_status('none');
            $product->set_catalog_visibility('visible'); $product->set_category_ids(array($category_id));
            $product->set_short_description($item[4]);
            $product->set_description($item[4] . ' Описание, цену и фотографию замените данными настоящего товара.');
            $product->update_meta_data('_zhibek_seed', 'product-' . $slug);
            $product->update_meta_data('_zhibek_demo', '1');
            $product->update_meta_data('_zhibek_image', 'product-' . $slug . '.webp');
            $product->save();
        }
    }
    flush_rewrite_rules();
    return array('breeds' => count($breed_ids), 'kittens' => count($kittens), 'products' => $woocommerce ? 4 : 0, 'woocommerce' => $woocommerce);
}

if (defined('WP_CLI') && WP_CLI) {
    /** Create marked demo data, owner-editable breeds and original care articles without enabling payments. */
    class Zhibek_Seed_Command {
        public function __invoke($args, $assoc_args) {
            try { $result = zhibek_seed_content(); }
            catch (Throwable $exception) { WP_CLI::error($exception->getMessage()); return; }
            if (!$result['woocommerce']) { WP_CLI::warning('WooCommerce не активен: магазин не создан. После активации повторите wp zhibek seed.'); }
            WP_CLI::success('ZHIBEK: породы, котята-примеры, журнал и страницы созданы. Демо-товаров: ' . $result['products'] . '. Повторный запуск сохраняет существующие записи. Платёжные шлюзы не изменены.');
        }
    }
    WP_CLI::add_command('zhibek seed', 'Zhibek_Seed_Command');
}
