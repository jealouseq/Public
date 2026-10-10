<?php
/**
 * Private Telegram configuration and authenticated administrator actions.
 */
defined('ABSPATH') || exit;

final class JRTG_Settings {
    const OPTION = 'joyrent_telegram_settings';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_jrtg_save', [self::class, 'save']);
        add_action('admin_post_jrtg_test', [self::class, 'test']);
        add_action('admin_post_jrtg_discover', [self::class, 'discover']);
        add_action('admin_post_jrtg_select_chat', [self::class, 'select_chat']);
    }

    public static function menu(): void {
        add_submenu_page('woocommerce', 'JOYRENT → Telegram', 'JOYRENT → Telegram', 'manage_options', 'joyrent-telegram', [self::class, 'page']);
    }

    private static function raw(): array {
        $raw = get_option(self::OPTION, []);
        return is_array($raw) ? $raw : [];
    }

    private static function constant_token(): ?string {
        if (!defined('JOYRENT_TELEGRAM_BOT_TOKEN')) {
            return null;
        }
        return is_string(JOYRENT_TELEGRAM_BOT_TOKEN) ? trim(JOYRENT_TELEGRAM_BOT_TOKEN) : '';
    }

    public static function get(): array {
        $raw = self::raw();
        return [
            'enabled' => !empty($raw['enabled']),
            'token' => self::constant_token() ?? (is_string($raw['token'] ?? null) ? $raw['token'] : ''),
            'chat_id' => is_string($raw['chat_id'] ?? null) ? $raw['chat_id'] : '',
            'enabled_since' => max(0, (int) ($raw['enabled_since'] ?? 0)),
            'revision' => is_string($raw['revision'] ?? null) ? $raw['revision'] : '',
        ];
    }

    public static function valid_token(string $token): bool {
        return (bool) preg_match('/^[0-9]{6,15}:[A-Za-z0-9_-]{20,100}$/D', $token);
    }

    public static function valid_chat(string $chat): bool {
        return (bool) preg_match('/^-?[1-9][0-9]{0,19}$/D', $chat);
    }

    public static function ready(): bool {
        $settings = self::get();
        return $settings['enabled'] && self::valid_token($settings['token']) && self::valid_chat($settings['chat_id']);
    }

    /**
     * Build a new configuration without modifying the saved option on failure.
     * The empty password field always preserves the saved credential.
     */
    public static function candidate(array $old, array $input, int $now): array {
        if (!is_string($input['token'] ?? '') || !is_string($input['chat_id'] ?? '') ||
            !is_bool($input['enabled'] ?? false) || !is_bool($input['clear_token'] ?? false)) {
            throw new InvalidArgumentException('invalid_settings');
        }
        $stored = is_string($old['token'] ?? null) ? $old['token'] : '';
        $previous_chat = is_string($old['chat_id'] ?? null) ? $old['chat_id'] : '';
        $constant = self::constant_token();
        $replacement = trim($input['token'] ?? '');
        $enabled = $input['enabled'] ?? false;
        $chat = trim($input['chat_id'] ?? '');
        $token = $stored;
        if ($constant === null) {
            if (!empty($input['clear_token'])) {
                $token = '';
            }
            if ($replacement !== '') {
                $token = $replacement;
            }
        }
        $effective = $constant ?? $token;
        if (($effective !== '' && !self::valid_token($effective)) ||
            ($chat !== '' && !self::valid_chat($chat)) ||
            ($enabled && ($effective === '' || $chat === ''))) {
            throw new InvalidArgumentException('invalid_settings');
        }
        $changed = $enabled !== !empty($old['enabled']) ||
            $chat !== $previous_chat || $effective !== ($constant ?? $stored);
        $revision = is_string($old['revision'] ?? null) ? $old['revision'] : '';
        return [
            'enabled' => $enabled,
            'token' => $token,
            'chat_id' => $chat,
            'enabled_since' => $changed || $revision === '' ? max(0, $now) : max(0, (int) ($old['enabled_since'] ?? 0)),
            'revision' => $changed || $revision === '' ? wp_generate_uuid4() : $revision,
        ];
    }

    private static function authorize(string $action): void {
        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав.', '', ['response' => 403]);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('Используйте форму настроек.', '', ['response' => 405]);
        }
        check_admin_referer($action);
    }

    private static function post_string(string $key): string {
        if (!isset($_POST[$key])) {
            return '';
        }
        if (!is_string($_POST[$key])) {
            throw new InvalidArgumentException('invalid_settings');
        }
        return wp_unslash($_POST[$key]);
    }

    private static function post_flag(string $key): bool {
        if (!isset($_POST[$key])) {
            return false;
        }
        if (!is_string($_POST[$key]) || $_POST[$key] !== '1') {
            throw new InvalidArgumentException('invalid_settings');
        }
        return true;
    }

    private static function finish(string $code, array $chats = []): void {
        set_transient('jrtg_notice_' . get_current_user_id(), ['code' => $code, 'chats' => $chats], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=joyrent-telegram'));
        exit;
    }

    public static function save(): void {
        self::authorize('jrtg_save');
        try {
            $settings = self::candidate(self::raw(), [
                'enabled' => self::post_flag('enabled'),
                'token' => self::post_string('token'),
                'chat_id' => self::post_string('chat_id'),
                'clear_token' => self::post_flag('clear_token'),
            ], time());
            update_option(self::OPTION, $settings, false);
            self::finish('saved');
        } catch (InvalidArgumentException $e) {
            self::finish('invalid_settings');
        }
    }

    public static function test(): void {
        self::authorize('jrtg_test');
        if (!class_exists('JRTG_Api')) {
            self::finish('dependencies_missing');
        }
        $result = JRTG_Api::send_message("JOYRENT: тестовое уведомление.\nЕсли вы видите это сообщение, бот может отправлять новые брони в этот чат.");
        self::finish(($result['status'] ?? '') === 'sent' ? 'test_sent' : (string) ($result['error'] ?? 'telegram_error'));
    }

    public static function discover(): void {
        self::authorize('jrtg_discover');
        if (!class_exists('JRTG_Api')) {
            self::finish('dependencies_missing');
        }
        $result = JRTG_Api::get_chats();
        $chats = [];
        foreach (($result['chats'] ?? []) as $chat) {
            if (is_array($chat) && is_string($chat['id'] ?? null) && self::valid_chat($chat['id']) && is_string($chat['label'] ?? null)) {
                $chats[] = ['id' => $chat['id'], 'label' => $chat['label']];
            }
            if (count($chats) >= 20) {
                break;
            }
        }
        self::finish(($result['status'] ?? '') === 'ok' ? ($chats ? 'chats_found' : 'no_chats') : (string) ($result['error'] ?? 'telegram_error'), $chats);
    }

    public static function select_chat(): void {
        self::authorize('jrtg_select_chat');
        try {
            $old = self::raw();
            $settings = self::candidate($old, [
                'enabled' => !empty($old['enabled']),
                'token' => '',
                'chat_id' => self::post_string('chat_id'),
                'clear_token' => false,
            ], time());
            if (!self::valid_chat($settings['chat_id'])) {
                throw new InvalidArgumentException('invalid_settings');
            }
            update_option(self::OPTION, $settings, false);
            self::finish('chat_saved');
        } catch (InvalidArgumentException $e) {
            self::finish('invalid_settings');
        }
    }

    public static function error_label(string $code): string {
        $labels = [
            'saved' => 'Настройки сохранены. Новые брони будут отправляться, если уведомления включены.',
            'chat_saved' => 'Чат выбран. Проверьте подключение тестовым сообщением и включите уведомления.',
            'test_sent' => 'Тестовое сообщение отправлено: Telegram подтвердил получение.',
            'chats_found' => 'Найдены чаты. Выберите, куда отправлять брони.',
            'no_chats' => 'Чаты пока не найдены. Откройте своего бота в Telegram, нажмите «Запустить» или отправьте /start, затем снова нажмите «Найти чат».',
            'invalid_settings' => 'Настройки не сохранены. Проверьте токен бота и числовой ID чата. Для включения нужны оба значения.',
            'not_configured' => 'Сначала сохраните действительный токен бота и ID чата.',
            'bot_unauthorized' => 'Telegram отклонил токен. Проверьте его в BotFather и сохраните снова.',
            'chat_forbidden' => 'Бот не может писать в этот чат. Запустите бота, проверьте ID и права в группе.',
            'bad_request' => 'Telegram отклонил запрос. Проверьте ID чата и доступ бота.',
            'bot_webhook_conflict' => 'Этот бот уже подключён к другому сервису через webhook. Создайте отдельного бота для JOYRENT или укажите ID чата вручную.',
            'rate_limited' => 'Telegram временно ограничил частоту сообщений. Повторите тест позже.',
            'telegram_server_error' => 'Telegram временно недоступен. Повторите тест позже.',
            'http_unknown' => 'Ответ Telegram не получен. Проверьте чат: сообщение могло дойти.',
            'invalid_response' => 'Не удалось подтвердить отправку. Проверьте чат: сообщение могло дойти.',
            'invalid_ack' => 'Telegram не подтвердил отправку. Проверьте чат: сообщение могло дойти.',
            'dependencies_missing' => 'Для уведомлений нужны активные WooCommerce и JOYRENT Rentals.',
            'disabled' => 'Уведомления выключены или настройки не заполнены. Проверьте подключение и включите отправку.',
            'config_changed' => 'Настройки получателя изменились после постановки в очередь. Проверьте текущий чат и отправьте уведомление вручную при необходимости.',
            'queue_unavailable' => 'Не удалось запланировать отправку. Проверьте Action Scheduler и WP-Cron; затем повторите вручную.',
            'send_interrupted' => 'Отправка прервалась без подтверждения. Сначала проверьте Telegram: сообщение могло дойти.',
            'retry_limit' => 'Автоматические попытки исчерпаны. Исправьте причину сбоя, затем повторите отправку вручную.',
            'order_unavailable' => 'Бронь больше не ожидает подтверждения или не завершена. Уведомление не отправлено.',
        ];
        return $labels[$code] ?? 'Не удалось выполнить действие. Проверьте настройки и повторите тест.';
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = self::get();
        $notice = get_transient('jrtg_notice_' . get_current_user_id());
        delete_transient('jrtg_notice_' . get_current_user_id());
        $notice = is_array($notice) ? $notice : [];
        $success = in_array($notice['code'] ?? '', ['saved', 'chat_saved', 'test_sent', 'chats_found'], true);
        ?>
        <div class="wrap">
            <h1>JOYRENT → Telegram</h1>
            <p>Уведомления о новых бронях для владельца. Контакты клиента, консоль, даты, игры и стоимость приходят в выбранный чат.</p>
            <?php if (!empty($notice['code'])): ?>
                <div class="notice <?php echo $success ? 'notice-success' : 'notice-warning'; ?> is-dismissible"><p><?php echo esc_html(self::error_label((string) $notice['code'])); ?></p></div>
            <?php endif; ?>
            <div style="max-width:850px;background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:20px 0">
                <h2>1. Создайте и запустите бота</h2>
                <ol>
                    <li>В Telegram откройте <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">официального BotFather</a> и отправьте <code>/newbot</code>.</li>
                    <li>Задайте имя и username бота. Полученный токен введите ниже и сохраните настройки.</li>
                    <li>Откройте созданного бота со своего аккаунта и нажмите «Запустить» или отправьте <code>/start</code>.</li>
                    <li>Нажмите «Найти чат», выберите свой чат и отправьте тестовое сообщение. Затем включите уведомления и сохраните.</li>
                </ol>
                <p>Используйте отдельного бота для JOYRENT. Для группы добавьте его в группу, разрешите отправку сообщений и отправьте <code>/start@username_бота</code>. Можно указать числовой ID чата вручную.</p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:850px">
                <input type="hidden" name="action" value="jrtg_save">
                <?php wp_nonce_field('jrtg_save'); ?>
                <h2>2. Настройки</h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="jrtg-enabled">Уведомления</label></th><td><label><input id="jrtg-enabled" type="checkbox" name="enabled" value="1"<?php echo $settings['enabled'] ? ' checked' : ''; ?>> Отправлять новые брони в Telegram</label>
                        <p class="description">Старые брони при включении не отправляются. Email-уведомления продолжают работать.</p></td></tr>
                    <tr><th scope="row"><label for="jrtg-token">Токен бота</label></th><td>
                        <input id="jrtg-token" type="password" name="token" value="" class="regular-text" autocomplete="new-password" spellcheck="false"<?php echo self::constant_token() !== null ? ' disabled' : ''; ?>>
                        <?php if (self::constant_token() !== null): ?>
                            <p class="description">Токен задан в wp-config.php через JOYRENT_TELEGRAM_BOT_TOKEN.</p>
                        <?php else: ?>
                            <p class="description"><?php echo $settings['token'] !== '' ? 'Токен сохранён. Оставьте поле пустым, чтобы сохранить его; новый токен заменит прежний.' : 'Вставьте токен, выданный BotFather. Он не показывается после сохранения.'; ?></p>
                            <label><input type="checkbox" name="clear_token" value="1"> Удалить сохранённый токен</label>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th scope="row"><label for="jrtg-chat">ID чата</label></th><td>
                        <input id="jrtg-chat" type="text" name="chat_id" value="<?php echo esc_attr($settings['chat_id']); ?>" class="regular-text" autocomplete="off" spellcheck="false">
                        <p class="description">Числовой ID, например 123456789 или -100123456789. Его можно получить кнопкой «Найти чат» после запуска бота.</p>
                    </td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Сохранить настройки</button></p>
            </form>
            <h2>3. Проверка подключения</h2>
            <p>Действия используют сохранённые настройки. Тест можно отправить до включения уведомлений.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
                <input type="hidden" name="action" value="jrtg_discover"><?php wp_nonce_field('jrtg_discover'); ?>
                <button type="submit" class="button">Найти чат</button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
                <input type="hidden" name="action" value="jrtg_test"><?php wp_nonce_field('jrtg_test'); ?>
                <button type="submit" class="button">Тестовое сообщение</button>
            </form>
            <?php if (!empty($notice['chats']) && is_array($notice['chats'])): ?>
                <ul>
                <?php foreach ($notice['chats'] as $chat): if (!is_array($chat) || !is_string($chat['id'] ?? null) || !self::valid_chat($chat['id'])) { continue; } ?>
                    <li><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="jrtg_select_chat">
                        <input type="hidden" name="chat_id" value="<?php echo esc_attr($chat['id']); ?>">
                        <?php wp_nonce_field('jrtg_select_chat'); ?>
                        <?php echo esc_html((string) ($chat['label'] ?? 'Чат')); ?> <code><?php echo esc_html($chat['id']); ?></code>
                        <button type="submit" class="button">Использовать этот чат</button>
                    </form></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p style="max-width:850px;margin-top:24px">Статус отправки каждой брони отображается в её карточке WooCommerce. При неизвестном результате сначала проверьте Telegram: повторная отправка вручную может создать дубликат. Токен хранится только на сервере; при компрометации перевыпустите его в BotFather.</p>
        </div>
        <?php
    }
}
