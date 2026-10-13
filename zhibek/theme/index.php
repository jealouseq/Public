<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$is_journal = is_home() || is_category('journal');
$title = $is_journal ? 'Журнал' : zh_archive_title();
$posts = $GLOBALS['wp_query']->posts;
$first = (!is_paged() && $posts) ? array_shift($posts) : null;
$side = array_splice($posts, 0, $first ? 2 : 0);
?>
<main id="main">
  <header class="page-head page-head--narrow">
    <div class="wrap">
      <h1 class="h1"><?php echo esc_html($title); ?></h1>
      <?php if ($is_journal) : ?><p class="lead">Коротко о породах, уходе и первых днях кошки в новом доме.</p><?php endif; ?>
    </div>
  </header>
  <section class="inner-sec"><div class="wrap">
    <?php if ($first) : ?>
      <div class="jtop">
        <a class="jfeat" href="<?php echo esc_url(get_permalink($first)); ?>">
          <span class="jfeat-meta"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d', $first))); ?> · <?php echo (int) zh_read_minutes($first); ?> мин</span>
          <h2 class="jfeat-t"><?php echo zh_t(get_the_title($first)); ?></h2>
          <p class="jfeat-p"><?php echo zh_t(wp_trim_words(get_the_excerpt($first), 22)); ?></p>
          <span class="jfeat-go">Читать <?php echo zh_arrow(); ?></span>
        </a>
        <?php if ($side) : ?>
        <div class="jside">
          <?php foreach ($side as $i => $p) : ?>
            <a class="jcard" href="<?php echo esc_url(get_permalink($p)); ?>">
              <span class="jcard-t"><?php echo zh_t(get_the_title($p)); ?></span>
              <span class="jcard-d"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d', $p))); ?> · <?php echo (int) zh_read_minutes($p); ?> мин</span>
              <span class="jcard-go" aria-hidden="true"><?php echo zh_arrow_up_right(); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($posts) : ?>
      <div class="jlist"><?php foreach ($posts as $i => $p) : ?>
        <a class="jrow" href="<?php echo esc_url(get_permalink($p)); ?>">
          <span class="jrow-t"><?php echo zh_t(get_the_title($p)); ?></span>
          <span class="jrow-d"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d', $p))); ?></span>
          <span class="jrow-go" aria-hidden="true"><?php echo zh_arrow_up_right(); ?></span>
        </a>
      <?php endforeach; ?></div>
    <?php endif; ?>
    <?php if (!$first && !$posts) : ?><p class="k-empty">Здесь пока ничего нет.</p><?php endif; ?>
    <?php the_posts_pagination(array('prev_text' => '←', 'next_text' => '→')); ?>
  </div></section>
</main>
<?php get_footer(); ?>
