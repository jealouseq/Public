<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php if (!is_front_page()): ?>
<header class="site-header"><div class="shell header-inner"><a class="wordmark" href="<?php echo esc_url(home_url('/')); ?>">JOYRENT<span class="brand-dot">.</span></a><a class="button button-outline" href="<?php echo esc_url(home_url('/#booking')); ?>">Обрати консоль і дати</a></div></header>
<?php endif; ?>
