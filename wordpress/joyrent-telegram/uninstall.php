<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('joyrent_telegram_settings');
delete_option('joyrent_telegram_subscribers');
delete_option('joyrent_telegram_dispatch');
delete_option('joyrent_telegram_test_status');
delete_transient('joyrent_telegram_chats');
foreach (['joyrent_telegram_deliver','joyrent_telegram_recover','joyrent_telegram_broadcast','joyrent_telegram_broadcast_recover','joyrent_telegram_subscriber_test'] as $hook) {
    if (function_exists('as_unschedule_all_actions')) as_unschedule_all_actions($hook,null,'joyrent-telegram');
    wp_unschedule_hook($hook);
}
// Order metadata keeps only delivery state and an acknowledgment ID, never bot credentials.
