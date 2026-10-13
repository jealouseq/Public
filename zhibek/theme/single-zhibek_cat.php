<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post();
  $post = get_post();
  if (post_password_required()) { ?>
    <main id="main"><header class="page-head"><div class="wrap"><h1 class="h1"><?php the_title(); ?></h1></div></header><div class="wrap prose-wrap"><div class="prose"><?php the_content(); ?></div></div></main>
    <?php continue;
  }
  $k = zh_cat_data($post); $contacts = zh_contacts();
  $age = zh_age($k['birth']);
  $specs = array();
  if ($k['breed_name']) { $specs[] = array('Порода', $k['breed_name']); }
  $specs[] = array('Возраст', $k['kind_label'] . ($age ? ', ' . $age : ''));
  if ($k['sex']) { $specs[] = array('Пол', $k['sex']); }
  if ($k['color']) { $specs[] = array('Окрас', $k['color']); }
  if ($k['birth']) { $specs[] = array('Родился', zh_date_ru($k['birth'])); }
  if ($k['price']) { $specs[] = array('Цена', $k['price']); }
  $wa = isset($contacts['whatsapp']) ? preg_replace('/\?text=.*/', '', $contacts['whatsapp'][1]) . '?text=' . rawurlencode('Здравствуйте! Интересует ' . $k['title'] . ($k['breed_name'] ? ' (' . zh_lower($k['breed_name']) . ')' : '') . '.') : '';
  // Другие коты: сначала той же породы, затем остальные.
  $breed_id = $k['breed'] ? $k['breed']->ID : 0;
  $others = array_values(array_filter(zh_cats(), function ($p) use ($post) { return $p->ID !== $post->ID; }));
  usort($others, function ($a, $b) use ($breed_id) { return ((int) get_post_meta($a->ID, '_zhibek_breed', true) === $breed_id ? 0 : 1) <=> ((int) get_post_meta($b->ID, '_zhibek_breed', true) === $breed_id ? 0 : 1); });
  $others = array_slice($others, 0, 3);
?>
<main id="main">
  <div class="wrap">
    <div class="cat-grid">
      <div class="cat-media" style="--bg:<?php echo esc_attr($k['color_hex']); ?>;--vt:c<?php echo (int) $post->ID; ?>"><?php zh_render_animal_media($post); ?></div>
      <div class="cinfo">
        <a class="back" href="<?php echo esc_url($k['breed'] ? get_permalink($k['breed']) : home_url('/cats/')); ?>"><?php echo zh_arrow('arr-back'); ?><span><?php echo $k['breed'] ? esc_html($k['breed_name']) : 'Все коты'; ?></span></a>
        <h1><?php the_title(); ?></h1>
        <div class="kchips">
          <?php if ($k['status_label']) : ?><span class="chip" data-status="<?php echo esc_attr($k['status']); ?>"><?php echo esc_html($k['status_label']); ?></span><?php endif; ?>
          <span class="chip chip-plain"><?php echo esc_html($k['kind_label']); ?></span>
          <?php if ($k['demo'] && zh_is_demo()) : ?><span class="chip chip-demo">Пример</span><?php endif; ?>
        </div>
        <?php if ($specs) : ?><dl class="kspecs"><?php foreach ($specs as $sp) : ?><div><dt><?php echo esc_html($sp[0]); ?></dt><dd><?php echo zh_t($sp[1]); ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
        <div class="prose"><?php the_content(); ?></div>
        <div class="kactions">
          <button class="btn btn-primary" type="button" data-enquire="<?php echo esc_attr($k['breed_name'] ?: 'Консультация'); ?>"><span><?php echo $k['status'] === 'breeder' ? 'Спросить о вязке' : 'Хочу познакомиться'; ?></span><?php echo zh_arrow(); ?></button>
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
      <div class="sec-head"><h2 class="h2">Ещё коты</h2><a class="link-arrow" href="<?php echo esc_url(home_url('/cats/')); ?>"><span>Все коты</span><?php echo zh_arrow(); ?></a></div>
      <div class="cgrid cgrid--3"><?php zh_render_cat_cards($others); ?></div>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
