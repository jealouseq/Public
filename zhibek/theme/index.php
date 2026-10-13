<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main">
  <header class="page-head"><div class="wrap"><span class="eyebrow">ZHIBEK</span><h1 class="h1"><?php echo is_search() ? 'Поиск' : (is_archive() ? esc_html(get_the_archive_title()) : 'Журнал'); ?></h1></div></header>
  <section class="inner-sec"><div class="wrap">
    <?php if (have_posts()) : ?>
      <div class="posts"><?php while (have_posts()) : the_post(); ?>
        <a class="post-card" href="<?php the_permalink(); ?>"><div><span class="meta"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d'))); ?></span><h3><?php the_title(); ?></h3><p><?php echo zh_t(wp_trim_words(get_the_excerpt(), 24)); ?></p></div><span class="go">Читать<?php echo zh_arrow(); ?></span></a>
      <?php endwhile; ?></div>
      <?php the_posts_pagination(array('prev_text' => '←', 'next_text' => '→')); ?>
    <?php else : ?><p class="k-empty">Здесь пока ничего нет.</p><?php endif; ?>
  </div></section>
</main>
<?php get_footer(); ?>
