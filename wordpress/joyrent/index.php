<?php get_header(); ?>
<main class="shell wp-page" style="padding-block:70px;min-height:65vh">
<?php while (have_posts()): the_post(); ?>
<article <?php post_class(); ?>><h1 style="font-family:var(--display);font-size:clamp(28px,4vw,48px);margin-bottom:35px"><?php the_title(); ?></h1><div class="wp-content"><?php the_content(); ?></div></article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
