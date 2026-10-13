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
$sticker_cycle = array('paw', 'fish', 'yarn', 'tail');
$jc = get_category_by_slug('journal');
$jurl = $jc ? get_category_link($jc) : (get_option('page_for_posts') ? get_permalink(get_option('page_for_posts')) : '');
$em = array('em' => array());
?>
<main id="main">

  <section class="pick" aria-labelledby="hero-title">
    <div class="wrap pick-head">
      <div>
        <span class="eyebrow" data-hero style="--i:0"><?php echo esc_html($c['hero']['eyebrow']); ?> · <?php echo esc_html($s['city']); ?></span>
        <h1 class="h1" id="hero-title" data-hero style="--i:1"><?php echo wp_kses($c['hero']['title'], $em); ?></h1>
      </div>
      <p class="lead" data-hero style="--i:2"><?php echo zh_t($c['hero']['lead']); ?></p>
    </div>
    <div class="pick-rail" data-hero style="--i:3">
      <div class="pick-panels">
        <?php foreach (array_slice($breeds, 0, 4) as $i => $b) :
          $m = zh_media($b); $n = (int) ($count_by_breed[$b->ID] ?? 0);
          $weight = ''; foreach (zh_breed_facts($b) as $f) { if (mb_stripos($f[0], 'Вес') === 0) { $weight = $f[1]; break; } }
          $small_w = ($m['w'] && $m['h']) ? (int) round($m['w'] * 520 / max($m['w'], $m['h'])) : 0; ?>
        <a class="pick-panel" href="<?php echo esc_url(get_permalink($b)); ?>" style="--bg:<?php echo esc_attr(zh_breed_color($b)); ?>">
          <span class="pick-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
          <span class="pick-name"><?php echo esc_html($b->post_title); ?></span>
          <span class="pick-meta"><?php echo esc_html(implode(' · ', array_filter(array($weight, $n ? $n . ' ' . zh_plural($n, 'кот', 'кота', 'котов') : '')))); ?></span>
          <?php if (!empty($c['hero']['notes'][$b->post_name])) : ?><span class="pick-note"><?php echo zh_hand_arrow(); ?><span><?php echo esc_html($c['hero']['notes'][$b->post_name]); ?></span></span><?php endif; ?>
          <?php if ($m['url']) : ?><img class="pick-cat" src="<?php echo esc_url($m['url']); ?>"<?php if ($m['small'] !== $m['url'] && $small_w) : ?> srcset="<?php echo esc_url($m['small']); ?> <?php echo $small_w; ?>w, <?php echo esc_url($m['url']); ?> <?php echo (int) $m['w']; ?>w" sizes="(min-width: 960px) 32vw, 64vw"<?php endif; ?> alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> decoding="async"<?php echo $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"'; ?> draggable="false"><?php endif; ?>
          <span class="pick-go" aria-hidden="true"><?php echo zh_arrow_up_right(); ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <span class="pick-hint hand" aria-hidden="true"><?php echo esc_html($c['hero']['hint']); ?> →</span>
    </div>
  </section>

  <div class="marquee-wrap" aria-hidden="true"><div class="marquee">
    <div class="marquee-track">
      <?php for ($g = 0; $g < 2; $g++) : ?>
        <div class="marquee-group">
          <?php foreach ($c['marquee'] as $k => $word) : ?>
            <span class="marquee-word"><?php echo esc_html($word); ?></span><?php echo zh_sticker($sticker_cycle[$k % 4], 'marquee-sticker'); ?>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div></div>

  <section class="sec" id="cats" aria-labelledby="cats-title">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="cats-title"><?php echo wp_kses($c['cats']['title'], $em); ?></h2>
        <p class="lead"><?php echo zh_t($c['cats']['lead']); ?></p>
      </div>
      <?php if ($cats) : ?>
        <?php zh_cat_filters($breeds, $cats); ?>
        <div class="cgrid" data-cgrid><?php zh_render_cat_cards($cats); ?></div>
        <p class="k-empty" data-filter-empty hidden><?php echo zh_t($c['cats']['empty']); ?> <button class="link-btn" type="button" data-enquire="Ближайшие помёты">Написать нам</button></p>
        <p class="more"><a class="btn btn-outline" href="<?php echo esc_url(home_url('/cats/')); ?>"><span>Все коты</span><?php echo zh_arrow(); ?></a></p>
      <?php else : echo zh_empty_cats($c['cats']['empty_title'], $c['cats']['empty']); endif; ?>
    </div>
  </section>

  <?php
  $bengal = null; foreach ($breeds as $bb) { if ($bb->post_name === 'bengal') { $bengal = $bb; } }
  $bengal_color = $bengal ? zh_breed_color($bengal) : '#6a2230';
  $scene_facts = $bengal ? array_slice(array_filter(zh_breed_facts($bengal), function ($f) { return $f[0] !== ''; }), 0, 4) : array();
  $scene_actions = '<a class="btn btn-light" href="' . esc_url($bengal ? get_permalink($bengal) : home_url('/breeds/')) . '"><span>' . esc_html($c['scene']['cta']) . '</span>' . zh_arrow() . '</a><button class="btn btn-line-light" type="button" data-enquire="Бенгальская"><span>' . esc_html($c['scene']['ask']) . '</span></button>';
  echo zh_scene('bottom', $bengal_color, $c['scene']['title'], $c['scene']['lead'], $scene_actions, array('hook' => $c['scene']['hook'], 'facts' => $scene_facts, 'notes' => $c['scene']['notes']));
  ?>

  <?php $compare = array(); foreach ($breeds as $b) { $sc = zh_breed_scores($b); if ($sc) { $compare[] = array($b, $sc); } } ?>
  <?php if (count($compare) > 1) : ?>
  <section class="sec compare" id="compare" aria-labelledby="compare-title" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="compare-title"><?php echo wp_kses($c['compare']['title'], $em); ?></h2>
        <p class="lead"><?php echo zh_t($c['compare']['lead']); ?></p>
      </div>
      <div class="chars">
        <?php foreach ($compare as $i => $row) : $b = $row[0]; $head = 'pets/' . $b->post_name . '-head.webp'; ?>
        <div class="char" data-reveal style="--bg:<?php echo esc_attr(zh_breed_color($b)); ?>;--d:<?php echo (int) $i; ?>">
          <?php if (zh_has_img($head)) : ?><img class="char-head" src="<?php echo esc_url(zh_img($head)); ?>" alt="" loading="lazy" decoding="async" draggable="false"><?php endif; ?>
          <div class="char-body">
            <h3><a href="<?php echo esc_url(get_permalink($b)); ?>"><?php echo esc_html($b->post_title); ?></a></h3>
            <dl>
              <?php foreach ($row[1] as $sc) : ?>
                <div><dt><?php echo esc_html($sc[0]); ?></dt><dd><span class="sr-only"><?php echo (int) $sc[1]; ?> из 5</span><span class="dots" aria-hidden="true"><?php for ($d = 1; $d <= 5; $d++) { echo '<i' . ($d <= $sc[1] ? ' class="on"' : '') . '></i>'; } ?></span></dd></div>
              <?php endforeach; ?>
            </dl>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="compare-note hand" aria-hidden="true"><?php echo zh_hand_arrow(); ?><span><?php echo esc_html($c['compare']['note']); ?></span></p>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec" id="process" aria-labelledby="process-title" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head sec-head--center" data-reveal><h2 class="h2" id="process-title"><?php echo wp_kses($c['process']['title'], $em); ?></h2></div>
      <div class="thread" data-thread>
        <span class="thread-line" aria-hidden="true"><span></span></span>
        <?php echo zh_sticker('yarn', 'thread-yarn'); ?>
        <ol class="steps">
          <?php foreach ($c['process']['steps'] as $i => $st) : ?>
            <li class="step" data-reveal>
              <span class="step-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
              <div class="step-body"><h3><?php echo esc_html($st[0]); ?></h3><p><?php echo zh_t($st[1]); ?></p></div>
              <span class="step-art" aria-hidden="true"><?php echo zh_sticker($st[2], 'step-sticker'); ?></span>
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
        <span class="eyebrow"><?php echo esc_html($c['delivery']['eyebrow']); ?></span>
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
        <?php if ($cities) : ?>
          <ul class="cities"><?php foreach ($cities as $city) { echo '<li><button type="button" data-city="' . esc_attr($city) . '">' . esc_html($city) . '</button></li>'; } ?></ul>
        <?php endif; ?>
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
        <?php if (zh_has_img('pets/peek-2.webp')) : ?><img class="faq-peek" src="<?php echo esc_url(zh_img('pets/peek-2.webp')); ?>" alt="" width="1100" height="733" loading="lazy" decoding="async" draggable="false"><?php endif; ?>
        <?php foreach ($c['faq']['items'] as $i => $q) : ?>
          <details class="faq-item" data-reveal><summary><span class="faq-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span><span class="faq-q"><?php echo zh_t($q[0]); ?></span></summary><p><?php echo zh_t($q[1]); ?></p></details>
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
            <span class="jrow-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
            <span class="jrow-t"><?php echo zh_t(get_the_title($p)); ?></span>
            <span class="jrow-d"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d', $p))); ?></span>
            <span class="jrow-go" aria-hidden="true"><?php echo zh_arrow_up_right(); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php $contacts = zh_contacts(); $fin = $breeds ? zh_media($breeds[min(1, count($breeds) - 1)]) : array('url' => '', 'type' => 'none', 'small' => ''); ?>
  <section class="finale" aria-labelledby="finale-title">
    <div class="wrap finale-in">
      <div class="finale-copy" data-reveal>
        <h2 class="h2" id="finale-title"><?php echo wp_kses($c['finale']['title'], $em); ?></h2>
        <p><?php echo zh_t($c['finale']['text']); ?></p>
        <div class="cta-actions">
          <button class="btn btn-light" type="button" data-enquire="Консультация"><span>Написать нам</span><?php echo zh_arrow(); ?></button>
          <?php foreach (array('whatsapp', 'telegram') as $k) : if (isset($contacts[$k])) : ?>
            <a class="btn btn-line-light" href="<?php echo esc_url($contacts[$k][1]); ?>" target="_blank" rel="noopener"><span><?php echo esc_html($contacts[$k][0]); ?></span></a>
          <?php endif; endforeach; ?>
        </div>
      </div>
      <?php if ($fin['url'] && $fin['type'] === 'cutout') : ?>
      <div class="finale-cat" data-particles data-src="<?php echo esc_url($fin['small']); ?>">
        <canvas aria-hidden="true"></canvas>
        <img src="<?php echo esc_url($fin['small']); ?>" alt="" loading="lazy" decoding="async" draggable="false">
      </div>
      <?php endif; ?>
    </div>
  </section>

</main>
<?php get_footer(); ?>
