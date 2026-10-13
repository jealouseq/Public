<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main">
  <?php while (have_posts()) : the_post(); ?>
    <header class="page-head page-head--narrow"><div class="wrap"><h1 class="h1"><?php the_title(); ?></h1></div></header>
    <div class="wrap prose-wrap prose-grid">
      <aside class="toc" data-toc hidden aria-label="Содержание"><span class="toc-h">На странице</span><ol class="toc-list"></ol></aside>
      <div class="prose"><?php the_content(); ?></div>
    </div>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
