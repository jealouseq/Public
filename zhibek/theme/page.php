<?php
if (!defined('ABSPATH')) { exit; }
get_header();
// Корзина, оформление заказа и личный кабинет WooCommerce занимают всю ширину, обычный текст страницы — узкую колонку.
$wide = function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
?>
<main id="main">
  <?php while (have_posts()) : the_post(); ?>
    <header class="page-head page-head--narrow<?php echo $wide ? ' page-head--shop' : ''; ?>"><div class="wrap"><h1 class="h1"><?php the_title(); ?></h1></div></header>
    <?php if ($wide) : ?>
    <div class="wrap prose-wrap shop-wrap"><div class="prose prose--wide"><?php the_content(); ?></div></div>
    <?php else : ?>
    <div class="wrap prose-wrap prose-grid">
      <aside class="toc" data-toc hidden aria-label="Содержание"><span class="toc-h">На странице</span><ol class="toc-list"></ol></aside>
      <div class="prose"><?php the_content(); ?></div>
    </div>
    <?php endif; ?>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
