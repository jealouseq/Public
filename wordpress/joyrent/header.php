<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php if (!is_front_page()): ?>
<header class="site-header"><div class="shell header-inner"><a class="wordmark" href="<?php echo esc_url(joyrent_home()); ?>">JOYRENT<span class="brand-dot">.</span></a><div class="native-header-actions"><?php if (is_page(['faq','faq-ru'])): ?><nav class="native-language-switch" aria-label="<?php echo joyrent_language()==='ru'?'Язык сайта':'Мова сайту'; ?>"><?php foreach (['uk'=>'UA','ru'=>'RU'] as $language=>$label): $url=joyrent_public_page_url('faq',$language); if (!$url) continue; ?><a lang="<?php echo esc_attr($language); ?>" href="<?php echo esc_url($url); ?>" <?php if (joyrent_language()===$language) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a><?php endforeach; ?></nav><?php endif; ?><a class="button button-outline" href="<?php echo esc_url(joyrent_home('booking')); ?>"><?php echo joyrent_language()==='ru'?'Выбрать даты':'Обрати дати'; ?></a></div></div></header>
<?php endif; ?>
