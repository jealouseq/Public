<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$breeds = zh_breeds();
?>
<main id="main" class="inner">
  <header class="page-hero" data-header="dark">
    <div class="wrap">
      <span class="eyebrow">ZHIBEK · Породы</span>
      <h1 class="display-l">Три породы,<br><em>три характера</em></h1>
      <p class="lead">Абиссинская, бенгальская и сиамская — короткошёрстные, подвижные и привязанные к человеку. Отличаются темпом, голосом и тем, что им нужно от дома.</p>
    </div>
  </header>
  <section class="bpanels" data-header="dark">
    <?php foreach ($breeds as $i => $b) : $w = zh_breed_world($b); $m = zh_media($b); $facts = zh_breed_facts($b); $traits = zh_traits($b); $label = zh_content()['worlds']['labels'][$b->post_name] ?? ''; ?>
      <article class="bpanel" style="--accent:<?php echo esc_attr($w['accent']); ?>;--glow:<?php echo esc_attr($w['glow']); ?>;--bg1:<?php echo esc_attr($w['bg1']); ?>;--bg2:<?php echo esc_attr($w['bg2']); ?>;--eye:<?php echo esc_attr($w['eye']); ?>">
        <div class="wbg wbg-<?php echo esc_attr($w['kind']); ?>" aria-hidden="true"><span class="wbg-sky"></span><span class="wbg-orb"></span><span class="wbg-rays"></span><span class="wbg-mist"></span><span class="wbg-stars"></span></div>
        <div class="wrap bpanel-inner">
          <div class="bpanel-copy">
            <div class="wslide-top"><span class="wnum"><?php echo esc_html(sprintf('%02d', $i + 1)); ?><i>/</i><?php echo esc_html(sprintf('%02d', count($breeds))); ?></span><?php if ($label) : ?><span class="wlabel"><?php echo esc_html($label); ?></span><?php endif; ?></div>
            <h2 class="display-l"><a href="<?php echo esc_url(get_permalink($b)); ?>"><?php echo esc_html($b->post_title); ?></a></h2>
            <p class="wsub"><?php echo esc_html(get_post_meta($b->ID, '_zhibek_subtitle', true)); ?></p>
            <?php if ($facts) : ?><dl class="wfacts"><?php foreach ($facts as $f) : ?><div><dt><?php echo esc_html($f[0]); ?></dt><dd><?php echo esc_html($f[1]); ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
            <?php if ($traits) : ?><ul class="traits"><?php foreach ($traits as $t) { echo '<li>' . esc_html($t) . '</li>'; } ?></ul><?php endif; ?>
            <div class="wlinks">
              <a class="btn btn-gold" href="<?php echo esc_url(get_permalink($b)); ?>" data-magnetic><span>О породе</span><?php echo zh_arrow(); ?></a>
              <a class="link-arrow" href="<?php echo esc_url(add_query_arg('breed', $b->post_name, home_url('/kittens/'))); ?>"><span>Котята</span><?php echo zh_arrow(); ?></a>
            </div>
          </div>
          <a class="bpanel-cat" href="<?php echo esc_url(get_permalink($b)); ?>" data-cursor="Открыть" aria-label="<?php echo esc_attr($b->post_title); ?>">
            <?php if ($m['url']) : ?><img src="<?php echo esc_url($m['url']); ?>" alt="<?php echo esc_attr($b->post_title . ' кошка'); ?>"<?php if ($m['w']) : ?> width="<?php echo (int) $m['w']; ?>" height="<?php echo (int) $m['h']; ?>"<?php endif; ?> loading="lazy" decoding="async" draggable="false"><?php endif; ?>
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</main>
<?php get_footer(); ?>
