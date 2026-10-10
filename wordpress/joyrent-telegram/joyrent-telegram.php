<?php
/**
 * Plugin Name: JOYRENT Telegram
 * Description: Private Telegram notifications for completed JOYRENT booking requests, with a durable queue and safe retries.
 * Version: 1.1.2
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, joyrent-rentals
 * Author: JOYRENT
 * License: GPL-2.0-or-later
 * Text Domain: joyrent-telegram
 */
if (!defined('ABSPATH')) exit;
define('JRTG_PLUGIN_FILE',__FILE__);

add_action('before_woocommerce_init',function():void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',JRTG_PLUGIN_FILE,true);
    }
});
add_action('plugins_loaded',function():void {
    $core_version=defined('JR_PLUGIN_FILE')?(get_file_data(JR_PLUGIN_FILE,['Version'=>'Version'])['Version']??''):'';
    if (!class_exists('WooCommerce')||!class_exists('JR_Lock')||!class_exists('JR_Games')||version_compare($core_version,'1.8.5','<')) {
        add_action('admin_notices',function():void {
            if (current_user_can('activate_plugins')) echo '<div class="notice notice-warning"><p>JOYRENT Telegram: активируйте WooCommerce и JOYRENT Rentals версии 1.8.5 или новее.</p></div>';
        });
        return;
    }
    foreach (['settings','api','message','subscriptions','dispatcher','notifications'] as $file) require_once __DIR__.'/includes/'.$file.'.php';
    JRTG_Settings::boot();
    JRTG_Subscriptions::boot();
    JRTG_Notifications::boot();
    JRTG_Dispatcher::boot();
},30);
register_deactivation_hook(__FILE__,function():void {
    delete_option('joyrent_telegram_dispatch');
    foreach (['joyrent_telegram_deliver','joyrent_telegram_recover','joyrent_telegram_broadcast','joyrent_telegram_broadcast_recover','joyrent_telegram_subscriber_test'] as $hook) {
        try {
            if (function_exists('as_unschedule_all_actions')) as_unschedule_all_actions($hook,null,'joyrent-telegram');
            wp_unschedule_hook($hook);
        } catch (Throwable $ignored) {}
    }
});
