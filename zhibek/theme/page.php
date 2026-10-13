<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main" class="inner">
  <?php while (have_posts()) : the_post(); ?>
    <header class="page-hero page-hero-compact" data-header="dark"><div class="wrap"><span class="eyebrow">ZHIBEK</span><h1 class="display-l"><?php the_title(); ?></h1></div></header>
    <section class="prose-wrap" data-header="light"><div class="wrap"><div class="prose"><?php the_content(); ?></div></div></section>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
