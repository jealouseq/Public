<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$c = zh_content();
$s = zh_settings();
$breeds = zh_breeds();
$kittens = zh_kittens(array('numberposts' => 4));
$cities = zh_map_city_list();
$posts = get_posts(array('numberposts' => 3, 'post_status' => 'publish', 'category_name' => 'journal', 'ignore_sticky_posts' => true));
if (!$posts) { $posts = get_posts(array('numberposts' => 3, 'post_status' => 'publish', 'ignore_sticky_posts' => true)); }
$products = function_exists('wc_get_products') ? wc_get_products(array('limit' => 4, 'status' => 'publish', 'visibility' => 'catalog')) : array();
$total = count($breeds);
?>
<main id="main">

  <section class="hero" aria-labelledby="hero-title">
    <div class="wrap">
      <div class="hero-grid">
        <div class="hero-copy">
          <span class="eyebrow" data-hero style="--i:0">Питомник · <?php echo esc_html($s['city']); ?></span>
          <h1 class="h1" id="hero-title" data-hero style="--i:1"><?php echo zh_t($c['hero']['title']); ?></h1>
          <p class="lead" data-hero style="--i:2"><?php echo zh_t($c['hero']['lead']); ?></p>
          <div class="hero-cta" data-hero style="--i:3">
            <a class="btn btn-primary" href="<?php echo esc_url(home_url('/kittens/')); ?>"><span><?php echo zh_t($c['hero']['cta_primary']); ?></span><?php echo zh_arrow(); ?></a>
            <button class="btn btn-outline" type="button" data-enquire="Консультация"><span><?php echo zh_t($c['hero']['cta_secondary']); ?></span></button>
          </div>
        </div>
        <figure class="hero-photo">
          <div class="hero-frame"><?php echo zh_scene_img('trio', 'Абиссинская, бенгальская и сиамская кошки', '(min-width: 960px) 46vw, 92vw', 'fetchpriority="high"'); ?></div>
          <nav class="hero-breeds" aria-label="Породы" data-hero style="--i:4">
            <?php foreach (array_slice($breeds, 0, 3) as $b) : ?>
              <a href="<?php echo esc_url(get_permalink($b)); ?>">
                <b><span><?php echo esc_html($b->post_title); ?></span><?php echo zh_arrow_up_right(); ?></b>
                <span><?php echo zh_t($c['breeds']['hint'][$b->post_name] ?? ''); ?></span>
              </a>
            <?php endforeach; ?>
          </nav>
        </figure>
      </div>
      <div class="facts">
        <?php foreach ($c['facts'] as $i => $f) : ?>
          <div class="fact" data-reveal style="--d:<?php echo (int) $i; ?>"><b><?php echo zh_t($f[0]); ?></b><span><?php echo zh_t($f[1]); ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($breeds) : ?>
  <section class="breeds" id="breeds" aria-labelledby="breeds-title">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="breeds-title"><?php echo zh_t($c['breeds']['title']); ?></h2>
        <p class="lead"><?php echo zh_t($c['breeds']['lead']); ?></p>
      </div>
      <div class="stack">
        <?php foreach ($breeds as $i => $b) { echo zh_breed_panel($b, $i, $total); } ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec" id="kittens" aria-labelledby="kittens-title">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="kittens-title"><?php echo zh_t($c['kittens']['title']); ?></h2>
        <p class="lead"><?php echo zh_t($c['kittens']['lead']); ?></p>
      </div>
      <?php if ($kittens) : ?>
        <div class="kgrid kgrid--scroll kgrid--4" data-reveal><?php zh_render_kitten_cards($kittens); ?></div>
        <p style="margin-top:40px"><a class="link-arrow" href="<?php echo esc_url(home_url('/kittens/')); ?>"><span>Все котята</span><?php echo zh_arrow(); ?></a></p>
      <?php else : ?>
        <p class="k-empty"><?php echo zh_t($c['kittens']['empty']); ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="sec" id="process" aria-labelledby="process-title" style="padding-top:0">
    <div class="wrap">
      <div class="process-grid">
        <div class="process-intro" data-reveal>
          
          <h2 class="h2" id="process-title"><?php echo zh_t($c['process']['title']); ?></h2>
          <p class="lead"><?php echo zh_t($c['process']['lead']); ?></p>
          <button class="btn btn-primary" type="button" data-enquire="Консультация"><span>Написать нам</span><?php echo zh_arrow(); ?></button>
          <figure class="process-photo"><?php echo zh_scene_img('lifestyle', 'Абиссинская кошка на диване у окна', '(min-width: 960px) 38vw, 92vw', 'loading="lazy"'); ?></figure>
        </div>
        <ol class="steps">
          <?php foreach ($c['process']['steps'] as $i => $st) : ?>
            <li data-reveal><h3><?php echo zh_t($st[0]); ?></h3><p><?php echo zh_t($st[1]); ?></p></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </section>

  <section class="sec" id="delivery" aria-labelledby="delivery-title" style="padding-top:0">
    <div class="wrap">
      <div class="delivery" data-reveal>
        <div class="delivery-copy">
          
          <h2 class="h2" id="delivery-title"><?php echo zh_t($c['delivery']['title']); ?></h2>
          <p><?php echo zh_t($c['delivery']['text']); ?></p>
          <?php if ($cities) : ?><ul class="cities"><?php foreach ($cities as $city) { echo '<li>' . esc_html($city) . '</li>'; } ?></ul><?php endif; ?>
        </div>
        <div class="delivery-map"><?php echo zh_map_svg(); ?></div>
      </div>
    </div>
  </section>

  <?php if ($products) : ?>
  <section class="sec" id="shop" aria-labelledby="shop-title" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head" data-reveal>
        <h2 class="h2" id="shop-title">Для дома</h2>
        <a class="link-arrow" href="<?php echo esc_url(zh_wc_url('shop')); ?>"><span>Весь магазин</span><?php echo zh_arrow(); ?></a>
      </div>
      <div class="pgrid">
        <?php foreach ($products as $p) : ?>
          <a class="pcard" href="<?php echo esc_url($p->get_permalink()); ?>" data-reveal>
            <span class="pcard-img"><?php zh_product_image($p); ?></span>
            <span class="pcard-name"><?php echo esc_html($p->get_name()); ?></span>
            <span class="pcard-price"><?php echo wp_kses_post($p->get_price_html()); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec" id="faq" aria-labelledby="faq-title" style="padding-top:0">
    <div class="wrap faq-grid">
      <div class="faq-head" data-reveal><h2 class="h2" id="faq-title"><?php echo zh_t($c['faq']['title']); ?></h2></div>
      <div class="faq-list">
        <?php foreach ($c['faq']['items'] as $i => $q) : ?>
          <details class="faq-item" data-reveal><summary><?php echo zh_t($q[0]); ?></summary><p><?php echo zh_t($q[1]); ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($posts) : ?>
  <section class="sec" id="journal" aria-labelledby="journal-title" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head" data-reveal><h2 class="h2" id="journal-title"><?php echo zh_t($c['journal']['title']); ?></h2><?php $jc = get_category_by_slug('journal'); $jurl = $jc ? get_category_link($jc) : (get_option('page_for_posts') ? get_permalink(get_option('page_for_posts')) : ''); if ($jurl) : ?><a class="link-arrow" href="<?php echo esc_url($jurl); ?>"><span>Все статьи</span><?php echo zh_arrow(); ?></a><?php endif; ?></div>
      <div class="posts">
        <?php foreach ($posts as $i => $p) : ?>
          <a class="post-card" href="<?php echo esc_url(get_permalink($p)); ?>" data-reveal style="--d:<?php echo (int) $i; ?>">
            <div><span class="meta"><?php echo esc_html(zh_date_ru(get_the_date('Y-m-d', $p))); ?></span><h3><?php echo zh_t(get_the_title($p)); ?></h3><p><?php echo zh_t(wp_trim_words(get_the_excerpt($p), 20)); ?></p></div>
            <span class="go">Читать<?php echo zh_arrow(); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

</main>
<?php get_footer(); ?>
