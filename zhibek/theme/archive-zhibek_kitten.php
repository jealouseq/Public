<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$breeds = zh_breeds();
$kittens = zh_kittens();
$c = zh_content();
?>
<main id="main">
  <header class="page-head">
    <div class="wrap">
      <span class="eyebrow">Питомник</span>
      <h1 class="h1">Котята</h1>
      <p class="lead"><?php echo zh_t($c['kittens']['lead']); ?> Заявка ничего не фиксирует и не требует оплаты.</p>
    </div>
  </header>
  <section class="inner-sec">
    <div class="wrap">
      <?php if ($kittens) { zh_kitten_filters($breeds, $kittens); } ?>
      <div class="kgrid" data-kgrid><?php zh_render_kitten_cards($kittens); ?></div>
      <p class="k-empty" data-filter-empty <?php echo $kittens ? 'hidden' : ''; ?>><?php echo zh_t($c['kittens']['empty']); ?> <button class="link-btn" type="button" data-enquire="Ближайшие помёты">Написать нам</button></p>
    </div>
  </section>
</main>
<?php get_footer(); ?>
