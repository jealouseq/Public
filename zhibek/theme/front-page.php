<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$c = zh_content();
$s = zh_settings();
$breeds = zh_breeds();
$cats = zh_cats(array('numberposts' => 8));
$all_cats = zh_cats();
$cities = zh_map_city_list();
$posts = get_posts(array('numberposts' => 3, 'post_status' => 'publish', 'category_name' => 'journal', 'ignore_sticky_posts' => true));
if (!$posts) { $posts = get_posts(array('numberposts' => 3, 'post_status' => 'publish', 'ignore_sticky_posts' => true)); }
$count_by_breed = array();
foreach ($all_cats as $cat) { $bid = (int) get_post_meta($cat->ID, '_zhibek_breed', true); $count_by_breed[$bid] = ($count_by_breed[$bid] ?? 0) + 1; }
$jc = get_category_by_slug('journal');
$jurl = $jc ? get_category_link($jc) : (get_option('page_for_posts') ? get_permalink(get_option('page_for_posts')) : '');
$em = array('em' => array());
?>
<main id="main">

  <?php
  $hb = array_slice($breeds, 0, 3);
  $score_labels = array(); foreach ($breeds as $bb) { foreach (zh_breed_scores($bb) as $sc) { if (!in_array($sc[0], $score_labels, true)) { $score_labels[] = $sc[0]; } } }
  ?>
  <section class="hero" aria-labelledby="hero-title">
    <div class="wrap hero-in">
      <div class="hero-copy">
        <h1 class="h1" id="hero-title"><?php echo wp_kses($c['hero']['title'], $em); ?></h1>
        <p class="lead"><?php echo zh_t($c['hero']['lead']); ?></p>
        <?php if ($hb) : ?>
        <div class="hero-tabs" aria-label="Порода" data-hero-tabs>
          <?php foreach ($hb as $i => $b) : ?>
            <button class="hero-tab<?php echo $i === 0 ? ' is-on' : ''; ?>" type="button" aria-controls="hs-<?php echo esc_attr($b->post_name); ?>" aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>" data-i="<?php echo (int) $i; ?>" style="--bc:<?php echo esc_attr(zh_breed_color($b)); ?>">
              <i aria-hidden="true"></i><span class="hero-tab-n"><?php echo esc_html($b->post_title); ?></span><span class="hero-tab-t"><?php echo esc_html(implode(', ', array_slice(zh_traits($b), 0, 3))); ?></span>
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="hero-actions">
          <a class="btn btn-primary" href="#picker"><span><?php echo esc_html($c['hero']['pick']); ?></span><?php echo zh_arrow(); ?></a>
          <button class="btn btn-outline" type="button" data-enquire="Консультация"><span>Написать</span></button>
        </div>
      </div>
      <?php if ($hb) : ?>
      <div class="hero-stage" data-hero-stage style="--bg:<?php echo esc_attr(zh_breed_color($hb[0])); ?>">
        <?php foreach ($hb as $i => $b) :
          $m = zh_media($b); $n = (int) ($count_by_breed[$b->ID] ?? 0);
          $facts = array_values(array_filter(zh_breed_facts($b), function ($f) { return $f[0] !== '' && mb_stripos($f[0], 'Глаза') !== 0; })); $facts = array_slice($facts, 0, 3);
          $small_w = ($m['w'] && $m['h']) ? (int) round($m['w'] * 640 / max($m['w'], $m['h'])) : 0; ?>
        <a class="hs-slide<?php echo $i === 0 ? ' is-on' : ''; ?>" href="<?php echo esc_url(get_permalink($b)); ?>" id="hs-<?php echo esc_attr($b->post_name); ?>" data-color="<?php echo esc_attr(zh_breed_color($b)); ?>"<?php echo $i === 0 ? '' : ' inert'; ?>>
          <span class="hs-name"><?php echo esc_html($b->post_title); ?></span>
          <?php if ($m['url']) : ?><img class="hs-cat" src="<?php echo esc_url($m['url']); ?>"<?php if ($m['small'] !== $m['url'] && $small_w) : ?> srcset="<?php echo esc_url($m['small']); ?> <?php echo $small_w; ?>w, <?php echo esc_url($m['url']); ?> <?php echo (int) $m['w']; ?>w" sizes="(min-width: 960px) 40vw, 90vw"<?php endif; ?> alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> decoding="async" draggable="false"<?php echo $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"'; ?>><?php endif; ?>
          <dl class="hs-facts">
            <?php if ($n) : ?><div><dt>В питомнике</dt><dd><?php echo (int) $n . ' ' . esc_html(zh_plural($n, 'кот', 'кота', 'котов')); ?></dd></div><?php endif; ?>
            <?php foreach ($facts as $f) : ?><div><dt><?php echo esc_html($f[0]); ?></dt><dd><?php echo zh_t($f[1]); ?></dd></div><?php endforeach; ?>
          </dl>
          <span class="hs-go"><span><?php echo esc_html($c['hero']['open']); ?></span><?php echo zh_arrow_up_right(); ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($cats) : ?>
  <section class="sec" id="cats" aria-labelledby="cats-title">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="cats-title"><?php echo wp_kses($c['cats']['title'], $em); ?></h2>
        <p class="lead"><?php echo zh_t($c['cats']['lead']); ?></p>
      </div>
        <?php zh_cat_filters($breeds, $cats); ?>
        <div class="cgrid" data-cgrid><?php zh_render_cat_cards($cats); ?></div>
        <p class="k-empty" data-filter-empty hidden><?php echo zh_t($c['cats']['empty']); ?> <button class="link-btn" type="button" data-enquire="Ближайшие помёты">Написать нам</button></p>
        <p class="more"><a class="btn btn-outline" href="<?php echo esc_url(home_url('/cats/')); ?>"><span>Все коты</span><?php echo zh_arrow(); ?></a></p>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($score_labels && count($hb) > 1) :
    $pk = array();
    foreach ($hb as $b) {
      $sc = array(); foreach (zh_breed_scores($b) as $r) { $sc[$r[0]] = $r[1]; }
      $pk[] = array('name' => $b->post_title, 'url' => get_permalink($b), 'color' => zh_breed_color($b), 'scores' => $sc,
        'fit' => wp_strip_all_tags(zh_t((string) get_post_meta($b->ID, '_zhibek_fit', true))),
        'img' => array('lie' => zh_pose($b, 'lie'), 'sit' => zh_pose($b, 'sit'), 'stretch' => zh_pose($b, 'stretch')));
    } ?>
  <section class="sec" id="picker" aria-labelledby="picker-title">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="picker-title"><?php echo esc_html($c['picker']['title']); ?></h2>
        <p class="lead"><?php echo zh_t($c['picker']['lead']); ?></p>
      </div>
      <div class="picker" data-picker data-breeds="<?php echo esc_attr(wp_json_encode($pk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>" data-match="<?php echo esc_attr($c['picker']['match']); ?>" data-open="<?php echo esc_attr($c['picker']['open']); ?>">
        <div class="picker-rows">
          <?php foreach ($score_labels as $lab) : $h = $c['picker']['hints'][$lab] ?? array('меньше', 'больше'); ?>
          <div class="prow" role="radiogroup" aria-label="<?php echo esc_attr($lab); ?>" data-label="<?php echo esc_attr($lab); ?>">
            <span class="prow-l"><?php echo esc_html($lab); ?></span>
            <span class="prow-dots"><?php for ($v = 1; $v <= 5; $v++) : ?><button type="button" role="radio" aria-checked="false" aria-label="<?php echo esc_attr($lab . ': ' . $v . ' из 5'); ?>" data-v="<?php echo (int) $v; ?>"><i></i></button><?php endfor; ?></span>
            <span class="prow-h"><span><?php echo esc_html($h[0]); ?></span><span><?php echo esc_html($h[1]); ?></span></span>
          </div>
          <?php endforeach; ?>
          <button class="link-btn picker-reset" type="button" hidden><?php echo esc_html($c['picker']['reset']); ?></button>
        </div>
        <div class="picker-result" aria-live="polite">
          <div class="pr-empty">
            <span class="pr-trio" aria-hidden="true"><?php foreach ($pk as $row) { echo '<span class="pr-pick" style="--bc:' . esc_attr($row['color']) . '"><span class="pr-arch"><img src="' . esc_url($row['img']['sit']) . '" alt="" loading="lazy" decoding="async" draggable="false"></span><b>' . esc_html($row['name']) . '</b></span>'; } ?></span>
            <p><?php echo esc_html($c['picker']['empty']); ?></p>
          </div>
          <a class="pr-card" href="#" hidden>
            <span class="pr-name"></span>
            <span class="pr-fit"></span>
            <img class="pr-cat" src="" alt="" decoding="async" draggable="false">
            <span class="hs-go"><span><?php echo esc_html($c['picker']['open']); ?></span><?php echo zh_arrow_up_right(); ?></span>
          </a>
          <ul class="pr-bars" hidden></ul>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec" id="process" aria-labelledby="process-title" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head" data-reveal><h2 class="h2" id="process-title"><?php echo esc_html($c['process']['title']); ?></h2></div>
      <?php $bybreed = array(); foreach ($breeds as $bb) { $bybreed[$bb->post_name] = $bb; } ?>
      <div class="proc" data-proc>
        <div class="proc-stage" aria-hidden="true" style="--bg:#2a46a0">
          <?php foreach ($c['process']['steps'] as $i => $st) : $pb = $bybreed[$st[2]] ?? null; $pimg = $pb ? zh_pose($pb, $st[3]) : ''; ?>
            <span class="ps-slide<?php echo $i === 0 ? ' is-on' : ''; ?>" data-color="<?php echo esc_attr($pb ? zh_breed_color($pb) : '#2c4a3b'); ?>"><?php if ($pimg) : ?><img src="<?php echo esc_url($pimg); ?>" alt="" decoding="async" draggable="false"<?php echo $i === 0 ? '' : ' loading="lazy"'; ?>><?php endif; ?></span>
          <?php endforeach; ?>
        </div>
        <ol class="proc-list">
          <?php foreach ($c['process']['steps'] as $i => $st) : $pb = $bybreed[$st[2]] ?? null; $pimg = $pb ? zh_pose($pb, $st[3]) : ''; ?>
            <li class="pstep<?php echo $i === 0 ? ' is-on' : ''; ?>" data-i="<?php echo (int) $i; ?>" style="--c:<?php echo esc_attr($pb ? zh_breed_color($pb) : '#2c4a3b'); ?>">
              <span class="pstep-n"><?php echo (int) ($i + 1); ?></span>
              <h3><?php echo esc_html($st[0]); ?></h3>
              <p><?php echo zh_t($st[1]); ?></p>
              <?php if ($pimg) : ?><span class="pstep-art" aria-hidden="true"><img src="<?php echo esc_url($pimg); ?>" alt="" loading="lazy" decoding="async" draggable="false"></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
      <p class="more" data-reveal><button class="btn btn-primary" type="button" data-enquire="Консультация"><span>Написать нам</span><?php echo zh_arrow(); ?></button></p>
    </div>
  </section>

  <section class="delivery" id="delivery" aria-labelledby="delivery-title">
    <div class="wrap delivery-in">
      <div class="delivery-copy" data-reveal>
        
        <h2 class="h2" id="delivery-title"><?php echo wp_kses($c['delivery']['title'], $em); ?></h2>
        <p class="delivery-lead"><?php echo zh_t($c['delivery']['text']); ?></p>
        <ul class="dfacts">
          <?php foreach ($c['delivery']['facts'] as $f) : ?>
            <li><span class="dfact-ico"><?php echo zh_icon($f[0]); ?></span><div><b><?php echo zh_t($f[1]); ?></b><span><?php echo zh_t($f[2]); ?></span></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="delivery-map" data-reveal data-map>
        <?php echo zh_map_svg(); ?>
        <ul class="cities"><?php foreach ($cities as $city) { echo '<li><button type="button" data-city="' . esc_attr($city) . '">' . esc_html($city) . '</button></li>'; } ?><li><button class="cities-other" type="button" data-enquire="Другой город"><?php echo esc_html($c['delivery']['other']); ?></button></li></ul>
      </div>
    </div>
  </section>


  <section class="sec" id="faq" aria-labelledby="faq-title">
    <div class="wrap faq-grid">
      <div class="faq-head" data-reveal>
        <h2 class="h2" id="faq-title"><?php echo wp_kses($c['faq']['title'], $em); ?></h2>
        <p class="faq-help"><?php echo zh_t($c['faq']['help']); ?></p>
        <button class="btn btn-primary" type="button" data-enquire="Вопрос"><span><?php echo esc_html($c['faq']['cta']); ?></span><?php echo zh_arrow(); ?></button>
      </div>
      <div class="faq-list">
        <?php foreach ($c['faq']['items'] as $i => $q) : ?>
          <details class="faq-item" data-reveal><summary><span class="faq-q"><?php echo zh_t($q[0]); ?></span></summary><p><?php echo zh_t($q[1]); ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($posts) : ?>
  <section class="sec" id="journal" aria-labelledby="journal-title" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="journal-title"><?php echo zh_t($c['journal']['title']); ?></h2>
        <?php if ($jurl) : ?><a class="link-arrow" href="<?php echo esc_url($jurl); ?>"><span>Все статьи</span><?php echo zh_arrow(); ?></a><?php endif; ?>
      </div>
      <div class="jlist">
        <?php foreach ($posts as $i => $p) : ?>
          <a class="jrow" href="<?php echo esc_url(get_permalink($p)); ?>" data-reveal>
            <span class="jrow-t"><?php echo zh_t(get_the_title($p)); ?></span>
            <span class="jrow-d"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d', $p))); ?></span>
            <span class="jrow-go" aria-hidden="true"><?php echo zh_arrow_up_right(); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

</main>
<?php get_footer(); ?>
