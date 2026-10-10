<?php
defined('ABSPATH') || exit;

/** Administrator-only bot connection; token, password hash and webhook secret stay server-side. */
final class JRTG_Settings {
    const OPTION = 'joyrent_telegram_settings';
    private const WRITE_LOCK = 'jrtg_settings_write';
    const TEST_OPTION = 'joyrent_telegram_test_status';
    private const TEST_LOCK = 'jrtg_test_status_write';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'menu']);
        foreach (['save', 'connect', 'test'] as $action) {
            add_action('admin_post_jrtg_' . $action, [self::class, $action]);
        }
        add_action('joyrent_telegram_subscriber_test', [self::class, 'send_test'], 10, 4);
    }

    public static function menu(): void {
        add_submenu_page('woocommerce', 'JOYRENT → Telegram', 'JOYRENT → Telegram',
            'manage_options', 'joyrent-telegram', [self::class, 'page']);
    }

    private static function raw(): array {
        // Another PHP worker may have changed this non-autoloaded option.
        wp_cache_delete(self::OPTION, 'options');
        wp_cache_delete('notoptions', 'options');
        $raw = get_option(self::OPTION, []);
        return is_array($raw) ? $raw : [];
    }

    private static function persist(array $settings): bool {
        update_option(self::OPTION, $settings, false);
        // update_option returns false both for unchanged data and failed writes.
        return self::raw() === $settings;
    }

    private static function release($owner): void {
        if (!$owner) return;
        try { JR_Lock::release(self::WRITE_LOCK, $owner); } catch (Throwable $ignored) {}
    }

    private static function connection_stamp(array $settings): string {
        // Include password/access and enabled revisions, not only the bot token.
        return hash('sha256', serialize($settings));
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
        $owner = false;
        $code = 'settings_unavailable';
        try {
            $owner = JR_Lock::acquire(self::WRITE_LOCK, 30);
            if (!$owner) {
                $code = 'settings_busy';
            } else {
                $password = self::text('password');
                $settings = self::candidate(self::raw(), [
                    'enabled' => self::flag('enabled'), 'token' => self::text('token'),
                    'password' => $password, 'clear_token' => self::flag('clear_token'),
                ], time());
                if (self::persist($settings)) $code = $password !== '' ? 'password_saved' : 'saved';
            }
        } catch (InvalidArgumentException $e) {
            $code = 'invalid_settings';
        } catch (Throwable $e) {
            $code = 'settings_unavailable';
        } finally {
            self::release($owner);
        }
        self::finish($code);
    }

    public static function connect(): void {
        self::authorize('jrtg_connect');
        $settings = self::get();
        if (!self::valid_token($settings['token']) || $settings['password_hash'] === '' ||
            !preg_match('/^[a-f0-9]{64}$/D', $settings['webhook_secret'])) self::finish('not_configured');
        $stamp = self::connection_stamp($settings);
        // Keep Telegram's two HTTP requests outside the short database write lock.
        $result = JRTG_Api::connect_webhook($settings, rest_url('joyrent-telegram/v1/update'));
        if (($result['status'] ?? '') !== 'ok') self::finish((string) ($result['error'] ?? 'telegram_error'));
        $owner = false;
        $code = 'settings_unavailable';
        try {
            $owner = JR_Lock::acquire(self::WRITE_LOCK, 30);
            if (!$owner) {
                $code = 'settings_busy';
            } elseif (!hash_equals($stamp, self::connection_stamp(self::get()))) {
                $code = 'config_changed';
            } else {
                $raw = self::raw();
                $raw['webhook_connected'] = true;
                $raw['webhook_bot'] = hash('sha256', $settings['token']);
                if (self::persist($raw)) $code = 'connected';
            }
        } catch (Throwable $e) {
            $code = 'settings_unavailable';
        } finally {
            self::release($owner);
        }
        self::finish($code);
    }

    private static function test_state(): array {
        wp_cache_delete(self::TEST_OPTION, 'options');
        wp_cache_delete('notoptions', 'options');
        $state = get_option(self::TEST_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function test_write(array $state): bool {
        update_option(self::TEST_OPTION, $state, false);
        return self::test_state() === $state;
    }

    private static function test_release($owner): void {
        if ($owner) {
            try { JR_Lock::release(self::TEST_LOCK, $owner); } catch (Throwable $ignored) {}
        }
    }

    private static function test_config(array $settings): string {
        return hash('sha256', serialize([
            $settings['token'], $settings['access_revision'], $settings['webhook_secret'],
            $settings['webhook_connected'],
        ]));
    }

    /** Opaque tickets keep recipient IDs and credentials out of health diagnostics. */
    private static function prepare_tests(int $count, array $settings): array {
        $owner = false;
        try {
            $owner = JR_Lock::acquire(self::TEST_LOCK, 10);
            if (!$owner) return [];
            $state = [
                'batch' => wp_generate_uuid4(), 'config' => self::test_config($settings),
                'created_at' => time(), 'updated_at' => time(), 'results' => [],
            ];
            for ($i = 0; $i < $count; $i++) {
                $state['results'][wp_generate_uuid4()] = ['status' => 'planning', 'updated_at' => time()];
            }
            return self::test_write($state) ? $state : [];
        } catch (Throwable $ignored) {
            return [];
        } finally {
            self::test_release($owner);
        }
    }

    /** Legacy jobs may share a legacy batch, but never supersede a newer admin test. */
    private static function prepare_legacy_test(array $settings): ?array {
        $owner = false;
        try {
            $owner = JR_Lock::acquire(self::TEST_LOCK, 10);
            if (!$owner) return null;
            $state = self::test_state();
            if (!empty($state['results']) && empty($state['legacy'])) return [];
            if (empty($state['batch']) || !is_array($state['results'] ?? null) ||
                ($state['config'] ?? '') !== self::test_config($settings)) {
                $state = [
                    'batch' => wp_generate_uuid4(), 'config' => self::test_config($settings),
                    'created_at' => time(), 'updated_at' => time(), 'legacy' => true, 'results' => [],
                ];
            }
            $ticket = wp_generate_uuid4();
            $state['results'][$ticket] = ['status' => 'planning', 'updated_at' => time()];
            $state['updated_at'] = time();
            return self::test_write($state) ? $state + ['ticket' => $ticket] : null;
        } catch (Throwable $ignored) {
            return null;
        } finally {
            self::test_release($owner);
        }
    }

    private static function test_result(string $batch, string $ticket, string $status, string $error = ''): bool {
        $owner = false;
        try {
            $owner = JR_Lock::acquire(self::TEST_LOCK, 10);
            if (!$owner) return false;
            $state = self::test_state();
            if (($state['batch'] ?? '') !== $batch || !isset($state['results'][$ticket])) return false;
            $before = $state['results'][$ticket]['status'] ?? '';
            // A fast worker may finish before the scheduler's acknowledgment is recorded.
            if ($status === 'queued' && in_array($before, ['queued', 'sending', 'sent', 'failed', 'unknown'], true)) return true;
            if (!in_array($before, ['planning', 'queued', 'sending'], true) ||
                ($status === 'sending' && !in_array($before, ['planning', 'queued'], true))) return false;
            $state['results'][$ticket] = ['status' => $status, 'updated_at' => time()];
            if ($error !== '') $state['results'][$ticket]['error'] = self::test_error($error);
            $state['updated_at'] = time();
            return self::test_write($state);
        } catch (Throwable $ignored) {
            return false;
        } finally {
            self::test_release($owner);
        }
    }

    private static function test_error($error): string {
        $allowed = [
            'not_configured', 'config_changed', 'unsubscribed', 'queue_unavailable',
            'http_unknown', 'invalid_response', 'invalid_ack', 'rate_limited',
            'telegram_server_error', 'bot_unauthorized', 'chat_forbidden', 'bad_request',
            'telegram_error', 'send_interrupted',
        ];
        return is_string($error) && in_array($error, $allowed, true) ? $error : 'invalid_response';
    }

    private static function schedule_test(array $args, int $at = 0): bool {
        if ($at === 0 && function_exists('as_enqueue_async_action')) {
            try {
                if (as_enqueue_async_action('joyrent_telegram_subscriber_test', $args, 'joyrent-telegram', true)) return true;
            } catch (Throwable $ignored) {}
        }
        try {
            $scheduled = wp_schedule_single_event($at ?: time() + 1, 'joyrent_telegram_subscriber_test', $args, true);
            return $scheduled !== false && !is_wp_error($scheduled);
        } catch (Throwable $ignored) {
            return false;
        }
    }

    public static function test(): void {
        self::authorize('jrtg_test');
        $s = self::get();
        if (!$s['webhook_connected']) self::finish('not_configured');
        $subscribers = JRTG_Subscriptions::all();
        if (!$subscribers) self::finish('no_subscribers');
        $state = self::prepare_tests(count($subscribers), $s);
        if (!$state) self::finish('test_status_unavailable');
        $tickets = array_keys($state['results']);
        $queued = false;
        $confirmed = true;
        $index = 0;
        foreach ($subscribers as $id => $subscriber) {
            $ticket = $tickets[$index++];
            $args = [(string) $id, $state['batch'], $ticket, (string) ($subscriber['generation'] ?? '')];
            if (self::schedule_test($args)) {
                $queued = true;
                $confirmed = self::test_result($state['batch'], $ticket, 'queued') && $confirmed;
            } else {
                $confirmed = self::test_result($state['batch'], $ticket, 'failed', 'queue_unavailable') && $confirmed;
            }
        }
        self::finish(!$confirmed ? 'test_status_unavailable' : ($queued ? 'test_queued' : 'queue_unavailable'));
    }

    public static function send_test($id, $batch = '', $ticket = '', $generation = ''): void {
        if (!is_scalar($id) || !preg_match('/^[1-9][0-9]{0,19}$/D', (string) $id) ||
            !is_string($batch) || !is_string($ticket) || !is_string($generation)) return;
        $s = self::get();
        if ($batch === '' && $ticket === '') {
            // Jobs queued by 1.1.0 carried only the destination ID.
            $legacy = self::prepare_legacy_test($s);
            if ($legacy === null) throw new RuntimeException('Telegram test status unavailable.');
            if (!$legacy) return;
            $batch = $legacy['batch'];
            $ticket = $legacy['ticket'];
        }
        $state = self::test_state();
        if (($state['batch'] ?? '') !== $batch ||
            !in_array($state['results'][$ticket]['status'] ?? '', ['planning', 'queued'], true)) return;
        $error = '';
        if (!$s['webhook_connected'] || !self::valid_token($s['token'])) $error = 'not_configured';
        elseif (!hash_equals((string) ($state['config'] ?? ''), self::test_config($s))) $error = 'config_changed';
        $subscribers = JRTG_Subscriptions::all();
        if ($error === '' && (!isset($subscribers[(string) $id]) ||
            ($generation !== '' && !hash_equals($generation, (string) ($subscribers[(string) $id]['generation'] ?? ''))))) $error = 'unsubscribed';
        if ($error !== '') {
            if (!self::test_result($batch, $ticket, 'failed', $error)) throw new RuntimeException('Telegram test status unavailable.');
            return;
        }
        if (!self::test_result($batch, $ticket, 'sending')) {
            if (!self::schedule_test([(string) $id, $batch, $ticket, $generation], time() + 5)) {
                throw new RuntimeException('Telegram test status unavailable.');
            }
            return;
        }
        $s['chat_id'] = (string) $id;
        try {
            $result = JRTG_Api::send_message('JOYRENT: тестовое уведомление. Вы подписаны на новые брони. Для отключения отправьте /stop.', $s);
        } catch (Throwable $ignored) {
            $result = ['status' => 'unknown', 'error' => 'http_unknown'];
        }
        $status = ($result['status'] ?? '') === 'sent' ? 'sent' :
            (in_array($result['status'] ?? '', ['failed', 'retry'], true) ? 'failed' : 'unknown');
        if (!self::test_result($batch, $ticket, $status, $status === 'sent' ? '' : ($result['error'] ?? 'invalid_response'))) {
            throw new RuntimeException('Telegram test status unavailable.');
        }
    }

    private static function diagnostic_time($timestamp): string {
        return is_int($timestamp) && $timestamp > 0 ? wp_date('d.m.Y H:i:s', $timestamp) : 'ещё не было';
    }

    private static function diagnostics(): void {
        echo '<div class="card" style="max-width:850px"><h2>Фоновая отправка</h2>';
        $dispatch = [];
        try {
            if (class_exists('JRTG_Dispatcher')) $dispatch = JRTG_Dispatcher::status();
        } catch (Throwable $ignored) {}
        $jobs = is_array($dispatch['jobs'] ?? null) ? $dispatch['jobs'] : [];
        $due = array_filter($jobs, static fn($at): bool => is_numeric($at) && (int) $at <= time());
        echo '<p>'.esc_html('В очереди броней: '.count($jobs).'. Готовы к отправке: '.count($due).'.').'</p>';
        echo '<p>'.esc_html('Последний запуск: '.self::diagnostic_time($dispatch['last_start'] ?? 0).'. Последнее завершение: '.self::diagnostic_time($dispatch['last_finish'] ?? 0).'.').'</p>';
        $error = $dispatch['error'] ?? '';
        if ($error !== '') {
            $label = match ($error) {
                'loopback_failed' => 'Не удалось запустить запрос к собственному сайту. Проверьте loopback-запросы хостинга и запуск WP-Cron.',
                'worker_failed' => 'Фоновая отправка завершилась с ошибкой. Проверьте журнал PHP и запланированные действия WooCommerce.',
                default => 'Последняя фоновая отправка сообщила об ошибке. Проверьте журнал PHP и запланированные действия WooCommerce.',
            };
            echo '<p>'.esc_html($label).'</p>';
        }
        if ($due && min(array_map('intval', $due)) < time() - 60) {
            echo '<p>Очередь задерживается больше минуты. Проверьте фоновые запросы и WP-Cron: для отправки не требуется новая команда /start.</p>';
        }
        $test = self::test_state();
        echo '<h3>Последний тест</h3>';
        if (empty($test['results']) || !is_array($test['results'])) {
            echo '<p>Тест ещё не отправлялся.</p>';
        } else {
            $counts = ['planning' => 0, 'queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'unknown' => 0];
            $errors = [];
            foreach ($test['results'] as $entry) {
                if (!is_array($entry)) continue;
                $status = is_string($entry['status'] ?? null) ? $entry['status'] : 'unknown';
                if ($status === 'sending' && (int) ($entry['updated_at'] ?? 0) < time() - 60) {
                    $status = 'unknown';
                    $entry['error'] = 'send_interrupted';
                }
                $counts[array_key_exists($status, $counts) ? $status : 'unknown']++;
                if (!empty($entry['error'])) $errors[self::test_error($entry['error'])] = true;
            }
            echo '<p>'.esc_html('Создан: '.self::diagnostic_time($test['created_at'] ?? 0).'. Обновлён: '.self::diagnostic_time($test['updated_at'] ?? 0).'.').'</p>';
            foreach (['planning'=>'Планирование не подтверждено', 'queued'=>'В очереди', 'sending'=>'Отправляется', 'sent'=>'Telegram подтвердил отправку', 'failed'=>'Не отправлено', 'unknown'=>'Результат неизвестен'] as $status => $label) {
                if ($counts[$status]) echo '<p>'.esc_html($label.': '.$counts[$status]).'</p>';
            }
            foreach (array_keys($errors) as $code) echo '<p>'.esc_html(self::error_label($code)).'</p>';
            if (($counts['planning'] || $counts['queued']) && (int) ($test['created_at'] ?? 0) < time() - 60) echo '<p>Тест ожидает фонового запуска больше минуты. Проверьте Action Scheduler и WP-Cron.</p>';
        }
        echo '</div>';
    }

    public static function error_label(string $code): string {
        $labels = [
            'saved' => 'Настройки сохранены.',
            'settings_busy' => 'Настройки меняются в другом запросе. Повторите действие.',
            'settings_unavailable' => 'Не удалось сохранить настройки. Повторите действие после восстановления базы данных.',
            'password_saved' => 'Пароль сохранён. Подписчики должны заново отправить /start и ввести пароль. Затем включите уведомления.',
            'connected' => 'Webhook бота зарегистрирован. Откройте его в Telegram, отправьте /start и введите пароль.',
            'test_queued' => 'Тест поставлен в очередь. Результат отправки отображается в блоке «Последний тест».',
            'test_status_unavailable' => 'Не удалось подтвердить статус теста. Проверьте базу данных и запланированные действия; перед повтором проверьте Telegram.',
            'telegram_server_error' => 'Telegram временно недоступен. Проверьте статус теста и повторите его позже.',
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
                    <tr><th scope="row">Подключение</th><td><?php echo $s['webhook_connected'] ? 'Webhook зарегистрирован (сохранённая настройка)' : 'Сначала сохраните настройки и подключите бота'; ?></td></tr>
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
            <p>Текущая доступность webhook здесь не проверяется. Если бот не отвечает, проверьте доступ к сайту и повторите «Подключить бота».</p>
            <?php self::diagnostics(); ?>
            <h2>Подписчики: <?php echo count($subscribers); ?></h2>
            <?php if ($subscribers): ?><ul><?php foreach ($subscribers as $subscriber): ?><li><?php echo esc_html((string) ($subscriber['label'] ?? 'Подписчик Telegram')); ?></li><?php endforeach; ?></ul>
            <?php else: ?><p>После /start и правильного пароля получатели появятся здесь.</p><?php endif; ?>
            <p style="max-width:850px">Статус рассылки каждой брони отображается в её карточке WooCommerce. При неизвестном результате сначала проверьте Telegram: ручной повтор может создать дубликат.</p>
        </div>
        <?php
    }
}
