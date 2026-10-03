<?php get_header(); ?>
<div id="joyrent-root"></div>
<?php
$ru=joyrent_language()==='ru';
$public=class_exists('JR_Settings') ? JR_Settings::public() : [];
?>
<noscript><main class="shell" style="padding-block:80px">
<h1><?php echo esc_html($ru ? 'JOYRENT — аренда PlayStation' : 'JOYRENT — оренда PlayStation'); ?></h1>
<p style="margin-block:24px"><?php echo esc_html($ru ? 'Включи JavaScript, чтобы выбрать консоль и даты. Или свяжись с нами напрямую.' : 'Увімкни JavaScript, щоб обрати консоль і дати. Або зв’яжися з нами напряму.'); ?></p>
<div class="footer-contacts">
<?php if (!empty($public['phone'])): ?><a class="button button-outline" href="<?php echo esc_url('tel:'.preg_replace('/[^+\d]/','',$public['phone'])); ?>"><?php echo esc_html($public['phone']); ?></a><?php endif; ?>
<?php if (!empty($public['telegram'])): ?><a class="button button-outline" href="<?php echo esc_url($public['telegram']); ?>" target="_blank" rel="noopener noreferrer">Telegram</a><?php endif; ?>
</div></main></noscript>
<?php get_footer(); ?>
