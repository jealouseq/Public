<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$breeds = zh_breeds();
$kittens = zh_kittens();
$c = zh_content();
?>
<main id="main" class="inner">
  <header class="page-hero page-hero-compact" data-header="dark">
    <div class="wrap">
      <span class="eyebrow">ZHIBEK · Котята</span>
      <h1 class="display-l">Сейчас <em>в питомнике</em></h1>
      <p class="lead">Нажмите на котёнка, чтобы увидеть паспорт, родителей и написать нам. Заявка ничего не фиксирует и не требует оплаты.</p>
    </div>
  </header>
  <section class="collection collection-inner" data-header="light">
    <div class="wrap">
      <?php zh_kitten_filters($breeds, $kittens); ?>
      <div class="kgrid" data-kgrid><?php zh_render_kitten_cards($kittens); ?></div>
      <p class="kempty" <?php echo $kittens ? 'hidden' : ''; ?>><?php echo esc_html($c['collection']['empty']); ?> <button class="link-btn" type="button" data-enquire="Ближайшие помёты">Написать нам</button></p>
    </div>
  </section>
</main>
<?php get_footer(); ?>
