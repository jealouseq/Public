<?php
defined('ABSPATH') || exit;

/** Administrator-only bot connection; token, password hash and webhook secret stay server-side. */
final class JRTG_Settings {
    const OPTION = 'joyrent_telegram_settings';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'menu']);
        foreach (['save', 'connect', 'test'] as $action) {
            add_action('admin_post_jrtg_' . $action, [self::class, $action]);
        }
        add_action('joyrent_telegram_subscriber_test', [self::class, 'send_test']);
    }

    public static function menu(): void {
        add_submenu_page('woocommerce', 'JOYRENT → Telegram', 'JOYRENT → Telegram',
            'manage_options', 'joyrent-telegram', [self::class, 'page']);
    }

    private static function raw(): array {
        $raw = get_option(self::OPTION, []);
        return is_array($raw) ? $raw : [];
    }

    private static function constant_token(): ?string {
        if (!defined('JOYRENT_TELEGRAM_BOT_TOKEN')) return null;
        return is_string(JOYRENT_TELEGRAM_BOT_TOKEN) ? trim(JOYRENT_TELEGRAM_BOT_TOKEN) : '';
    }

    public static function valid_token(string $token): bool {
        return (bool) preg_match('/^[0-9]{6,15}:[A-Za-z0-9_-]{20,100}$/D', $token);
    }

    public static function get(): array {
        $raw = self::raw();
        $token = self::constant_token() ?? (is_string($raw['token'] ?? null) ? $raw['token'] : '');
        $connected = !empty($raw['webhook_connected']) &&
            hash_equals(hash('sha256', $token), (string) ($raw['webhook_bot'] ?? ''));
        return [
            'enabled' => !empty($raw['enabled']) && $connected,
            'token' => $token,
            'password_hash' => is_string($raw['password_hash'] ?? null) ? $raw['password_hash'] : '',
            'webhook_secret' => is_string($raw['webhook_secret'] ?? null) ? $raw['webhook_secret'] : '',
            'webhook_connected' => $connected,
            'webhook_bot' => is_string($raw['webhook_bot'] ?? null) ? $raw['webhook_bot'] : '',
            'access_revision' => is_string($raw['access_revision'] ?? null) ? $raw['access_revision'] : '',
            'revision' => is_string($raw['revision'] ?? null) ? $raw['revision'] : '',
            'enabled_since' => max(0, (int) ($raw['enabled_since'] ?? 0)),
        ];
    }

    public static function ready(): bool {
        $s = self::get();
        return $s['enabled'] && self::valid_token($s['token']) && $s['password_hash'] !== '' &&
            preg_match('/^[a-f0-9]{64}$/D', $s['webhook_secret']) && $s['webhook_connected'];
    }

    public static function candidate(array $old, array $input, int $now): array {
        foreach (['token', 'password'] as $key) {
            if (!is_string($input[$key] ?? '')) throw new InvalidArgumentException('invalid_settings');
        }
        foreach (['enabled', 'clear_token'] as $key) {
            if (!is_bool($input[$key] ?? false)) throw new InvalidArgumentException('invalid_settings');
        }
        $stored = is_string($old['token'] ?? null) ? $old['token'] : '';
        $token = $stored;
        $replacement = trim($input['token'] ?? '');
        if (self::constant_token() === null) {
            if (!empty($input['clear_token'])) $token = '';
            if ($replacement !== '') $token = $replacement;
        }
        $effective = self::constant_token() ?? $token;
        if ($effective !== '' && !self::valid_token($effective)) throw new InvalidArgumentException('invalid_settings');
        $password = $input['password'] ?? '';
        if ($password !== '' && (strlen($password) < 4 || strlen($password) > 128 || trim($password) !== $password)) {
            throw new InvalidArgumentException('invalid_settings');
        }
        $password_hash = is_string($old['password_hash'] ?? null) ? $old['password_hash'] : '';
        $password_changed = $password !== '';
        if ($password_changed) $password_hash = wp_hash_password($password);
        $bot_changed = $effective !== (self::constant_token() ?? $stored);
        $secret = is_string($old['webhook_secret'] ?? null) ? $old['webhook_secret'] : '';
        if ($bot_changed || !preg_match('/^[a-f0-9]{64}$/D', $secret)) $secret = bin2hex(random_bytes(32));
        $connected = !$bot_changed && !empty($old['webhook_connected']) &&
            hash_equals(hash('sha256', $effective), (string) ($old['webhook_bot'] ?? ''));
        $enabled = !empty($input['enabled']) && $connected && $effective !== '' &&
            $password_hash !== '' && !$password_changed;
        $access = is_string($old['access_revision'] ?? null) ? $old['access_revision'] : '';
        if ($password_changed || $bot_changed || $access === '') $access = wp_generate_uuid4();
        $changed = $bot_changed || $password_changed || $enabled !== !empty($old['enabled']);
        $revision = is_string($old['revision'] ?? null) ? $old['revision'] : '';
        return [
            'token' => $token, 'password_hash' => $password_hash,
            'webhook_secret' => $secret, 'webhook_connected' => $connected,
            'webhook_bot' => $connected ? hash('sha256', $effective) : '',
            'access_revision' => $access, 'enabled' => $enabled,
            'revision' => $changed || $revision === '' ? wp_generate_uuid4() : $revision,
            'enabled_since' => $changed || $revision === '' ? max(0, $now) : max(0, (int) ($old['enabled_since'] ?? 0)),
        ];
    }

    private static function authorize(string $action): void {
        if (!current_user_can('manage_options')) wp_die('Недостаточно прав.', '', ['response' => 403]);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_die('Используйте форму настроек.', '', ['response' => 405]);
        check_admin_referer($action);
    }

    private static function text(string $key): string {
        if (!isset($_POST[$key])) return '';
        if (!is_string($_POST[$key])) throw new InvalidArgumentException('invalid_settings');
        return wp_unslash($_POST[$key]);
    }

    private static function flag(string $key): bool {
        if (!isset($_POST[$key])) return false;
        if (!is_string($_POST[$key]) || $_POST[$key] !== '1') throw new InvalidArgumentException('invalid_settings');
        return true;
    }

    private static function finish(string $code): void {
        set_transient('jrtg_notice_' . get_current_user_id(), $code, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=joyrent-telegram'));
        exit;
    }

    public static function save(): void {
        self::authorize('jrtg_save');
        try {
            $password = self::text('password');
            $settings = self::candidate(self::raw(), [
                'enabled' => self::flag('enabled'), 'token' => self::text('token'),
                'password' => $password, 'clear_token' => self::flag('clear_token'),
            ], time());
            update_option(self::OPTION, $settings, false);
            self::finish($password !== '' ? 'password_saved' : 'saved');
        } catch (InvalidArgumentException $e) {
            self::finish('invalid_settings');
        }
    }

    public static function connect(): void {
        self::authorize('jrtg_connect');
        $settings = self::get();
        if (!self::valid_token($settings['token']) || $settings['password_hash'] === '' ||
            !preg_match('/^[a-f0-9]{64}$/D', $settings['webhook_secret'])) self::finish('not_configured');
        $stamp = hash('sha256', $settings['token'] . $settings['webhook_secret']);
        $result = JRTG_Api::connect_webhook($settings, rest_url('joyrent-telegram/v1/update'));
        if (($result['status'] ?? '') !== 'ok') self::finish((string) ($result['error'] ?? 'telegram_error'));
        $current = self::get();
        if (!hash_equals($stamp, hash('sha256', $current['token'] . $current['webhook_secret']))) self::finish('config_changed');
        $raw = self::raw();
        $raw['webhook_connected'] = true;
        $raw['webhook_bot'] = hash('sha256', $settings['token']);
        update_option(self::OPTION, $raw, false);
        self::finish('connected');
    }

    public static function test(): void {
        self::authorize('jrtg_test');
        $s = self::get();
        if (!$s['webhook_connected']) self::finish('not_configured');
        $subscribers = JRTG_Subscriptions::all();
        if (!$subscribers) self::finish('no_subscribers');
        $queued = false;
        foreach ($subscribers as $id => $subscriber) {
            if (function_exists('as_enqueue_async_action')) {
                $queued = (bool) as_enqueue_async_action('joyrent_telegram_subscriber_test', [(string) $id], 'joyrent-telegram', true) || $queued;
            } else {
                $scheduled = wp_schedule_single_event(time() + 1, 'joyrent_telegram_subscriber_test', [(string) $id], true);
                $queued = ($scheduled !== false && !is_wp_error($scheduled)) || $queued;
            }
        }
        self::finish($queued ? 'test_queued' : 'queue_unavailable');
    }

    public static function send_test($id): void {
        $s = self::get();
        if (!$s['webhook_connected'] || !isset(JRTG_Subscriptions::all()[(string) $id])) return;
        $s['chat_id'] = (string) $id;
        JRTG_Api::send_message('JOYRENT: тестовое уведомление. Вы подписаны на новые брони. Для отключения отправьте /stop.', $s);
    }

    public static function error_label(string $code): string {
        $labels = [
            'saved' => 'Настройки сохранены.',
            'password_saved' => 'Пароль сохранён. Подписчики должны заново отправить /start и ввести пароль. Затем включите уведомления.',
            'connected' => 'Бот подключён. Откройте его в Telegram, отправьте /start и введите пароль.',
            'test_queued' => 'Тестовое сообщение поставлено в очередь для всех активных подписчиков.',
            'no_subscribers' => 'Подписчиков пока нет. Отправьте боту /start и введите пароль.',
            'invalid_settings' => 'Настройки не сохранены. Проверьте токен и пароль длиной от 4 до 128 символов.',
            'not_configured' => 'Сначала сохраните токен и пароль, затем нажмите «Подключить бота».',
            'webhook_url' => 'Для подключения бота сайт должен открываться по HTTPS.',
            'bot_unauthorized' => 'Telegram отклонил токен. Проверьте его в BotFather.',
            'chat_forbidden' => 'Получатель заблокировал бота. Для подписки нужно запустить или разблокировать его.',
            'bad_request' => 'Telegram отклонил запрос. Проверьте настройки бота и доступность сайта.',
            'rate_limited' => 'Telegram временно ограничил отправку. Повторите позже.',
            'http_unknown' => 'Ответ Telegram не получен. Проверьте личные сообщения перед ручным повтором.',
            'invalid_response' => 'Отправка не подтверждена. Проверьте Telegram перед повтором.',
            'invalid_ack' => 'Telegram не подтвердил отправку. Проверьте сообщения перед повтором.',
            'disabled' => 'Уведомления выключены или бот не подключён.',
            'config_changed' => 'Настройки бота изменились. Старые уведомления автоматически не отправляются.',
            'unsubscribed' => 'Получатель отписался или изменился пароль доступа.',
            'queue_unavailable' => 'Не удалось запланировать отправку. Проверьте Action Scheduler и WP-Cron.',
            'retry_limit' => 'Автоматические попытки исчерпаны.',
            'send_interrupted' => 'Отправка прервалась без подтверждения. Проверьте Telegram перед повтором.',
            'order_unavailable' => 'Бронь больше не ожидает подтверждения.',
            'webhook_mismatch' => 'Telegram не подтвердил адрес подключения. Подключите бота повторно.',
        ];
        return $labels[$code] ?? 'Не удалось выполнить действие. Проверьте настройки и подключение.';
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) return;
        $s = self::get();
        $notice = get_transient('jrtg_notice_' . get_current_user_id());
        delete_transient('jrtg_notice_' . get_current_user_id());
        $notice = is_string($notice) ? $notice : '';
        $subscribers = class_exists('JRTG_Subscriptions') ? JRTG_Subscriptions::all() : [];
        $configured = $s['webhook_connected'] && $s['password_hash'] !== '';
        ?>
        <div class="wrap">
            <h1>JOYRENT → Telegram</h1>
            <p>Новые брони приходят всем, кто запустил бота и ввёл правильный пароль.</p>
            <?php if ($notice !== ''): ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html(self::error_label($notice)); ?></p></div><?php endif; ?>
            <div style="max-width:850px;background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:20px 0">
                <h2>Подключение</h2>
                <ol>
                    <li>Создайте бота через <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">BotFather</a> командой <code>/newbot</code>.</li>
                    <li>Сохраните токен бота и пароль доступа в форме ниже. Затем нажмите «Подключить бота».</li>
                    <li>Каждый получатель открывает бота и отправляет <code>/start</code>. Бот просит пароль и подключает человека к новым броням.</li>
                    <li>Отправьте тест подписчикам. Когда он придёт, включите уведомления и сохраните настройки.</li>
                </ol>
                <p><code>/stop</code> отключает уведомления для отправившего команду человека. Используйте отдельного бота JOYRENT.</p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:850px">
                <input type="hidden" name="action" value="jrtg_save"><?php wp_nonce_field('jrtg_save'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="jrtg-token">Токен бота</label></th><td>
                        <input id="jrtg-token" type="password" name="token" value="" class="regular-text" autocomplete="new-password" spellcheck="false"<?php echo self::constant_token() !== null ? ' disabled' : ''; ?>>
                        <p class="description"><?php echo self::constant_token() !== null ? 'Токен задан в wp-config.php.' : ($s['token'] !== '' ? 'Токен сохранён. Пустое поле сохраняет прежнее значение.' : 'Вставьте токен из BotFather. После сохранения он не отображается.'); ?></p>
                        <?php if (self::constant_token() === null): ?><label><input type="checkbox" name="clear_token" value="1"> Удалить токен</label><?php endif; ?>
                    </td></tr>
                    <tr><th scope="row"><label for="jrtg-password">Пароль доступа</label></th><td>
                        <input id="jrtg-password" type="password" name="password" value="" class="regular-text" autocomplete="new-password" spellcheck="false">
                        <p class="description"><?php echo $s['password_hash'] !== '' ? 'Пароль сохранён. Пустое поле сохраняет его. Смена пароля требует повторной подписки всех получателей.' : 'Этот пароль получатели вводят боту после /start.'; ?></p>
                    </td></tr>
                    <tr><th scope="row">Подключение</th><td><?php echo $s['webhook_connected'] ? 'Бот подключён' : 'Сначала сохраните настройки и подключите бота'; ?></td></tr>
                    <tr><th scope="row"><label for="jrtg-enabled">Уведомления</label></th><td>
                        <label><input id="jrtg-enabled" type="checkbox" name="enabled" value="1"<?php echo $s['enabled'] ? ' checked' : ''; ?><?php echo !$configured ? ' disabled' : ''; ?>> Отправлять новые брони подписчикам</label>
                        <p class="description">Старые брони не рассылаются. Email-уведомления продолжают работать.</p>
                    </td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Сохранить настройки</button></p>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
                <input type="hidden" name="action" value="jrtg_connect"><?php wp_nonce_field('jrtg_connect'); ?>
                <button type="submit" class="button">Подключить бота</button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
                <input type="hidden" name="action" value="jrtg_test"><?php wp_nonce_field('jrtg_test'); ?>
                <button type="submit" class="button">Отправить тест подписчикам</button>
            </form>
            <h2>Подписчики: <?php echo count($subscribers); ?></h2>
            <?php if ($subscribers): ?><ul><?php foreach ($subscribers as $subscriber): ?><li><?php echo esc_html((string) ($subscriber['label'] ?? 'Подписчик Telegram')); ?></li><?php endforeach; ?></ul>
            <?php else: ?><p>После /start и правильного пароля получатели появятся здесь.</p><?php endif; ?>
            <p style="max-width:850px">Статус рассылки каждой брони отображается в её карточке WooCommerce. При неизвестном результате сначала проверьте Telegram: ручной повтор может создать дубликат.</p>
        </div>
        <?php
    }
}
