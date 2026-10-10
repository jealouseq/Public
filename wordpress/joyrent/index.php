<?php get_header(); $ru=joyrent_language()==='ru'; ?>
<main id="main-content" class="shell wp-page" style="padding-block:70px;min-height:65vh">
<?php if (is_search()): ?><h1><?php echo esc_html(($ru?'Результаты поиска: ':'Результати пошуку: ').get_search_query(false)); ?></h1><?php elseif (!is_singular()&&!is_404()&&have_posts()): the_archive_title('<h1>','</h1>'); endif; ?>
<?php if (have_posts()): ?>
<?php while (have_posts()): the_post(); ?>
<article <?php post_class(); ?>>
<?php if (is_singular()): ?><h1 style="font-family:var(--service);font-size:clamp(24px,4vw,48px);font-weight:550;line-height:1.15;letter-spacing:-.025em;overflow-wrap:anywhere;margin-bottom:35px"><?php the_title(); ?></h1>
<?php else: ?><h2 style="font-family:var(--service);font-size:clamp(24px,4vw,48px);font-weight:550;line-height:1.15;letter-spacing:-.025em;overflow-wrap:anywhere;margin-bottom:35px"><a href="<?php echo esc_url(get_permalink(get_the_ID())); ?>"><?php the_title(); ?></a></h2><?php endif; ?>
<div class="wp-content" style="overflow-wrap:anywhere"><?php the_content(); ?></div></article>
<?php endwhile; ?>
<?php if (!is_singular()) the_posts_pagination(['prev_text'=>'Назад','next_text'=>$ru?'Далее':'Далі']); ?>
<?php else:
    $empty_title=is_404()?($ru?'Страница не найдена':'Сторінку не знайдено'):($ru?'Ничего не найдено':'Нічого не знайдено');
    $empty_text=is_search()?($ru?'Попробуй другой запрос или вернись на главную.':'Спробуй інший запит або повернися на головну.'):($ru?'Проверь адрес или вернись на главную.':'Перевір адресу або повернися на головну.');
?>
<?php if (is_search()): ?><h2><?php echo esc_html($empty_title); ?></h2><?php else: ?><h1><?php echo esc_html($empty_title); ?></h1><?php endif; ?>
<p style="margin-block:24px"><?php echo esc_html($empty_text); ?></p>
<a class="button button-outline" href="<?php echo esc_url(joyrent_home()); ?>"><?php echo esc_html($ru?'На главную':'На головну'); ?></a>
<?php endif; ?>
</main>
<?php get_footer(); ?>
