<?php
if (!defined('ABSPATH')) { exit; }
get_header();

$c = zh_content();
$breeds = zh_breeds();
$kittens = zh_kittens(array('numberposts' => 6));
$contacts = zh_contacts();
$hero_img = zh_img('cats/hero-abyssinian.webp');
$eye_colors = array();
foreach ($breeds as $b) { $eye_colors[] = zh_breed_world($b)['eye']; }
if (!$eye_colors) { $eye_colors = array('#d9a238', '#9ec35b', '#4f8fd6'); }
$cities = zh_map_city_list();
?>
<main id="main" class="home">

<!-- ═════ 01 · ВРАТА И ГЕРОЙ ═════ -->
<section class="gate" id="top" data-chapter="Начало" data-header="dark" data-gate>
  <div class="gate-pin">
    <div class="hero" data-hero>
      <div class="hero-room" aria-hidden="true"><span class="hero-halo"></span><span class="hero-beam"></span><span class="hero-floor"></span></div>
      <div class="hero-dust" aria-hidden="true" data-dust></div>
      <h1 class="hero-word" data-hero-word><?php echo zh_wordmark('wm wm-hero'); ?><span class="sr-only"> — питомник кошек в Казахстане</span></h1>
      <figure class="hero-cat" data-hero-cat>
        <span class="hero-shadow" aria-hidden="true"></span>
        <img src="<?php echo esc_url($hero_img); ?>" width="835" height="1179" alt="Абиссинская кошка с чуть склонённой головой" fetchpriority="high" decoding="async" draggable="false">
      </figure>
      <div class="hero-light" aria-hidden="true"></div>
      <div class="hero-copy" data-hero-copy>
        <span class="eyebrow"><?php echo esc_html($c['hero']['eyebrow']); ?></span>
        <p class="hero-tagline"><?php echo esc_html($c['hero']['tagline']); ?></p>
        <p class="lead"><?php echo esc_html($c['hero']['lead']); ?></p>
        <div class="hero-ctas">
          <a class="btn btn-gold" href="#kittens" data-magnetic data-scrollto><span><?php echo esc_html($c['hero']['cta_primary']); ?></span><?php echo zh_arrow(); ?></a>
          <button class="btn btn-ghost" type="button" data-enquire="Консультация" data-magnetic><span><?php echo esc_html($c['hero']['cta_secondary']); ?></span></button>
        </div>
      </div>
      <div class="hero-aside" aria-hidden="true">
        <span class="hero-scroll"><i></i>Листайте</span>
      </div>
    </div>
    <div class="doors" data-doors aria-hidden="true">
      <div class="doors-glow"></div>
      <div class="door door-l"><span class="door-face"><?php echo zh_door_emblem('l'); ?></span></div>
      <div class="door door-r"><span class="door-face"><?php echo zh_door_emblem('r'); ?></span></div>
      <div class="doors-copy"><span class="doors-name">ZHIBEK</span><span class="doors-hint"><?php echo esc_html($c['gate']['hint']); ?></span></div>
    </div>
  </div>
</section>

<!-- ═════ 02 · ВЗГЛЯД (WebGL) ═════ -->
<section class="eye" id="eye" data-chapter="Взгляд" data-header="dark" data-eye data-colors="<?php echo esc_attr(implode(',', $eye_colors)); ?>">
  <div class="eye-pin">
    <canvas class="eye-canvas" data-eye-canvas aria-hidden="true"></canvas>
    <div class="eye-fallback" aria-hidden="true"><span></span></div>
    <div class="eye-shade" aria-hidden="true"></div>
    <div class="eye-copy">
      <?php foreach ($c['eye']['lines'] as $i => $line) : ?>
        <p class="eye-line" data-eye-line="<?php echo (int) $i; ?>"><?php
          if ($i === 1 && count($eye_colors) >= 3) {
              $parts = array_map('trim', explode('.', rtrim($line, '.')));
              foreach ($parts as $pi => $part) { echo '<span class="eye-word" data-eye-word="' . (int) $pi . '" style="--c:' . esc_attr($eye_colors[$pi % count($eye_colors)]) . '">' . esc_html($part) . '.</span> '; }
          } else { echo esc_html($line); }
        ?></p>
      <?php endforeach; ?>
    </div>
    <ul class="eye-dots" aria-hidden="true"><?php foreach ($eye_colors as $i => $col) { echo '<li data-eye-dot="' . (int) $i . '" style="--c:' . esc_attr($col) . '"></li>'; } ?></ul>
  </div>
</section>

