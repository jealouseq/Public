<?php
/**
 * Plugin Name: JOYRENT Rentals
 * Description: Ukrainian PlayStation rental catalog, guest requests and WooCommerce order integration.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Author: JOYRENT
 * License: GPL-2.0-or-later
 * Text Domain: joyrent-rentals
 */
if (!defined('ABSPATH')) exit;
define('JR_PLUGIN_FILE', __FILE__);
foreach (['domain','settings','games','store','orders','rest'] as $file) require_once __DIR__.'/includes/'.$file.'.php';

add_action('init', ['JR_Games','register']);
add_action('init', ['JR_Orders','register_status']);
add_action('rest_api_init', ['JR_REST','register']);
add_action('admin_menu', ['JR_Settings','menu']);
add_action('admin_init', ['JR_Settings','register']);
add_action('admin_post_joyrent_seed', ['JR_Settings','seed']);
add_action('add_meta_boxes', ['JR_Games','meta_boxes']);
add_action('save_post_joyrent_game', ['JR_Games','save'], 10, 2);
add_filter('wc_order_statuses', ['JR_Orders','statuses']);
add_filter('option_page_capability_joyrent', fn() => 'manage_woocommerce');
// Support durable-key queries in the legacy order store as well as HPOS.
add_filter('woocommerce_order_data_store_cpt_get_orders_query', function (array $query, array $vars): array {
    if (!empty($vars['joyrent_request_key'])) $query['meta_query'][]=['key'=>'_joyrent_request_key','value'=>$vars['joyrent_request_key']];
    return $query;
}, 10, 2);
// Rental products enter orders only through the validated request route.
add_filter('woocommerce_is_purchasable', function (bool $purchasable, WC_Product $product): bool {
    return $product->get_meta('_joyrent_console') ? false : $purchasable;
}, 10, 2);
add_filter('woocommerce_add_to_cart_validation', function (bool $valid, int $product_id): bool {
    $product = wc_get_product($product_id);
    if ($product && $product->get_meta('_joyrent_console')) {
        wc_add_notice('Для оренди обери консоль і дати у формі JOYRENT.', 'error');
        return false;
    }
    return $valid;
}, 10, 2);
register_activation_hook(__FILE__, function (): void { JR_Games::register(); JR_Orders::register_status(); if (class_exists('WooCommerce')) JR_Store::seed(); flush_rewrite_rules(); });
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
add_action('before_woocommerce_init', function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', JR_PLUGIN_FILE, true);
});
