<?php
/**
 * Описание и отзывы товара подряд, без вкладок: без скрипта, с корректной разметкой для скринридеров.
 * Заменяет шаблон WooCommerce single-product/tabs/tabs.php.
 */
if (!defined('ABSPATH')) { exit; }

$product_tabs = apply_filters('woocommerce_product_tabs', array());
if (!empty($product_tabs)) : ?>
<div class="woocommerce-tabs zh-tabs">
    <?php foreach ($product_tabs as $key => $product_tab) : ?>
        <section class="zh-tab zh-tab--<?php echo esc_attr($key); ?>" id="tab-<?php echo esc_attr($key); ?>">
            <?php if (isset($product_tab['callback'])) { call_user_func($product_tab['callback'], $key, $product_tab); } ?>
        </section>
    <?php endforeach; ?>
    <?php do_action('woocommerce_product_after_tabs'); ?>
</div>
<?php endif; ?>
