<?php get_header(); ?>
<main class="shell wp-page" style="padding-block:70px;min-height:65vh">
<?php while (have_posts()): the_post(); ?>
<article <?php post_class(); ?>><h1 style="font-family:var(--service);font-size:clamp(24px,4vw,48px);font-weight:550;line-height:1.15;letter-spacing:-.025em;overflow-wrap:anywhere;margin-bottom:35px"><?php the_title(); ?></h1><div class="wp-content" style="overflow-wrap:anywhere"><?php the_content(); ?></div></article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
