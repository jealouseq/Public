<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main">
  <?php while (have_posts()) : the_post(); ?>
    <header class="page-head page-head--narrow"><div class="wrap"><h1 class="h1"><?php the_title(); ?></h1></div></header>
    <div class="wrap prose-wrap"><div class="prose"><?php the_content(); ?></div></div>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
