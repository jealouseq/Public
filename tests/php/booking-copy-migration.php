<?php
// Isolated production migration logic: in-memory WP boundary, no wp-load or database.
define('ABSPATH', '/isolated-test/');
final class WooCommerce {}
final class WP_Post {
    public int $ID;
    public string $post_name, $post_title, $post_content, $post_excerpt = '', $post_status = 'publish';
    public function __construct(array $fields) { foreach ($fields as $key => $value) $this->$key = $value; }
}
final class JR_Lock {
    public static function acquire(string $name): string { return 'isolated-owner'; }
    public static function release(string $name, string $owner): void {}
}
$options = []; $pages = []; $writes = 0; $nextId = 1; $checks = 0; $failures = [];
function get_option(string $name, mixed $default = false): mixed { global $options; return $options[$name] ?? $default; }
function update_option(string $name, mixed $value, mixed $autoload = null): bool { global $options; $options[$name] = $value; return true; }
function get_page_by_path(string $slug): ?WP_Post { global $pages; return isset($pages[$slug]) ? clone $pages[$slug] : null; }
function wp_insert_post(array $fields, bool $error = false): int {
    global $pages, $nextId, $writes;
    unset($fields['post_type']); $fields['ID'] = $nextId++;
    $pages[$fields['post_name']] = new WP_Post($fields); $writes++;
    return $fields['ID'];
}
function wp_update_post(array $fields): int {
    global $pages, $writes;
    foreach ($pages as $page) if ($page->ID === $fields['ID']) {
        foreach ($fields as $key => $value) $page->$key = $value;
        $writes++; return $page->ID;
    }
    throw new RuntimeException('Unknown isolated page');
}
function is_wp_error(mixed $value): bool { return false; }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function register_post_status(string $key, array $value): void { global $registeredStatus; $registeredStatus = [$key, $value]; }
function _n_noop(string $singular, string $plural, string $domain): array { return [$singular, $plural]; }
// Calls outside the page-copy boundary must fail before touching any external state.
function wp_mail(...$args): never { throw new RuntimeException('Migration attempted email'); }
function wc_get_orders(...$args): never { throw new RuntimeException('Migration attempted order access'); }
function get_posts(...$args): never { throw new RuntimeException('1.8 copy migration attempted game access'); }
function check_copy(bool $value, string $label): void { global $checks, $failures; $checks++; if (!$value) $failures[] = $label; }
function fixture_pages(array $seeds): void {
    global $pages, $writes, $nextId;
    $pages = []; $writes = 0; $nextId = 1;
    foreach ($seeds as $slug => $seed) wp_insert_post(['post_name' => $slug, 'post_title' => $seed['title'], 'post_content' => $seed['content']]);
    $writes = 0;
}
$plugin = getenv('JR_TEST_PLUGIN_PATH') ?: dirname(__DIR__, 2).'/wordpress/joyrent-rentals';
foreach (['settings', 'locale', 'store', 'orders'] as $include) require $plugin.'/includes/'.$include.'.php';
$base = $plugin.'/data/';
$old18 = json_decode(file_get_contents(getenv('JR_TEST_BASELINE_PAGES') ?: $base.'page-seeds-1.8.json'), true, 512, JSON_THROW_ON_ERROR);
$next = json_decode(file_get_contents($base.'legal-pages.json'), true, 512, JSON_THROW_ON_ERROR);
$faq = json_decode(file_get_contents($base.'faq.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['uk' => ['faq', 'Питання про оренду'], 'ru' => ['faq-ru', 'Вопросы об аренде']] as $lang => [$slug, $title]) {
    $content = '';
    foreach ($faq[$lang] as $entry) $content .= '<details><summary>'.esc_html($entry['question']).'</summary><p>'.esc_html($entry['answer']).'</p></details>';
    $next[$slug] = ['title' => $title, 'content' => $content];
}
fixture_pages($old18); $options = ['joyrent_version' => '1.8.0', 'joyrent_settings' => JR_Settings::defaults()];
$settings = $options['joyrent_settings']; $ids = array_map(fn($page) => $page->ID, $pages);
JR_Store::upgrade();
check_copy(get_option('joyrent_version') === '1.8.2', 'Existing 1.8 install advances to 1.8.2');
foreach ($next as $slug => $seed) {
    check_copy($pages[$slug]->post_content === $seed['content'] && $pages[$slug]->ID === $ids[$slug], 'Untouched 1.8 page migrates in place '.$slug);
    check_copy(!preg_match('/заяв[а-яіїєё]*/iu', $pages[$slug]->post_content), 'Visitor page uses booking wording '.$slug);
}
check_copy(get_option('joyrent_settings') === $settings, 'Copy upgrade preserves all settings');
$before = serialize($pages); $beforeWrites = $writes; JR_Store::upgrade();
check_copy(serialize($pages) === $before && $writes === $beforeWrites, 'Repeated upgrade makes no page writes');
foreach (['1.6', '1.7'] as $archive) {
    fixture_pages(json_decode(file_get_contents($base.'page-seeds-'.$archive.'.json'), true, 512, JSON_THROW_ON_ERROR));
    $options['joyrent_version'] = '1.7.1'; JR_Store::upgrade();
    foreach ($next as $slug => $seed) check_copy($pages[$slug]->post_content === $seed['content'], 'Older exact archive migrates '.$archive.' '.$slug);
}
foreach (['post_title' => 'Owner title', 'post_content' => 'Owner content', 'post_excerpt' => 'Owner excerpt', 'post_status' => 'draft'] as $field => $value) {
    fixture_pages($old18);
    foreach ($pages as $page) $page->$field = $value;
    $options['joyrent_version'] = '1.8.0'; $before = serialize($pages); JR_Store::upgrade();
    check_copy(serialize($pages) === $before && $writes === 0, 'Owner page '.$field.' is preserved');
}
$business = ['faq', 'faq-ru', 'umovy-orendy', 'usloviya-arendy'];
foreach ([['deposit_ps5' => 9000], ['city' => 'Owner city', 'city_ru' => 'Город владельца'], ['delivery_fee' => 125]] as $custom) {
    fixture_pages($old18); $options['joyrent_settings'] = array_merge($settings, $custom); $options['joyrent_version'] = '1.8.0';
    $saved = $options['joyrent_settings']; JR_Store::upgrade();
    foreach ($business as $slug) {
        $content = $pages[$slug]->post_content;
        check_copy(!preg_match('/заяв[а-яіїєё]*/iu', $content), 'Custom shop neutral copy uses booking wording '.$slug);
        check_copy(!preg_match('/7 500|25 000|200 грн|300 грн|Одес/iu', $content), 'Custom shop neutral copy avoids approved commercial claims '.$slug);
    }
    check_copy(get_option('joyrent_settings') === $saved, 'Custom settings remain intact');
    foreach (['konfidentsiinist', 'konfidentsialnost'] as $slug) check_copy($pages[$slug]->post_content === $next[$slug]['content'], 'Privacy still migrates for custom settings '.$slug);
}
fixture_pages([]); $options['joyrent_version'] = '1.8.0'; JR_Store::upgrade();
foreach ($next as $slug => $seed) check_copy(isset($pages[$slug]) && !preg_match('/заяв[а-яіїєё]*/iu', $pages[$slug]->post_content), 'Missing pages seed booking copy '.$slug);
foreach ([
    'Перевір дані бронювання.' => 'Проверь данные брони.',
    'Не вдалося надіслати бронювання.' => 'Не удалось отправить бронь.',
    'Зараз бронювання недоступне. Спробуй пізніше.' => 'Сейчас бронирование недоступно. Попробуй позже.',
    'Параметри бронювання змінилися. Онови сторінку та спробуй ще раз.' => 'Параметры брони изменились. Обнови страницу и попробуй ещё раз.',
    'Це бронювання вже надсилається. Зачекай і спробуй ще раз.' => 'Эта бронь уже отправляется. Подожди и попробуй ещё раз.',
    'Забагато бронювань за короткий час. Спробуй через 15 хвилин.' => 'Слишком много бронирований за короткое время. Попробуй через 15 минут.',
    'Не вдалося прийняти бронювання. Спробуй ще раз трохи пізніше.' => 'Не удалось принять бронь. Попробуй ещё раз чуть позже.',
] as $ua => $ru) check_copy(JR_Locale::message($ua, 'ru') === $ru && JR_Locale::message($ua, 'uk') === $ua, 'Localized public error '.$ua);
check_copy(JR_Locale::message('Unknown', 'ru') === 'Не удалось обработать бронь. Попробуй ещё раз чуть позже.', 'Localized public error fallback uses booking terminology');
JR_Orders::register_status();
check_copy($registeredStatus[0] === 'wc-jr-request' && $registeredStatus[1]['label'] === 'Бронювання', 'WooCommerce human status changes while machine key is preserved');
check_copy(JR_Orders::statuses([])['wc-jr-request'] === 'Бронювання', 'WooCommerce filtered human status uses booking terminology');
check_copy($registeredStatus[1]['label_count'] === ['Бронювання <span class="count">(%s)</span>', 'Бронювання <span class="count">(%s)</span>'], 'WooCommerce singular and plural human status use booking terminology');
echo json_encode(['checks' => $checks, 'failures' => $failures, 'mailAttempts' => 0, 'orderAccess' => 0, 'databaseAccess' => 0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
exit($failures ? 1 : 0);
