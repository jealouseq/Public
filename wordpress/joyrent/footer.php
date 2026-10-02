<?php if (!is_front_page()): ?><footer class="site-footer"><div class="shell native-footer-links"><a href="<?php echo esc_url(joyrent_home()); ?>">JOYRENT — <?php echo joyrent_language()==='ru'?'аренда PlayStation':'оренда PlayStation'; ?></a><div><a href="<?php echo esc_url(joyrent_faq_url(joyrent_language())); ?>">FAQ</a><a href="<?php echo esc_url(joyrent_home('booking')); ?>"><?php echo joyrent_language()==='ru'?'Оставить заявку':'Залишити заявку'; ?></a></div></div></footer><?php endif; ?>
<?php wp_footer(); ?>
</body></html>