<!-- ═════ 03 · ТРИ МИРА ═════ -->
<?php if ($breeds) : $n = count($breeds); ?>
<section class="worlds" id="breeds" data-chapter="Породы" data-header="dark" data-worlds data-count="<?php echo (int) $n; ?>">
  <div class="worlds-pin">
    <div class="worlds-bg" aria-hidden="true">
      <?php foreach ($breeds as $i => $b) : $w = zh_breed_world($b); ?>
        <div class="wbg wbg-<?php echo esc_attr($w['kind']); ?>" data-wbg="<?php echo (int) $i; ?>" style="--accent:<?php echo esc_attr($w['accent']); ?>;--glow:<?php echo esc_attr($w['glow']); ?>;--bg1:<?php echo esc_attr($w['bg1']); ?>;--bg2:<?php echo esc_attr($w['bg2']); ?>">
          <span class="wbg-sky"></span><span class="wbg-orb"></span><span class="wbg-rays"></span><span class="wbg-mist"></span><span class="wbg-stars"></span>
        </div>
      <?php endforeach; ?>
      <canvas class="worlds-rosette" data-rosette></canvas>
    </div>
    <div class="worlds-stage">
      <?php foreach ($breeds as $i => $b) : $w = zh_breed_world($b); $m = zh_media($b); ?>
        <figure class="wcat" data-wcat="<?php echo (int) $i; ?>" style="--accent:<?php echo esc_attr($w['accent']); ?>;--glow:<?php echo esc_attr($w['glow']); ?>">
          <span class="wcat-shadow" aria-hidden="true"></span>
          <?php if ($m['url']) : ?><img src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> loading="lazy" decoding="async" draggable="false"><?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
    <div class="worlds-ui">
      <div class="wslides">
        <?php foreach ($breeds as $i => $b) : $w = zh_breed_world($b); $facts = zh_breed_facts($b); $label = $c['worlds']['labels'][$b->post_name] ?? ''; ?>
          <article class="wslide" data-wslide="<?php echo (int) $i; ?>" style="--accent:<?php echo esc_attr($w['accent']); ?>;--eye:<?php echo esc_attr($w['eye']); ?>">
            <div class="wslide-top"><span class="wnum"><?php echo esc_html(sprintf('%02d', $i + 1)); ?><i>/</i><?php echo esc_html(sprintf('%02d', $n)); ?></span><?php if ($label) : ?><span class="wlabel"><?php echo esc_html($label); ?></span><?php endif; ?></div>
            <h2 class="wname display-l" data-split><?php echo esc_html($b->post_title); ?></h2>
            <p class="wsub"><?php echo esc_html(get_post_meta($b->ID, '_zhibek_subtitle', true)); ?></p>
            <?php if ($facts) : ?>
              <dl class="wfacts"><?php foreach ($facts as $f) : ?><div><dt><?php echo esc_html($f[0]); ?></dt><dd><?php echo esc_html($f[1]); ?></dd></div><?php endforeach; ?></dl>
            <?php endif; ?>
            <?php $fit = get_post_meta($b->ID, '_zhibek_fit', true); $note = get_post_meta($b->ID, '_zhibek_note', true); if ($fit || $note) : ?>
              <div class="wnotes"><?php if ($fit) : ?><p><b>Подойдёт, если</b> <?php echo esc_html(lcfirst($fit)); ?></p><?php endif; ?><?php if ($note) : ?><p><b>Важно знать</b> <?php echo esc_html($note); ?></p><?php endif; ?></div>
            <?php endif; ?>
            <div class="wlinks">
              <a class="btn btn-gold" href="<?php echo esc_url(add_query_arg('breed', $b->post_name, home_url('/kittens/'))); ?>" data-magnetic><span>Котята: <?php echo esc_html(mb_strtolower($b->post_title)); ?></span><?php echo zh_arrow(); ?></a>
              <a class="link-arrow" href="<?php echo esc_url(get_permalink($b)); ?>"><span>О породе</span><?php echo zh_arrow(); ?></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <nav class="wnav" aria-label="Породы">
        <?php foreach ($breeds as $i => $b) : ?><button type="button" data-wgo="<?php echo (int) $i; ?>" style="--c:<?php echo esc_attr(zh_breed_world($b)['eye']); ?>"><i></i><span><?php echo esc_html($b->post_title); ?></span></button><?php endforeach; ?>
      </nav>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═════ 04 · ГАЛЕРЕЯ (горизонтальный скролл) ═════ -->
