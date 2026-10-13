<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$breeds = zh_breeds();
$cats = zh_cats();
$c = zh_content();
?>
<main id="main">
  <header class="page-head">
    <div class="wrap">
      <h1 class="h1">Наши коты</h1>
      <p class="lead"><?php echo zh_t($c['cats']['lead']); ?> Заявка ничего не фиксирует и не требует оплаты.</p>
    </div>
  </header>
  <section class="inner-sec">
    <div class="wrap">
      <?php if ($cats) : zh_cat_filters($breeds, $cats); ?>
        <div class="cgrid" data-cgrid><?php zh_render_cat_cards($cats); ?></div>
        <p class="k-empty" data-filter-empty hidden><?php echo zh_t($c['cats']['empty']); ?> <button class="link-btn" type="button" data-enquire="Ближайшие помёты">Написать нам</button></p>
      <?php else : echo zh_empty_cats($c['cats']['empty_title'], $c['cats']['empty']); endif; ?>
    </div>
  </section>
</main>
<?php get_footer(); ?>
