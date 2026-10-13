<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main">
  <?php while (have_posts()) : the_post(); ?>
    <header class="page-head page-head--narrow article-head">
      <div class="wrap">
        <a class="back" href="<?php echo esc_url(zh_journal_url()); ?>"><?php echo zh_arrow('arr-back'); ?><span>Журнал</span></a>
        <h1 class="h1"><?php the_title(); ?></h1>
        <p class="article-meta"><span><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d'))); ?></span><span><?php echo (int) zh_read_minutes(); ?> мин чтения</span></p>
        <?php echo zh_sticker(zh_post_sticker(), 'article-sticker'); ?>
      </div>
    </header>
    <div class="wrap prose-wrap prose-grid">
      <aside class="toc" data-toc hidden aria-label="Содержание"><span class="toc-h">В статье</span><ol class="toc-list"></ol></aside>
      <article class="prose prose--article"><?php the_content(); ?></article>
    </div>
    <?php $more = get_posts(array('post_type' => 'post', 'posts_per_page' => 3, 'post__not_in' => array(get_the_ID()), 'category_name' => 'journal')); if ($more) : ?>
    <section class="inner-sec"><div class="wrap">
      <div class="sec-head"><div><span class="eyebrow">Журнал</span><h2 class="h2">Читайте <em>дальше</em></h2></div></div>
      <div class="jlist"><?php foreach ($more as $i => $a) : ?>
        <a class="jrow" href="<?php echo esc_url(get_permalink($a)); ?>">
          <span class="jrow-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
          <span class="jrow-t"><?php echo zh_t($a->post_title); ?></span>
          <span class="jrow-d"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d', $a))); ?></span>
          <span class="jrow-go" aria-hidden="true"><?php echo zh_arrow_up_right(); ?></span>
        </a>
      <?php endforeach; ?></div></div></section>
    <?php endif; ?>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
