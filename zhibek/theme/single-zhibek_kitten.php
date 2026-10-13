<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post();
  $post = get_post();
  if (post_password_required()) { ?>
    <main id="main" class="inner"><header class="page-hero" data-header="dark"><div class="wrap"><h1 class="display-l"><?php the_title(); ?></h1></div></header><div class="wrap prose-wrap"><div class="prose"><?php the_content(); ?></div></div></main>
    <?php continue;
  }
  $k = zh_kitten_data($post); $w = $k['world']; $contacts = zh_contacts();
  $age = zh_age($k['birth']);
  $style = '--accent:' . esc_attr($w['accent']) . ';--glow:' . esc_attr($w['glow']) . ';--bg1:' . esc_attr($w['bg1']) . ';--bg2:' . esc_attr($w['bg2']) . ';--eye:' . esc_attr($w['eye']);
  $specs = array();
  if ($k['breed_name']) { $specs[] = array('Порода', $k['breed_name']); }
  if ($k['sex']) { $specs[] = array('Пол', $k['sex']); }
  if ($k['color']) { $specs[] = array('Окрас', $k['color']); }
  if ($k['birth']) { $specs[] = array('Родился', zh_date_ru($k['birth']) . ($age ? ' · ' . $age : '')); }
  if ($k['price']) { $specs[] = array('Цена', $k['price']); }
  $wa = isset($contacts['whatsapp']) ? $contacts['whatsapp'][1] : '';
  if ($wa) { $wa = preg_replace('/\?text=.*/', '', $wa) . '?text=' . rawurlencode('Здравствуйте! Интересует котёнок ' . $k['title'] . ' (' . $k['breed_name'] . ').'); }
  $others = array_filter(zh_kittens(array('numberposts' => 4)), function ($p) use ($post) { return $p->ID !== $post->ID; });
  $others = array_slice($others, 0, 3);
?>
<main id="main" class="inner kitten-page">
  <section class="khero" data-header="dark" style="<?php echo $style; ?>">
    <div class="wbg wbg-<?php echo esc_attr($w['kind']); ?>" aria-hidden="true"><span class="wbg-sky"></span><span class="wbg-orb"></span><span class="wbg-rays"></span><span class="wbg-mist"></span><span class="wbg-stars"></span></div>
    <div class="wrap khero-grid">
      <div class="khero-media" data-kitten-hero><?php zh_render_animal_media($post); ?></div>
      <div class="khero-info">
        <a class="back" href="<?php echo esc_url(home_url('/kittens/')); ?>"><?php echo zh_arrow('arr-back'); ?><span>Все котята</span></a>
        <?php if ($k['breed']) : ?><a class="khero-breed" href="<?php echo esc_url(get_permalink($k['breed'])); ?>"><?php echo esc_html($k['breed_name']); ?></a><?php endif; ?>
        <h1 class="display-xl"><?php the_title(); ?></h1>
        <div class="khero-chips">
          <?php if ($k['status_label']) : ?><span class="chip chip-status" data-status="<?php echo esc_attr($k['status']); ?>"><?php echo esc_html($k['status_label']); ?></span><?php endif; ?>
          <?php if ($k['demo'] && zh_is_demo()) : ?><span class="chip chip-demo">Пример</span><?php endif; ?>
        </div>
        <?php if ($specs) : ?><dl class="kspecs"><?php foreach ($specs as $s) : ?><div><dt><?php echo esc_html($s[0]); ?></dt><dd><?php echo esc_html($s[1]); ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
        <div class="prose prose-dark"><?php the_content(); ?></div>
        <div class="khero-actions">
          <button class="btn btn-gold" type="button" data-enquire="<?php echo esc_attr($k['breed_name'] ?: 'Консультация'); ?>" data-magnetic><span>Хочу познакомиться</span><?php echo zh_arrow(); ?></button>
          <?php if ($wa) : ?><a class="btn btn-ghost" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener" data-magnetic><?php echo zh_icon('whatsapp'); ?><span>Написать в WhatsApp</span></a><?php endif; ?>
          <button class="kcard-save kcard-save-lg" type="button" data-save="<?php echo (int) $post->ID; ?>" aria-pressed="false" aria-label="Сохранить"><?php echo zh_icon('heart'); ?></button>
        </div>
        <p class="khero-note">Заявка ничего не фиксирует и не требует оплаты.</p>
      </div>
    </div>
  </section>

  <section class="passport-sec" data-header="dark" data-passport>
    <div class="wrap passport-grid">
      <div class="passport-copy">
        <span class="eyebrow">Паспорт</span>
        <h2 class="display-m">Всё о котёнке <em>в одном месте</em></h2>
        <p class="lead">Откройте паспорт: происхождение, родители и документы, которые вы получите вместе с котёнком.</p>
        <button class="btn btn-ghost" type="button" data-passport-toggle aria-expanded="false"><span>Открыть паспорт</span></button>
      </div>
      <div class="passport" data-passport-book>
        <div class="pp-book">
          <div class="pp-back"></div>
          <div class="pp-page pp-right">
            <span class="pp-title">Происхождение</span>
            <dl class="pp-list">
              <?php if ($k['breed_name']) : ?><div><dt>Порода</dt><dd><?php echo esc_html($k['breed_name']); ?></dd></div><?php endif; ?>
              <?php if ($k['color']) : ?><div><dt>Окрас</dt><dd><?php echo esc_html($k['color']); ?></dd></div><?php endif; ?>
              <?php if ($k['sire']) : ?><div><dt>Отец</dt><dd><?php echo esc_html($k['sire']); ?></dd></div><?php endif; ?>
              <?php if ($k['dam']) : ?><div><dt>Мать</dt><dd><?php echo esc_html($k['dam']); ?></dd></div><?php endif; ?>
              <div><dt>Документы</dt><dd>Родословная, ветпаспорт, чип</dd></div>
            </dl>
            <?php if ($k['demo'] && zh_is_demo()) : ?><span class="chip chip-demo pp-demo">Пример</span><?php endif; ?>
          </div>
          <div class="pp-leaf" data-passport-leaf>
            <div class="pp-cover">
              <?php echo zh_mark('pp-mark'); ?>
              <span class="pp-cover-title">Паспорт котёнка</span>
              <span class="pp-cover-brand"><?php echo zh_wordmark('wm'); ?></span>
              <span class="pp-cover-line" aria-hidden="true"></span>
            </div>
            <div class="pp-page pp-left">
              <?php $m = zh_media($post); if ($m['url']) : ?><span class="pp-photo" data-type="<?php echo esc_attr($m['type']); ?>"><img src="<?php echo esc_url($m['url']); ?>" alt="" loading="lazy" decoding="async"></span><?php endif; ?>
              <b class="pp-name"><?php the_title(); ?></b>
              <span class="pp-no">Карточка № <?php echo esc_html(sprintf('%04d', $post->ID)); ?></span>
              <?php if ($age) : ?><span class="pp-age"><?php echo esc_html($age); ?></span><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($others) : ?>
  <section class="collection collection-inner" data-header="light">
    <div class="wrap">
      <div class="sec-head"><div><span class="eyebrow">Ещё котята</span><h2 class="display-m">Другие в питомнике</h2></div><a class="link-arrow" href="<?php echo esc_url(home_url('/kittens/')); ?>"><span>Все котята</span><?php echo zh_arrow(); ?></a></div>
      <div class="kgrid"><?php zh_render_kitten_cards($others); ?></div>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
