<?php
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post();
  $b = get_post();
  if (post_password_required()) { ?>
    <main id="main"><header class="page-head"><div class="wrap"><h1 class="h1"><?php the_title(); ?></h1></div></header><div class="wrap prose-wrap"><div class="prose"><?php the_content(); ?></div></div></main>
    <?php continue;
  }
  $traits = zh_traits($b);
  $fit = get_post_meta($b->ID, '_zhibek_fit', true); $note = get_post_meta($b->ID, '_zhibek_note', true);
  $kittens = zh_kittens(array('meta_key' => '_zhibek_breed', 'meta_value' => $b->ID));
?>
<main id="main">
  <div class="wrap"><?php echo zh_breed_panel($b, 0, 1, true); ?></div>

  <div class="wrap bbody">
    <div class="prose"><?php the_content(); ?></div>
    <aside class="bside" aria-label="Коротко о породе">
      <?php if ($traits) : ?><div class="bside-block"><span class="label">Характер</span><ul class="traits"><?php foreach ($traits as $t) { echo '<li>' . esc_html($t) . '</li>'; } ?></ul></div><?php endif; ?>
      <?php if ($fit) : ?><div class="bside-block"><span class="label">Подойдёт, если</span><p><?php echo zh_t($fit); ?></p></div><?php endif; ?>
      <?php if ($note) : ?><div class="bside-block"><span class="label">Важно знать</span><p><?php echo zh_t($note); ?></p></div><?php endif; ?>
    </aside>
  </div>

  <?php if ($kittens) : ?>
  <section class="inner-sec">
    <div class="wrap">
      <div class="sec-head"><div><span class="eyebrow">Котята</span><h2 class="h2">Сейчас в питомнике</h2></div><a class="link-arrow" href="<?php echo esc_url(add_query_arg('breed', $b->post_name, home_url('/kittens/'))); ?>"><span>Все котята</span><?php echo zh_arrow(); ?></a></div>
      <div class="kgrid"><?php zh_render_kitten_cards($kittens); ?></div>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php endwhile; get_footer(); ?>
