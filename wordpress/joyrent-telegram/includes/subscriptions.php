<?php
defined('ABSPATH') || exit;

/** Secret-authenticated bot conversation. Password text is never persisted. */
final class JRTG_Subscriptions {
    const OPTION = 'joyrent_telegram_subscribers';

    public static function boot(): void {
        add_action('rest_api_init', function(): void {
            register_rest_route('joyrent-telegram/v1', '/update', [
                'methods' => 'POST', 'callback' => [self::class, 'receive'],
                'permission_callback' => [self::class, 'authorize'],
            ]);
        });
    }

    public static function stamp(): string {
        $s = JRTG_Settings::get();
        return hash('sha256', ($s['token'] ?? '') . '|' . ($s['access_revision'] ?? ''));
    }

    private static function state(): array {
        wp_cache_delete(self::OPTION, 'options');
        $state = get_option(self::OPTION, []);
        $stamp = self::stamp();
        if (!is_array($state) || !is_string($state['bot'] ?? null) || !hash_equals($stamp, $state['bot'])) {
            return ['bot' => $stamp, 'subscribers' => [], 'pending' => [], 'seen' => []];
        }
        foreach (['subscribers', 'pending', 'seen'] as $key) {
            if (!is_array($state[$key] ?? null)) $state[$key] = [];
        }
        return $state;
    }

    public static function all(): array {
        return self::state()['subscribers'];
    }

    public static function authorize(WP_REST_Request $request) {
        $settings = JRTG_Settings::get();
        $secret = $settings['webhook_secret'] ?? '';
        $supplied = $request->get_header('x-telegram-bot-api-secret-token');
        if (!is_string($secret) || !preg_match('/^[a-f0-9]{64}$/D', $secret) ||
            !is_string($supplied) || !hash_equals($secret, $supplied) ||
            empty($settings['password_hash']) || empty($settings['token']) || empty($settings['webhook_connected'])) {
            return new WP_Error('jrtg_forbidden', 'Доступ запрещён.', ['status' => 403]);
        }
        return true;
    }

    public static function receive(WP_REST_Request $request) {
        if (strlen($request->get_body()) > 65536) {
            return new WP_Error('jrtg_payload', 'Сообщение слишком большое.', ['status' => 413]);
        }
        $update = $request->get_json_params();
        if (!is_array($update)) return new WP_REST_Response(['ok' => true]);
        $message = $update['message'] ?? null;
        $update_id = $update['update_id'] ?? null;
        if (!is_int($update_id) || $update_id <= 0 || !is_array($message) ||
            ($message['chat']['type'] ?? '') !== 'private' || !empty($message['from']['is_bot']) ||
            (!is_int($message['chat']['id'] ?? null) && !is_string($message['chat']['id'] ?? null)) ||
            (string) ($message['from']['id'] ?? '') !== (string) $message['chat']['id'] ||
            !preg_match('/^[1-9][0-9]{0,19}$/D', (string) $message['chat']['id']) ||
            !is_string($message['text'] ?? null) || strlen($message['text']) > 512) {
            return new WP_REST_Response(['ok' => true]);
        }
        $chat = (string) $message['chat']['id'];
        $owner = false;
        try {
            $owner = JR_Lock::acquire('jrtg_subscriber_registry', 30);
            if (!$owner) return new WP_Error('jrtg_busy', 'Повторите позже.', ['status' => 503]);
            $now = time();
            $state = self::state();
            if (isset($state['seen'][(string) $update_id])) return new WP_REST_Response(['ok' => true]);
            foreach ($state['seen'] as $id => $at) if ((int) $at < $now - 86400) unset($state['seen'][$id]);
            foreach ($state['pending'] as $id => $pending) {
                if ((int) ($pending['until'] ?? 0) < $now && (int) ($pending['blocked_until'] ?? 0) < $now) unset($state['pending'][$id]);
            }
            $text = trim($message['text']);
            $reply = '';
            $pending = $state['pending'][$chat] ?? [];
            $stop = preg_match('/^\/stop(?:@[A-Za-z0-9_]+)?$/D', $text);
            $start = preg_match('/^\/start(?:@[A-Za-z0-9_]+)?(?:\s.*)?$/D', $text);
            if ($stop) {
                unset($state['subscribers'][$chat], $state['pending'][$chat]);
                $reply = 'Уведомления отключены. Чтобы подключиться снова, отправьте /start.';
            } elseif (isset($state['subscribers'][$chat])) {
                $reply = 'Вы уже подключены к уведомлениям JOYRENT. Для отключения отправьте /stop.';
            } elseif ((int) ($pending['blocked_until'] ?? 0) > $now) {
                $reply = 'Слишком много попыток. Попробуйте через 10 минут.';
            } elseif ($start) {
                if ((int) ($pending['window'] ?? 0) <= $now - 600) $pending = [];
                $pending['window'] = $pending['window'] ?? $now;
                $pending['failures'] = $pending['failures'] ?? 0;
                $pending['until'] = $now + 600;
                $state['pending'][$chat] = $pending;
                $reply = 'Напишите пароль для получения новых броней JOYRENT.';
            } elseif ((int) ($pending['until'] ?? 0) < $now) {
                $reply = 'Для подключения отправьте /start.';
            } elseif (wp_check_password($text, JRTG_Settings::get()['password_hash'])) {
                $first = is_string($message['chat']['first_name'] ?? null) ? $message['chat']['first_name'] : '';
                $last = is_string($message['chat']['last_name'] ?? null) ? $message['chat']['last_name'] : '';
                $label = sanitize_text_field(trim($first . ' ' . $last));
                $chars = preg_split('//u', $label, -1, PREG_SPLIT_NO_EMPTY);
                $label = is_array($chars) ? implode('', array_slice($chars, 0, 100)) : '';
                $state['subscribers'][$chat] = [
                    'label' => $label ?: 'Подписчик Telegram',
                    'generation' => wp_generate_uuid4(), 'since' => $now,
                ];
                unset($state['pending'][$chat]);
                $reply = 'Вы подключены! Новые брони JOYRENT будут приходить сюда. Для отключения отправьте /stop.';
            } else {
                if ((int) ($pending['window'] ?? 0) <= $now - 600) {
                    $pending['window'] = $now;
                    $pending['failures'] = 0;
                }
                $pending['failures'] = (int) ($pending['failures'] ?? 0) + 1;
                if ($pending['failures'] >= 5) $pending['blocked_until'] = $now + 600;
                $state['pending'][$chat] = $pending;
                $reply = $pending['failures'] >= 5 ? 'Слишком много попыток. Попробуйте через 10 минут.' : 'Неверный пароль. Попробуйте ещё раз.';
            }
            $state['seen'][(string) $update_id] = $now;
            if (count($state['seen']) > 1000) $state['seen'] = array_slice($state['seen'], -1000, null, true);
            update_option(self::OPTION, $state, false);
            // Telegram accepts sendMessage as the webhook response, avoiding a second HTTP request.
            return new WP_REST_Response([
                'method' => 'sendMessage', 'chat_id' => $chat, 'text' => $reply,
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            return new WP_Error('jrtg_unavailable', 'Временно недоступно.', ['status' => 503]);
        } finally {
            if ($owner) {
                try { JR_Lock::release('jrtg_subscriber_registry', $owner); } catch (Throwable $ignored) {}
            }
        }
    }
}
