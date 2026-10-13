<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$breeds = zh_breeds();
$c = zh_content();
$all_cats = zh_cats(); $count = array();
foreach ($all_cats as $cat) { $bid = (int) get_post_meta($cat->ID, '_zhibek_breed', true); $count[$bid] = ($count[$bid] ?? 0) + 1; }
$total = count($breeds);
// Для сравнения: подписи оценок и фактов в порядке появления.
$score_labels = array(); $fact_labels = array(); $scores = array(); $facts = array();
foreach ($breeds as $b) {
  $scores[$b->ID] = array(); foreach (zh_breed_scores($b) as $sc) { $scores[$b->ID][$sc[0]] = (int) $sc[1]; if (!in_array($sc[0], $score_labels, true)) { $score_labels[] = $sc[0]; } }
  $facts[$b->ID] = array(); foreach (zh_breed_facts($b) as $f) { if ($f[0] === '') { continue; } $lab = mb_stripos($f[0], 'Окрас') === 0 ? 'Окрасы' : $f[0]; $facts[$b->ID][$lab] = $f[1]; if (!in_array($lab, $fact_labels, true)) { $fact_labels[] = $lab; } }
}
// В таблицу попадают только признаки, которые есть минимум у двух пород.
$fact_labels = array_values(array_filter($fact_labels, function ($lab) use ($breeds, $facts) { $n = 0; foreach ($breeds as $b) { if (isset($facts[$b->ID][$lab])) { $n++; } } return $n >= 2; }));
$guide = get_posts(array('name' => 'choose-breed', 'post_type' => 'post', 'numberposts' => 1));
?>
<main id="main" class="page-breeds">
  <header class="page-head">
    <div class="wrap">
      <span class="eyebrow">Питомник</span>
      <h1 class="h1">Наши <em>породы</em></h1>
      <p class="lead"><?php echo zh_t($c['breeds']['lead']); ?></p>
      <?php if ($total > 1) : ?>
      <nav class="breed-jump" aria-label="Перейти к породе">
        <?php foreach ($breeds as $b) : ?><a href="#<?php echo esc_attr($b->post_name); ?>" style="--bc:<?php echo esc_attr(zh_breed_color($b)); ?>"><i aria-hidden="true"></i><?php echo esc_html($b->post_title); ?></a><?php endforeach; ?>
        <a href="#compare-breeds" class="breed-jump-cmp">Сравнить</a>
      </nav>
      <?php endif; ?>
    </div>
  </header>

  <div class="bands">
    <?php foreach ($breeds as $i => $b) :
      $m = zh_media($b); $n = (int) ($count[$b->ID] ?? 0);
      $fit = (string) get_post_meta($b->ID, '_zhibek_fit', true);
      $traits = zh_traits($b);
      $chips = array(); foreach ($facts[$b->ID] as $lab => $val) { if (count($chips) < 3 && mb_stripos($lab, 'Глаза') !== 0) { $chips[] = array($lab, $val); } }
      $note = !empty($c['hero']['notes'][$b->post_name]) ? $c['hero']['notes'][$b->post_name] : '';
    ?>
    <section class="band<?php echo $i % 2 ? ' band--flip' : ''; ?>" id="<?php echo esc_attr($b->post_name); ?>" data-breed="<?php echo esc_attr($b->post_name); ?>" style="--bg:<?php echo esc_attr(zh_breed_color($b)); ?>" aria-labelledby="band-<?php echo esc_attr($b->post_name); ?>">
      <div class="band-in wrap">
        <div class="band-copy">
          <span class="band-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?> / <?php echo esc_html(sprintf('%02d', $total)); ?></span>
          <h2 class="band-name" id="band-<?php echo esc_attr($b->post_name); ?>"><a href="<?php echo esc_url(get_permalink($b)); ?>"><?php echo esc_html($b->post_title); ?></a></h2>
          <p class="band-sub"><?php echo zh_t(get_post_meta($b->ID, '_zhibek_subtitle', true)); ?></p>
          <?php if ($fit) : ?><p class="band-fit"><span class="hand">подойдёт, если</span><?php echo zh_t($fit); ?></p><?php endif; ?>
          <?php if ($scores[$b->ID]) : ?>
          <dl class="band-scores"><?php foreach ($scores[$b->ID] as $lab => $val) : ?><div><dt><?php echo esc_html($lab); ?></dt><dd><span class="sr-only"><?php echo (int) $val; ?> из 5</span><span class="dots" aria-hidden="true"><?php for ($d = 1; $d <= 5; $d++) { echo '<i' . ($d <= $val ? ' class="on"' : '') . '></i>'; } ?></span></dd></div><?php endforeach; ?></dl>
          <?php endif; ?>
          <?php if ($chips) : ?><ul class="band-chips"><?php foreach ($chips as $ch) { echo '<li><b>' . esc_html($ch[0]) . '</b> ' . esc_html($ch[1]) . '</li>'; } ?></ul><?php endif; ?>
          <div class="band-actions">
            <a class="btn btn-light" href="<?php echo esc_url(get_permalink($b)); ?>"><span>Смотреть породу</span><?php echo zh_arrow(); ?></a>
            <?php if ($n) : ?><a class="btn btn-line-light" href="<?php echo esc_url(add_query_arg('breed', $b->post_name, home_url('/cats/'))); ?>"><span><?php echo $n . ' ' . esc_html(zh_plural($n, 'кот', 'кота', 'котов')); ?></span></a><?php endif; ?>
          </div>
        </div>
        <div class="band-stage">
          <?php if ($note) : ?><span class="band-note hand" aria-hidden="true"><?php echo zh_hand_arrow(); ?><span><?php echo esc_html($note); ?></span></span><?php endif; ?>
          <?php if ($m['url']) : ?><img class="band-cat" src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> loading="<?php echo $i ? 'lazy' : 'eager'; ?>" decoding="async" draggable="false"><?php endif; ?>
        </div>
      </div>
    </section>
    <?php endforeach; ?>
  </div>

  <?php if ($total > 1 && ($score_labels || $fact_labels)) : ?>
  <section class="cmp-sec" id="compare-breeds" aria-labelledby="cmp-title">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="cmp-title">Породы <em>рядом</em></h2>
        <p class="lead"><?php echo zh_t($c['breeds']['compare']); ?></p>
      </div>
      <div class="cmp-scroll" tabindex="0" role="region" aria-label="Сравнение пород">
        <table class="cmp">
          <thead><tr><td></td><?php foreach ($breeds as $b) : ?><th scope="col" style="--bg:<?php echo esc_attr(zh_breed_color($b)); ?>"><a href="<?php echo esc_url(get_permalink($b)); ?>"><?php echo esc_html($b->post_title); ?></a></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($score_labels as $lab) : ?>
            <tr><th scope="row"><?php echo esc_html($lab); ?></th><?php foreach ($breeds as $b) : $v = $scores[$b->ID][$lab] ?? 0; ?><td><?php if ($v) : ?><span class="sr-only"><?php echo (int) $v; ?> из 5</span><span class="dots" aria-hidden="true"><?php for ($d = 1; $d <= 5; $d++) { echo '<i' . ($d <= $v ? ' class="on"' : '') . '></i>'; } ?></span><?php else : ?>—<?php endif; ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            <?php foreach ($fact_labels as $lab) : ?>
            <tr><th scope="row"><?php echo esc_html($lab); ?></th><?php foreach ($breeds as $b) : ?><td><?php echo isset($facts[$b->ID][$lab]) ? zh_t($facts[$b->ID][$lab]) : '—'; ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            <tr><th scope="row">Подойдёт, если</th><?php foreach ($breeds as $b) : $fit = (string) get_post_meta($b->ID, '_zhibek_fit', true); ?><td><?php echo $fit ? zh_t($fit) : '—'; ?></td><?php endforeach; ?></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="help-sec" aria-label="Помощь с выбором">
    <div class="wrap help-grid">
      <?php if ($guide) : ?>
      <a class="help-card help-card--paper" href="<?php echo esc_url(get_permalink($guide[0])); ?>">
        <span class="help-eyebrow">Журнал · <?php echo (int) zh_read_minutes($guide[0]); ?> мин</span>
        <span class="help-title"><?php echo zh_t($guide[0]->post_title); ?></span>
        <span class="help-go">Читать <?php echo zh_arrow(); ?></span>
      </a>
      <?php endif; ?>
      <div class="help-card help-card--ink">
        <span class="help-eyebrow">Не получается выбрать?</span>
        <span class="help-title"><?php echo zh_t($c['breeds']['help']); ?></span>
        <button class="btn btn-light" type="button" data-enquire="Помощь с выбором породы"><span>Написать нам</span><?php echo zh_arrow(); ?></button>
      </div>
    </div>
  </section>
</main>
<?php get_footer(); ?>
