<?php
if (!defined('ABSPATH')) { exit; }
get_header(); ?>
<main id="main">
  <?php while (have_posts()) : the_post(); ?>
    <header class="page-head page-head--narrow">
      <div class="wrap">
        <a class="back" href="<?php echo esc_url(home_url('/#journal')); ?>"><?php echo zh_arrow('arr-back'); ?><span>Журнал</span></a>
        <h1 class="h1"><?php the_title(); ?></h1>
        <p class="article-meta"><span><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d'))); ?></span><span><?php echo esc_html(max(1, (int) ceil(str_word_count(wp_strip_all_tags(get_the_content()), 0, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя') / 180))); ?> мин чтения</span></p>
      </div>
    </header>
    <article class="wrap prose-wrap"><div class="prose"><?php the_content(); ?></div></article>
    <?php $more = get_posts(array('post_type' => 'post', 'posts_per_page' => 2, 'post__not_in' => array(get_the_ID()), 'category_name' => 'journal')); if ($more) : ?>
    <section class="inner-sec"><div class="wrap">
      <div class="sec-head"><div><span class="eyebrow">Журнал</span><h2 class="h2">Читайте дальше</h2></div></div>
      <div class="posts"><?php foreach ($more as $a) : ?>
        <a class="post-card" href="<?php echo esc_url(get_permalink($a)); ?>"><div><span class="meta"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d', $a))); ?></span><h3><?php echo zh_t($a->post_title); ?></h3><p><?php echo zh_t(wp_trim_words(get_the_excerpt($a), 20)); ?></p></div><span class="go">Читать<?php echo zh_arrow(); ?></span></a>
      <?php endforeach; ?></div></div></section>
    <?php endif; ?>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
