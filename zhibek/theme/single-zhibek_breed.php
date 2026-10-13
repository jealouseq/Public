<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post();
  $b = get_post();
  if (post_password_required()) { ?>
    <main id="main"><header class="page-head"><div class="wrap"><h1 class="h1"><?php the_title(); ?></h1></div></header><div class="wrap prose-wrap"><div class="prose"><?php the_content(); ?></div></div></main>
    <?php continue;
  }
  $color = zh_breed_color($b);
  $m = zh_media($b);
  $facts = zh_breed_facts($b);
  $scores = zh_breed_scores($b);
  $traits = zh_traits($b);
  $fit = get_post_meta($b->ID, '_zhibek_fit', true); $note = get_post_meta($b->ID, '_zhibek_note', true);
  $cats = zh_cats(array('meta_key' => '_zhibek_breed', 'meta_value' => $b->ID));
  $points = zh_callout_points($b->post_name);
  $others = array_values(array_filter(zh_breeds(), function ($x) use ($b) { return $x->ID !== $b->ID; }));
  $all = zh_breeds(); $idx = 0; foreach ($all as $k => $x) { if ($x->ID === $b->ID) { $idx = $k; } }
  $slots = array('Глаз' => 'eyes', 'Шерсть' => 'coat', 'Вес' => 'weight', 'Окрас' => 'color', 'Рисунок' => 'color');
  $callouts = array(); $side = 0;
  if ($points && $m['type'] === 'cutout') {
    foreach ($facts as $f) {
      foreach ($slots as $label => $slot) {
        if (mb_stripos($f[0], $label) === 0 && isset($points[$slot]) && !isset($callouts[$slot])) { $callouts[$slot] = array($f[0], $f[1], $points[$slot], 'r'); break; }
      }
    }
  }
  ?>
<main id="main">
  <section class="bhero" style="--bg:<?php echo esc_attr($color); ?>">
    <div class="wrap bhero-in">
      <div class="bhero-top"><a class="back" href="<?php echo esc_url(home_url('/breeds/')); ?>"><?php echo zh_arrow('arr-back'); ?><span>Все породы</span></a><span class="bhero-n"><?php echo esc_html(sprintf('%02d', $idx + 1)); ?> / <?php echo esc_html(sprintf('%02d', count($all))); ?></span></div>
      <h1 class="bhero-name"><?php the_title(); ?></h1>
      <div class="bhero-copy">
        <p class="bhero-sub"><?php echo zh_t(get_post_meta($b->ID, '_zhibek_subtitle', true)); ?></p>
        <?php if ($facts) : ?><dl class="bhero-facts"><?php foreach ($facts as $f) : ?><div><dt><?php echo esc_html($f[0]); ?></dt><dd><?php echo zh_t($f[1]); ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
        <div class="bhero-cta">
          <a class="btn btn-light" href="#cats"><span>Коты породы</span><?php echo zh_arrow(); ?></a>
          <button class="btn btn-line-light" type="button" data-enquire="<?php echo esc_attr($b->post_title); ?>"><span>Задать вопрос</span></button>
        </div>
      </div>
      <figure class="bhero-stage">
        <?php if ($m['url']) : ?><img class="bhero-cat" src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> fetchpriority="high" decoding="async" draggable="false"><?php endif; ?>
        <?php foreach ($callouts as $slot => $co) : ?>
          <span class="callout callout--<?php echo esc_attr($co[3]); ?>" style="top:<?php echo (int) $co[2][1]; ?>%"><?php echo zh_hand_arrow(); ?><span><b><?php echo esc_html($co[0]); ?></b> <?php echo esc_html(mb_strimwidth($co[1], 0, 34, '…')); ?></span></span>
        <?php endforeach; ?>
      </figure>
    </div>
  </section>

  <?php if ($scores) : ?>
  <section class="bscores" style="--bg:<?php echo esc_attr($color); ?>" aria-label="Характер породы">
    <div class="wrap bscores-in">
      <?php foreach ($scores as $sc) : ?>
        <div class="bscore"><span><?php echo esc_html($sc[0]); ?></span><i><span class="sr-only"><?php echo (int) $sc[1]; ?> из 5</span><span class="dots" aria-hidden="true"><?php for ($d = 1; $d <= 5; $d++) { echo '<b' . ($d <= $sc[1] ? ' class="on"' : '') . '></b>'; } ?></span></i></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="bbody">
    <div class="wrap bbody-in">
      <div class="bquote" data-reveal>
        <?php if ($fit) : ?><span class="hand">подойдёт, если</span><p><?php echo zh_t($fit); ?></p><?php endif; ?>
        <?php if ($note) : ?><span class="hand">важно знать</span><p class="bquote-note"><?php echo zh_t($note); ?></p><?php endif; ?>
        <?php if ($traits) : ?><ul class="traits"><?php foreach ($traits as $t) { echo '<li>' . esc_html($t) . '</li>'; } ?></ul><?php endif; ?>
      </div>
      <div class="prose" data-reveal><?php the_content(); ?></div>
    </div>
  </section>

  <?php if ($b->post_name === 'bengal') {
    $sc = zh_content()['breed_scene'];
    $sc_actions = '<a class="btn btn-light" href="#cats"><span>Коты породы</span>' . zh_arrow() . '</a><button class="btn btn-line-light" type="button" data-enquire="' . esc_attr($b->post_title) . '"><span>Задать вопрос</span></button>';
    echo zh_scene('right', $color, $sc['title'], $sc['lead'], $sc_actions, array('hook' => $sc['hook'], 'facts' => $sc['facts'], 'notes' => $sc['notes']));
  } ?>

  <section class="sec bcats" id="cats" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head" data-reveal><h2 class="h2">Коты породы <em><?php echo esc_html(zh_lower($b->post_title)); ?></em></h2><p class="lead">Котята и взрослые вместе. Фильтр поможет выбрать нужных.</p></div>
      <?php if ($cats) : zh_cat_filters(array($b), $cats, false); ?>
        <div class="cgrid" data-cgrid><?php zh_render_cat_cards($cats); ?></div>
        <p class="k-empty" data-filter-empty hidden>Таких котов сейчас нет. <button class="link-btn" type="button" data-enquire="<?php echo esc_attr($b->post_title); ?>">Напишите нам</button>, расскажем о ближайших помётах.</p>
      <?php else : echo zh_empty_cats('Котов этой породы <em>пока нет</em>', 'Напишите нам, расскажем о ближайших помётах.', $b->post_title); endif; ?>
    </div>
  </section>

  <?php if ($others) : ?>
  <section class="nextb">
    <?php foreach ($others as $o) : $om = zh_media($o); ?>
      <a class="nextb-item" href="<?php echo esc_url(get_permalink($o)); ?>" style="--bg:<?php echo esc_attr(zh_breed_color($o)); ?>">
        <span class="hand">другая порода</span>
        <span class="nextb-name"><?php echo esc_html($o->post_title); ?></span>
        <span class="nextb-go" aria-hidden="true"><?php echo zh_arrow(); ?></span>
        <?php if ($om['url']) : ?><img src="<?php echo esc_url($om['small']); ?>" alt="" loading="lazy" decoding="async" draggable="false"><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
