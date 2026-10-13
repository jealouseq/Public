<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$breeds = zh_breeds();
$c = zh_content();
$em = array('em' => array());
$all_cats = zh_cats(); $count = array();
foreach ($all_cats as $cat) { $bid = (int) get_post_meta($cat->ID, '_zhibek_breed', true); $count[$bid] = ($count[$bid] ?? 0) + 1; }
?>
<main id="main" class="page-breeds">
  <header class="page-head">
    <div class="wrap">
      <span class="eyebrow">Питомник</span>
      <h1 class="h1">Выберите <em>породу</em></h1>
      <p class="lead"><?php echo zh_t($c['hero']['lead']); ?></p>
    </div>
  </header>
  <div class="bands">
    <?php foreach ($breeds as $i => $b) : $m = zh_media($b); $n = (int) ($count[$b->ID] ?? 0); $facts = array_slice(zh_breed_facts($b), 0, 3); ?>
    <a class="band" href="<?php echo esc_url(get_permalink($b)); ?>" style="--bg:<?php echo esc_attr(zh_breed_color($b)); ?>">
      <span class="band-in wrap">
        <span class="band-copy">
          <span class="band-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
          <span class="band-name"><?php echo esc_html($b->post_title); ?></span>
          <span class="band-sub"><?php echo zh_t(get_post_meta($b->ID, '_zhibek_subtitle', true)); ?></span>
          <?php if ($facts) : ?><span class="band-facts"><?php foreach ($facts as $f) { echo '<span><b>' . esc_html($f[0]) . '</b> ' . esc_html($f[1]) . '</span>'; } ?></span><?php endif; ?>
          <span class="band-go"><span><?php echo $n ? $n . ' ' . zh_plural($n, 'кот', 'кота', 'котов') : 'О породе'; ?></span><?php echo zh_arrow(); ?></span>
        </span>
        <?php if ($m['url']) : ?><img class="band-cat" src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> loading="<?php echo $i ? 'lazy' : 'eager'; ?>" decoding="async" draggable="false"><?php endif; ?>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
</main>
<?php get_footer(); ?>