<section class="gallery" id="gallery" data-chapter="Галерея" data-header="light" data-hgallery>
  <div class="gallery-pin">
    <div class="gallery-track" data-track>
      <div class="gintro">
        <span class="eyebrow"><?php echo esc_html($c['gallery']['eyebrow']); ?></span>
        <h2 class="display-xl"><?php echo esc_html($c['gallery']['title']); ?></h2>
        <span class="gintro-hint"><?php echo zh_arrow(); ?> листайте вниз</span>
      </div>
      <?php foreach ($c['gallery']['frames'] as $f) : ?>
        <figure class="gframe gframe-wide" data-w="<?php echo (int) $f['w']; ?>" style="--ar:<?php echo (int) $f['w']; ?>/<?php echo (int) $f['h']; ?>">
          <button class="gframe-btn" type="button" data-zoom data-cursor="Смотреть" aria-label="Открыть: <?php echo esc_attr($f['title']); ?>"><img src="<?php echo esc_url(zh_img($f['img'])); ?>" width="<?php echo (int) $f['w']; ?>" height="<?php echo (int) $f['h']; ?>" alt="<?php echo esc_attr($f['text']); ?>" loading="lazy" decoding="async" draggable="false"></button>
          <figcaption><span class="gnum"><?php echo esc_html($f['n']); ?></span><b><?php echo esc_html($f['title']); ?></b><span><?php echo esc_html($f['text']); ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
      <?php $gi = 4; foreach ($breeds as $b) : $w = zh_breed_world($b); $m = zh_media($b); if (!$m['url']) { continue; } ?>
        <figure class="gframe gframe-portrait" style="--bg1:<?php echo esc_attr($w['bg1']); ?>;--bg2:<?php echo esc_attr($w['bg2']); ?>;--glow:<?php echo esc_attr($w['glow']); ?>;--accent:<?php echo esc_attr($w['accent']); ?>">
          <a class="gframe-btn gportrait" href="<?php echo esc_url(get_permalink($b)); ?>" data-cursor="Открыть" aria-label="Порода: <?php echo esc_attr($b->post_title); ?>"><span class="gp-glow" aria-hidden="true"></span><img src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>" loading="lazy" decoding="async" draggable="false"></a>
          <figcaption><span class="gnum"><?php echo esc_html(sprintf('%02d', $gi++)); ?></span><b><?php echo esc_html($b->post_title); ?></b><span><?php echo esc_html(get_post_meta($b->ID, '_zhibek_traits', true) ? implode(', ', array_slice(zh_traits($b), 0, 2)) : ''); ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
      <div class="gend">
        <a class="btn btn-dark" href="<?php echo esc_url(home_url('/kittens/')); ?>" data-magnetic><span><?php echo esc_html($c['gallery']['cta']); ?></span><?php echo zh_arrow(); ?></a>
      </div>
    </div>
    <div class="gallery-bar" aria-hidden="true"><span></span></div>
  </div>
</section>

<!-- ═════ 05 · РОДОСЛОВНАЯ ═════ -->
<section class="pedigree" id="pedigree" data-chapter="Документы" data-header="dark" data-pedigree>
  <div class="wrap pedigree-grid">
    <div class="pedigree-copy">
      <span class="eyebrow"><?php echo esc_html($c['pedigree']['eyebrow']); ?></span>
      <h2 class="display-l"><?php echo esc_html($c['pedigree']['title']); ?></h2>
      <p class="lead"><?php echo esc_html($c['pedigree']['text']); ?></p>
      <div class="pnode-card" data-pcard aria-live="polite">
        <span class="pnode-rel" data-pcard-rel></span>
        <b class="pnode-name" data-pcard-name></b>
        <span class="pnode-meta" data-pcard-meta></span>
      </div>
    </div>
    <div class="pedigree-chart"><?php echo zh_pedigree_svg(); ?><span class="chip chip-sample"><?php echo esc_html($c['pedigree']['sample']); ?></span></div>
  </div>
  <div class="wrap kit">
    <h3 class="kit-title"><?php echo esc_html($c['pedigree']['kit_title']); ?></h3>
    <ul class="kit-grid">
      <?php $icons = array('doc', 'shield', 'chip', 'contract', 'gift', 'chat'); foreach ($c['pedigree']['kit'] as $i => $k) : ?>
        <li class="kit-item"><?php echo zh_icon($icons[$i % count($icons)]); ?><b><?php echo esc_html($k[0]); ?></b><span><?php echo esc_html($k[1]); ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ═════ 06 · КОЛЛЕКЦИЯ КОТЯТ ═════ -->
<section class="collection" id="kittens" data-chapter="Котята" data-header="light">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="eyebrow"><?php echo esc_html($c['collection']['eyebrow']); ?></span><h2 class="display-l"><?php echo esc_html($c['collection']['title']); ?></h2></div>
      <p class="sec-head-text"><?php echo esc_html($c['collection']['text']); ?></p>
    </div>
    <?php zh_kitten_filters($breeds, $kittens); ?>
    <div class="kgrid" data-kgrid><?php zh_render_kitten_cards($kittens); ?></div>
    <p class="kempty" hidden><?php echo esc_html($c['collection']['empty']); ?> <button class="link-btn" type="button" data-enquire="Ближайшие помёты">Написать нам</button></p>
    <div class="collection-foot"><a class="link-arrow" href="<?php echo esc_url(home_url('/kittens/')); ?>"><span>Все котята</span><?php echo zh_arrow(); ?></a></div>
  </div>
