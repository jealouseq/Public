<?php
if (!defined('ABSPATH')) { exit; }
get_header();
$breeds = zh_breeds();
$c = zh_content();
?>
<main id="main" class="page-breeds">
  <header class="page-head">
    <div class="wrap">
      <span class="eyebrow">Питомник</span>
      <h1 class="h1">Породы</h1>
      <p class="lead"><?php echo zh_t($c['breeds']['lead']); ?></p>
    </div>
  </header>
  <div class="wrap">
    <div class="stack"><?php foreach ($breeds as $i => $b) { echo zh_breed_panel($b, $i, count($breeds), false, 'h2'); } ?></div>
  </div>
</main>
<?php get_footer(); ?>
