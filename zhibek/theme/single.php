<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main" class="inner">
  <?php while (have_posts()) : the_post(); ?>
    <header class="page-hero page-hero-compact article-hero" data-header="dark">
      <div class="wrap">
        <a class="back" href="<?php echo esc_url(home_url('/#journal')); ?>"><?php echo zh_arrow('arr-back'); ?><span>Журнал</span></a>
        <h1 class="display-l"><?php the_title(); ?></h1>
        <p class="article-meta"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d'))); ?> · <?php echo esc_html(max(1, (int) ceil(str_word_count(wp_strip_all_tags(get_the_content()), 0, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя') / 180))); ?> мин чтения</p>
      </div>
    </header>
    <article class="prose-wrap" data-header="light"><div class="wrap"><div class="prose prose-article"><?php the_content(); ?></div></div></article>
    <?php $more = get_posts(array('post_type' => 'post', 'posts_per_page' => 2, 'post__not_in' => array(get_the_ID()), 'category_name' => 'journal')); if ($more) : ?>
    <section class="journal journal-more" data-header="light"><div class="wrap"><div class="sec-head"><div><span class="eyebrow">Ещё в журнале</span></div></div>
      <div class="journal-grid journal-grid-2"><?php foreach ($more as $i => $a) : ?>
        <a class="jcard" href="<?php echo esc_url(get_permalink($a)); ?>" data-cursor="Читать"><span class="jcard-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span><h3><?php echo esc_html($a->post_title); ?></h3><p><?php echo esc_html(wp_trim_words(get_the_excerpt($a), 20)); ?></p><span class="link-arrow"><span>Читать</span><?php echo zh_arrow(); ?></span></a>
      <?php endforeach; ?></div></div></section>
    <?php endif; ?>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
