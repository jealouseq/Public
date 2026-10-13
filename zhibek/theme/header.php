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
<header class="site-header" data-site-header>
  <div class="wrap header-in">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo zh_brand(); ?></a>
    <nav class="primary-nav" aria-label="Основная навигация"><?php zh_navigation('primary-menu'); ?></nav>
    <div class="header-actions">
      <button class="btn btn-primary btn-sm" type="button" data-enquire="Консультация"><span>Написать</span></button>
      <?php if (function_exists('wc_get_cart_url')) : $n = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?>
        <a class="cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="Корзина"><?php echo zh_icon('bag'); ?><span class="cart-count" data-n="<?php echo $n; ?>"><?php echo $n; ?></span></a>
      <?php endif; ?>
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu" aria-label="Открыть меню"><span></span><span></span></button>
    </div>
  </div>
</header>
<div class="menu" id="menu" hidden>
  <nav class="menu-nav" aria-label="Меню"><?php zh_navigation('menu-items'); ?></nav>
  <div class="menu-foot">
    <button class="btn btn-primary btn-block" type="button" data-enquire="Консультация"><span>Написать нам</span><?php echo zh_arrow(); ?></button>
    <?php foreach (zh_contacts() as $key => $c) : ?>
      <a href="<?php echo esc_url($c[1]); ?>" <?php echo in_array($key, array('whatsapp', 'telegram', 'instagram'), true) ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($c[0]); ?></a>
    <?php endforeach; ?>
  </div>
</div>
