<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main" class="inner">
  <section class="notfound" data-header="dark">
    <div class="wrap">
      <span class="eyebrow">Ошибка 404</span>
      <h1 class="display-xl">Здесь <em>пусто</em></h1>
      <p class="lead">Такой страницы нет. Вернитесь на главную или посмотрите, какие котята живут в питомнике сейчас.</p>
      <div class="notfound-actions"><a class="btn btn-gold" href="<?php echo esc_url(home_url('/')); ?>" data-magnetic><span>На главную</span><?php echo zh_arrow(); ?></a><a class="btn btn-ghost" href="<?php echo esc_url(home_url('/kittens/')); ?>" data-magnetic><span>Котята</span></a></div>
    </div>
  </section>
</main>
<?php get_footer(); ?>
