<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main">
  <div class="wrap nf">
    <span class="nf-code" aria-hidden="true">4<span><?php echo zh_sticker('face', 'nf-face'); ?></span>4</span>
    <h1 class="h2">Такой страницы <em>нет</em></h1>
    <p class="lead muted">Вернитесь на главную или посмотрите, какие коты есть сейчас.</p>
    <div class="hero-cta"><a class="btn btn-primary" href="<?php echo esc_url(home_url('/')); ?>"><span>На главную</span><?php echo zh_arrow(); ?></a><a class="btn btn-outline" href="<?php echo esc_url(home_url('/cats/')); ?>"><span>Коты</span></a></div>
  </div>
</main>
<?php get_footer(); ?>
