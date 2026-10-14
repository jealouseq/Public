<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main">
  <div class="wrap nf">
    <span class="nf-code" aria-hidden="true">4<span><?php if (zh_has_img('pets/bengal-head.webp')) : ?><img class="nf-face" src="<?php echo esc_url(zh_img('pets/bengal-head.webp')); ?>" alt="" width="392" height="359" draggable="false"><?php endif; ?></span>4</span>
    <h1 class="h2">Такой страницы нет</h1>
    <p class="lead muted">Вернитесь на главную или посмотрите, какие коты есть сейчас.</p>
    <div class="hero-cta"><a class="btn btn-primary" href="<?php echo esc_url(home_url('/')); ?>"><span>На главную</span><?php echo zh_arrow(); ?></a><a class="btn btn-outline" href="<?php echo esc_url(home_url('/cats/')); ?>"><span>Коты</span></a></div>
  </div>
</main>
<?php get_footer(); ?>
