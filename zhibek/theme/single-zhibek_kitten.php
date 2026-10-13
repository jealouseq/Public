<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post();
  $post = get_post();
  if (post_password_required()) { ?>
    <main id="main"><header class="page-head"><div class="wrap"><h1 class="h1"><?php the_title(); ?></h1></div></header><div class="wrap prose-wrap"><div class="prose"><?php the_content(); ?></div></div></main>
    <?php continue;
  }
  $k = zh_kitten_data($post); $contacts = zh_contacts();
  $age = zh_age($k['birth']);
  $specs = array();
  if ($k['breed_name']) { $specs[] = array('Порода', $k['breed_name']); }
  if ($k['sex']) { $specs[] = array('Пол', $k['sex']); }
  if ($k['color']) { $specs[] = array('Окрас', $k['color']); }
  if ($k['birth']) { $specs[] = array('Родился', zh_date_ru($k['birth']) . ($age ? ' · ' . $age : '')); }
  if ($k['price']) { $specs[] = array('Цена', $k['price']); }
  $wa = isset($contacts['whatsapp']) ? preg_replace('/\?text=.*/', '', $contacts['whatsapp'][1]) . '?text=' . rawurlencode('Здравствуйте! Интересует котёнок ' . $k['title'] . ($k['breed_name'] ? ' (' . $k['breed_name'] . ')' : '') . '.') : '';
  $others = array_slice(array_filter(zh_kittens(array('numberposts' => 4)), function ($p) use ($post) { return $p->ID !== $post->ID; }), 0, 3);
?>
<main id="main">
  <div class="wrap">
    <div class="kitten-grid">
      <div class="kitten-media" style="--bg:<?php echo esc_attr($k['color_hex']); ?>;--vt:k<?php echo (int) $post->ID; ?>"><?php zh_render_animal_media($post); ?></div>
      <div class="kinfo">
        <a class="back" href="<?php echo esc_url(home_url('/kittens/')); ?>"><?php echo zh_arrow('arr-back'); ?><span>Все котята</span></a>
        <?php if ($k['breed']) : ?><a class="kinfo-breed" href="<?php echo esc_url(get_permalink($k['breed'])); ?>"><?php echo esc_html($k['breed_name']); ?></a><?php endif; ?>
        <h1><?php the_title(); ?></h1>
        <div class="kchips">
          <?php if ($k['status_label']) : ?><span class="chip" data-status="<?php echo esc_attr($k['status']); ?>"><?php echo esc_html($k['status_label']); ?></span><?php endif; ?>
          <?php if ($k['demo'] && zh_is_demo()) : ?><span class="chip chip-demo">Пример</span><?php endif; ?>
        </div>
        <?php if ($specs) : ?><dl class="kspecs"><?php foreach ($specs as $sp) : ?><div><dt><?php echo esc_html($sp[0]); ?></dt><dd><?php echo zh_t($sp[1]); ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
        <div class="prose"><?php the_content(); ?></div>
        <div class="kactions">
          <button class="btn btn-primary" type="button" data-enquire="<?php echo esc_attr($k['breed_name'] ?: 'Консультация'); ?>"><span>Хочу познакомиться</span><?php echo zh_arrow(); ?></button>
          <?php if ($wa) : ?><a class="btn btn-outline" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener"><span>WhatsApp</span></a><?php endif; ?>
        </div>
        <p class="knote">Заявка ничего не фиксирует и не требует оплаты.</p>
        <?php if ($k['sire'] || $k['dam']) : ?>
        <div class="lineage">
          <h2 class="h3">Родители</h2>
          <div class="lineage-cards">
            <?php if ($k['sire']) : ?><div class="lineage-card"><span>Отец</span><b><?php echo esc_html($k['sire']); ?></b></div><?php endif; ?>
            <?php if ($k['dam']) : ?><div class="lineage-card"><span>Мать</span><b><?php echo esc_html($k['dam']); ?></b></div><?php endif; ?>
          </div>
          <p class="docs-note">С котёнком выдаём родословную, ветпаспорт и чип.<?php echo ($k['demo'] && zh_is_demo()) ? ' Клички родителей в этом примере вымышлены.' : ''; ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($others) : ?>
  <section class="sec">
    <div class="wrap">
      <div class="sec-head"><div><span class="eyebrow">Ещё котята</span><h2 class="h2">Другие в питомнике</h2></div><a class="link-arrow" href="<?php echo esc_url(home_url('/kittens/')); ?>"><span>Все котята</span><?php echo zh_arrow(); ?></a></div>
      <div class="kgrid"><?php zh_render_kitten_cards($others); ?></div>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
