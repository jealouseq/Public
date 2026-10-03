<?php if (!is_front_page()):
    $ru=joyrent_language()==='ru';
    $public=class_exists('JR_Settings') ? JR_Settings::public() : [];
    $phone=(string)($public['phone']??'');
    $digits=preg_replace('/\D/','',$phone);
    $phone_full=$phone; $phone_short=$phone;
    if (preg_match('/^380\d{9}$/',$digits)) {
        $local='0'.substr($digits,3);
        $phone_short=substr($local,0,3).' '.substr($local,3,3).' '.substr($local,6,2).' '.substr($local,8);
        $phone_full='+38 '.$phone_short;
    }
?>
<footer class="site-footer" id="contact"><div class="shell">
    <div class="footer-top">
        <a class="wordmark" href="<?php echo esc_url(joyrent_home()); ?>" aria-label="<?php echo esc_attr($ru ? 'JOYRENT — на главную' : 'JOYRENT — на головну'); ?>">JOYRENT<span class="brand-dot">.</span></a>
        <div class="footer-contacts">
        <?php if ($phone!==''): ?><a class="footer-contact footer-phone" href="<?php echo esc_url('tel:'.preg_replace('/[^+\d]/','',$phone)); ?>" aria-label="<?php echo esc_attr(($ru ? 'Позвонить: ' : 'Зателефонувати: ').$phone_full); ?>" title="<?php echo esc_attr($phone_full); ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16.2v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 1.1 3.5 2 2 0 0 1 3.1 1.3h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.5 2.1L7.1 9.3a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.4 1.9.7 2.9.8a2 2 0 0 1 1.6 1.9Z"/></svg><span class="phone-full"><?php echo esc_html($phone_full); ?></span><span class="phone-short" aria-hidden="true"><?php echo esc_html($phone_short); ?></span></a><?php endif; ?>
        <?php if (!empty($public['telegram'])): ?><a class="footer-contact footer-telegram" href="<?php echo esc_url($public['telegram']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($ru ? 'Написать JOYRENT в Telegram' : 'Написати JOYRENT у Telegram'); ?>" title="Telegram · JOYRENT"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 3-3.6 18-6.2-6-4 3.5.8-6.1L3 10l18-7Z"/><path d="m8 12.4 9-6.2-5.8 8.8"/></svg><span>Telegram</span></a><?php endif; ?>
        <?php if (!empty($public['email'])): ?><a class="footer-contact footer-email" href="<?php echo esc_url('mailto:'.$public['email']); ?>"><?php echo esc_html($public['email']); ?></a><?php endif; ?>
        </div>
    </div>
    <div class="footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> JOYRENT</span><div><a href="<?php echo esc_url(joyrent_faq_url(joyrent_language())); ?>">FAQ</a><a href="<?php echo esc_url(joyrent_home('booking')); ?>"><?php echo esc_html($ru ? 'Оставить заявку' : 'Залишити заявку'); ?></a></div><span class="footer-note"><?php echo esc_html($ru ? 'PlayStation — торговая марка Sony.' : 'PlayStation — торгова марка Sony.'); ?></span></div>
</div></footer><?php endif; ?>
<?php wp_footer(); ?>
</body></html>
