<?php if (!defined('ABSPATH')) { exit; } ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a href="#main" class="skip-link">К содержимому</a>
<div class="grain" aria-hidden="true"></div>
<div class="cursor" aria-hidden="true"><span class="cursor-dot"></span><span class="cursor-ring"><span class="cursor-label"></span></span></div>
<div class="scroll-progress" aria-hidden="true"><span></span></div>
<header class="site-header" data-site-header>
  <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="ZHIBEK — на главную"><?php echo zh_brand(); ?></a>
  <nav class="primary-nav" aria-label="Основная навигация"><?php zh_navigation('primary-menu'); ?></nav>
  <div class="header-actions">
    <button class="hdr-contact" type="button" data-enquire="Консультация" data-magnetic><span>Написать</span></button>
    <?php if (function_exists('wc_get_cart_url')) : ?>
      <a class="cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="Корзина"><?php echo zh_icon('bag'); ?><span class="cart-count"><?php echo WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?></span></a>
    <?php endif; ?>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu" aria-label="Открыть меню"><span></span><span></span></button>
  </div>
</header>
<div class="menu" id="menu" aria-hidden="true" hidden>
  <div class="menu-bg" aria-hidden="true"></div>
  <nav class="menu-nav" aria-label="Меню"><?php zh_navigation('menu-items'); ?></nav>
  <div class="menu-foot">
    <?php foreach (zh_contacts() as $key => $c) : ?>
      <a href="<?php echo esc_url($c[1]); ?>" <?php echo in_array($key, array('whatsapp', 'telegram', 'instagram'), true) ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($c[0]); ?></a>
    <?php endforeach; ?>
    <span class="menu-city"><?php echo esc_html(zh_settings()['city']); ?></span>
  </div>
</div>
<div class="silk" aria-hidden="true"><canvas></canvas></div>
