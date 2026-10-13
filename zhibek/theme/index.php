<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main" class="inner">
  <header class="page-hero page-hero-compact" data-header="dark"><div class="wrap"><span class="eyebrow">ZHIBEK · Журнал</span><h1 class="display-l"><?php echo is_search() ? 'Поиск' : (is_archive() ? esc_html(get_the_archive_title()) : 'Журнал'); ?></h1></div></header>
  <section class="journal" data-header="light"><div class="wrap">
    <?php if (have_posts()) : ?>
      <div class="journal-grid journal-grid-list"><?php $i = 0; while (have_posts()) : the_post(); $i++; ?>
        <a class="jcard" href="<?php the_permalink(); ?>" data-cursor="Читать"><span class="jcard-n"><?php echo esc_html(sprintf('%02d', $i)); ?></span><h3><?php the_title(); ?></h3><p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 28)); ?></p><span class="link-arrow"><span>Читать</span><?php echo zh_arrow(); ?></span></a>
      <?php endwhile; ?></div>
      <?php the_posts_pagination(array('prev_text' => '←', 'next_text' => '→')); ?>
    <?php else : ?><p class="kempty">Здесь пока ничего нет.</p><?php endif; ?>
  </div></section>
</main>
<?php get_footer(); ?>
