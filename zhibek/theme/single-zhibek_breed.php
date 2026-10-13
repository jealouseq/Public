<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post();
  $b = get_post();
  if (post_password_required()) { ?>
    <main id="main" class="inner"><header class="page-hero" data-header="dark"><div class="wrap"><h1 class="display-l"><?php the_title(); ?></h1></div></header><div class="wrap prose-wrap"><div class="prose"><?php the_content(); ?></div></div></main>
    <?php continue;
  }
  $w = zh_breed_world($b); $m = zh_media($b); $facts = zh_breed_facts($b); $traits = zh_traits($b);
  $fit = get_post_meta($b->ID, '_zhibek_fit', true); $note = get_post_meta($b->ID, '_zhibek_note', true);
  $label = zh_content()['worlds']['labels'][$b->post_name] ?? '';
  $kittens = zh_kittens(array('meta_key' => '_zhibek_breed', 'meta_value' => $b->ID));
  $style = '--accent:' . esc_attr($w['accent']) . ';--glow:' . esc_attr($w['glow']) . ';--bg1:' . esc_attr($w['bg1']) . ';--bg2:' . esc_attr($w['bg2']) . ';--eye:' . esc_attr($w['eye']);
?>
<main id="main" class="inner">
  <section class="bhero" data-header="dark" style="<?php echo $style; ?>">
    <div class="wbg wbg-<?php echo esc_attr($w['kind']); ?>" aria-hidden="true"><span class="wbg-sky"></span><span class="wbg-orb"></span><span class="wbg-rays"></span><span class="wbg-mist"></span><span class="wbg-stars"></span></div>
    <div class="wrap bhero-inner">
      <div class="bhero-copy">
        <a class="back" href="<?php echo esc_url(home_url('/breeds/')); ?>"><?php echo zh_arrow('arr-back'); ?><span>Все породы</span></a>
        <?php if ($label) : ?><span class="wlabel"><?php echo esc_html($label); ?></span><?php endif; ?>
        <h1 class="display-xl"><?php the_title(); ?></h1>
        <p class="wsub"><?php echo esc_html(get_post_meta($b->ID, '_zhibek_subtitle', true)); ?></p>
        <?php if ($facts) : ?><dl class="wfacts"><?php foreach ($facts as $f) : ?><div><dt><?php echo esc_html($f[0]); ?></dt><dd><?php echo esc_html($f[1]); ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
        <div class="wlinks">
          <a class="btn btn-gold" href="<?php echo esc_url(add_query_arg('breed', $b->post_name, home_url('/kittens/'))); ?>" data-magnetic><span>Котята: <?php echo esc_html(zh_lower($b->post_title)); ?></span><?php echo zh_arrow(); ?></a>
          <button class="btn btn-ghost" type="button" data-enquire="<?php echo esc_attr($b->post_title); ?>" data-magnetic><span>Задать вопрос</span></button>
        </div>
      </div>
      <figure class="bhero-cat">
        <?php if ($m['url']) : ?><img src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> fetchpriority="high" decoding="async" draggable="false"><?php endif; ?>
      </figure>
    </div>
  </section>

  <section class="bbody" data-header="light">
    <div class="wrap bbody-grid">
      <div class="prose"><?php the_content(); ?></div>
      <aside class="bside">
        <?php if ($traits) : ?><div class="bside-block"><span class="label">Характер</span><ul class="traits traits-light"><?php foreach ($traits as $t) { echo '<li>' . esc_html($t) . '</li>'; } ?></ul></div><?php endif; ?>
        <?php if ($fit) : ?><div class="bside-block"><span class="label">Подойдёт, если</span><p><?php echo esc_html($fit); ?></p></div><?php endif; ?>
        <?php if ($note) : ?><div class="bside-block"><span class="label">Важно знать</span><p><?php echo esc_html($note); ?></p></div><?php endif; ?>
      </aside>
    </div>
  </section>

  <?php if ($kittens) : ?>
  <section class="collection collection-inner" data-header="light">
    <div class="wrap">
      <div class="sec-head"><div><span class="eyebrow">Котята</span><h2 class="display-m">Сейчас в питомнике</h2></div><a class="link-arrow" href="<?php echo esc_url(add_query_arg('breed', $b->post_name, home_url('/kittens/'))); ?>"><span>Все котята</span><?php echo zh_arrow(); ?></a></div>
      <div class="kgrid"><?php zh_render_kitten_cards($kittens); ?></div>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
