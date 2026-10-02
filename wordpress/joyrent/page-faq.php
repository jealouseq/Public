<?php get_header(); $ru=joyrent_language()==='ru'; ?>
<main id="main-content" class="shell faq-page">
<a class="faq-back" href="<?php echo esc_url(joyrent_home()); ?>"><?php echo $ru?'На главную':'На головну'; ?></a>
<div class="faq-page-layout"><div><p class="eyebrow">FAQ</p><h1><?php the_title(); ?></h1><p class="faq-intro"><?php echo $ru?'Что подготовить, как получить консоль и что важно знать перед арендой.':'Що підготувати, як отримати консоль і що важливо знати перед орендою.'; ?></p></div><div>
<?php while (have_posts()): the_post(); ?><div class="faq-list"><?php the_content(); ?></div><?php endwhile; ?>
<div class="faq-page-actions"><a class="button button-light" href="<?php echo esc_url(joyrent_home('booking')); ?>"><?php echo $ru?'Выбрать даты':'Обрати дати'; ?></a></div>
</div></div></main>
<?php get_footer(); ?>