</section>

<!-- ═════ 07 · КАК ЭТО ПРОИСХОДИТ + КАРТА ═════ -->
<section class="process" id="process" data-chapter="Питомник" data-header="dark">
  <div class="wrap process-grid">
    <div class="process-copy">
      <span class="eyebrow"><?php echo esc_html($c['process']['eyebrow']); ?></span>
      <h2 class="display-l"><?php echo esc_html($c['process']['title']); ?></h2>
      <ol class="steps">
        <?php foreach ($c['process']['steps'] as $i => $st) : ?>
          <li class="step"><span class="step-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span><div><b><?php echo esc_html($st[0]); ?></b><p><?php echo esc_html($st[1]); ?></p></div></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <div class="process-map" data-map>
      <span class="eyebrow"><?php echo esc_html($c['map']['eyebrow']); ?></span>
      <h3 class="display-s"><?php echo esc_html($c['map']['title']); ?></h3>
      <p><?php echo esc_html($c['map']['text']); ?></p>
      <?php echo zh_map_svg(); ?>
      <?php if ($cities) : ?><ul class="city-list"><?php foreach ($cities as $city) { echo '<li><button type="button" data-city-chip="' . esc_attr($city) . '">' . esc_html($city) . '</button></li>'; } ?></ul><?php endif; ?>
    </div>
  </div>
</section>

<!-- ═════ 08 · ЖУРНАЛ ═════ -->
<?php $articles = get_posts(array('post_type' => 'post', 'posts_per_page' => 3, 'post_status' => 'publish', 'category_name' => 'journal')); ?>
<?php if ($articles) : ?>
<section class="journal" id="journal" data-chapter="Журнал" data-header="light">
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow"><?php echo esc_html($c['journal']['eyebrow']); ?></span><h2 class="display-l"><?php echo esc_html($c['journal']['title']); ?></h2></div></div>
    <div class="journal-grid">
      <?php foreach ($articles as $i => $a) : ?>
        <a class="jcard<?php echo $i === 0 ? ' jcard-lead' : ''; ?>" href="<?php echo esc_url(get_permalink($a)); ?>" data-cursor="Читать">
          <span class="jcard-n"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
          <h3><?php echo esc_html($a->post_title); ?></h3>
          <p><?php echo esc_html(wp_trim_words(get_the_excerpt($a) ?: wp_strip_all_tags($a->post_content), $i === 0 ? 30 : 18)); ?></p>
          <span class="link-arrow"><span>Читать</span><?php echo zh_arrow(); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═════ 09 · ВОПРОСЫ ═════ -->
<section class="faq" data-chapter="Вопросы" data-header="light">
  <div class="wrap faq-grid">
    <div><span class="eyebrow"><?php echo esc_html($c['faq']['eyebrow']); ?></span><h2 class="display-l"><?php echo esc_html($c['faq']['title']); ?></h2></div>
    <div class="faq-list">
      <?php foreach ($c['faq']['items'] as $item) : ?>
        <details><summary><span><?php echo esc_html($item[0]); ?></span><i aria-hidden="true"></i></summary><p><?php echo esc_html($item[1]); ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═════ 10 · ФИНАЛ ═════ -->
<section class="finale" id="contact" data-chapter="Контакты" data-header="dark" data-finale>
  <canvas class="finale-canvas" data-particles data-src="<?php echo esc_url($hero_img); ?>" aria-hidden="true"></canvas>
  <div class="wrap finale-inner">
    <span class="eyebrow"><?php echo esc_html($c['finale']['eyebrow']); ?></span>
    <h2 class="display-xl"><?php echo esc_html($c['finale']['title']); ?></h2>
    <p class="lead"><?php echo esc_html($c['finale']['text']); ?></p>
    <div class="finale-actions">
      <button class="btn btn-gold btn-lg" type="button" data-enquire="Консультация" data-magnetic><span>Оставить заявку</span><?php echo zh_arrow(); ?></button>
      <?php foreach (array('whatsapp', 'telegram', 'instagram') as $k) : if (isset($contacts[$k])) : ?>
        <a class="btn btn-ghost" href="<?php echo esc_url($contacts[$k][1]); ?>" target="_blank" rel="noopener" data-magnetic><?php echo zh_icon($k); ?><span><?php echo esc_html($contacts[$k][0]); ?></span></a>
      <?php endif; endforeach; ?>
    </div>
    <?php if (isset($contacts['phone']) || isset($contacts['email'])) : ?>
      <p class="finale-lines"><?php foreach (array('phone', 'email') as $k) { if (isset($contacts[$k])) { echo '<a href="' . esc_url($contacts[$k][1]) . '">' . esc_html($contacts[$k][0]) . '</a>'; } } ?></p>
    <?php endif; ?>
  </div>
</section>

</main>
<?php get_footer(); ?>
