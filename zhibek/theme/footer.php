<?php if (!defined('ABSPATH')) { exit; }
$s = zh_settings();
$contacts = zh_contacts();
$c = zh_content();
$show_cta = !is_front_page() && !(function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page()));
$peek = zh_has_img('pets/peek-2.webp') ? zh_img('pets/peek-2.webp') : '';
$peek_dlg = zh_has_img('pets/peek.webp') ? zh_img('pets/peek.webp') : '';
?>
<?php if ($show_cta) : ?>
<section class="cta-final" aria-label="Связаться с питомником">
  <div class="wrap">
    <div class="cta-card" data-reveal>
      <?php if ($peek) : ?><img class="cta-peek" src="<?php echo esc_url($peek); ?>" alt="" width="1100" height="733" loading="lazy" decoding="async" draggable="false"><?php endif; ?>
      <div class="cta-copy">
        <h2><?php echo wp_kses($c['finale']['title'], array('em' => array())); ?></h2>
        <p><?php echo zh_t($c['finale']['text']); ?></p>
        <div class="cta-actions">
          <button class="btn btn-light" type="button" data-enquire="Консультация"><span>Написать нам</span><?php echo zh_arrow(); ?></button>
          <?php foreach (array('whatsapp', 'telegram') as $k) : if (isset($contacts[$k])) : ?>
            <a class="btn btn-line-light" href="<?php echo esc_url($contacts[$k][1]); ?>" target="_blank" rel="noopener"><span><?php echo esc_html($contacts[$k][0]); ?></span></a>
          <?php endif; endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo zh_brand(); ?></a>
      <p><?php echo esc_html($c['footer']['tagline']); ?></p>
    </div>
    <nav class="footer-col" aria-label="Питомник">
      <span class="label">Питомник</span>
      <a href="<?php echo esc_url(home_url('/breeds/')); ?>">Породы</a>
      <a href="<?php echo esc_url(home_url('/cats/')); ?>">Коты</a>
      <a href="<?php echo esc_url(home_url('/#process')); ?>">Как забрать</a>
      <a href="<?php echo esc_url(home_url('/#faq')); ?>">Вопросы</a>
      <a href="<?php echo esc_url(home_url('/#journal')); ?>">Журнал</a>
    </nav>
    <?php $terms_url = zh_page_url('order-terms'); $has_shop = class_exists('WooCommerce'); if ($has_shop || $terms_url) : ?>
    <nav class="footer-col" aria-label="Магазин и условия">
      <span class="label"><?php echo $has_shop ? 'Магазин' : 'Условия'; ?></span>
      <?php if ($has_shop) : ?>
        <a href="<?php echo esc_url(zh_wc_url('shop')); ?>">Вещи для дома</a>
        <a href="<?php echo esc_url(zh_wc_url('myaccount')); ?>">Личный кабинет</a>
      <?php endif; ?>
      <?php if ($terms_url) : ?><a href="<?php echo esc_url($terms_url); ?>">Доставка и оплата</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <div class="footer-col">
      <span class="label">Связаться</span>
      <?php foreach ($contacts as $key => $ct) : ?>
        <a href="<?php echo esc_url($ct[1]); ?>" <?php echo in_array($key, array('whatsapp', 'telegram', 'instagram'), true) ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($ct[0]); ?></a>
      <?php endforeach; ?>
      <button class="link-btn" type="button" data-enquire="Вопрос питомнику">Написать нам</button>
      <span class="muted"><?php echo esc_html($s['city']); ?></span>
    </div>
  </div>
  <?php if (zh_is_demo()) : ?><p class="wrap demo-note">Сайт работает в демонстрационном режиме: иллюстрации и товары — примеры.<?php if (current_user_can('manage_options')) : ?> Выключить: Настройки → ZHIBEK.<?php endif; ?></p><?php endif; ?>
  <div class="wrap footer-bottom">
    <span>© <?php echo esc_html(wp_date('Y')); ?> ZHIBEK</span>
    <?php if (zh_privacy_url()) : ?><a href="<?php echo esc_url(zh_privacy_url()); ?>">Конфиденциальность</a><?php endif; ?>
  </div>
  <div class="footer-mega" aria-hidden="true"><?php echo zh_wordmark('wm'); ?></div>
</footer>

<dialog id="enquiry" class="enquiry" aria-labelledby="enquiry-title">
  <?php if ($peek_dlg) : ?><img class="enquiry-peek" src="<?php echo esc_url($peek_dlg); ?>" alt="" width="1100" height="722" loading="lazy" decoding="async" draggable="false"><?php endif; ?>
  <div class="enquiry-card">
    <button class="enquiry-close" type="button" data-close aria-label="Закрыть форму"><?php echo zh_icon('close'); ?></button>
    <div class="enquiry-body">
      <h2 id="enquiry-title">Напишите <em>нам</em></h2>
      <p class="enquiry-lead">Оставьте контакт, и мы ответим. Заявка ни к чему вас не обязывает.</p>
      <form id="enquiry-form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" novalidate>
        <input type="hidden" name="action" value="zhibek_enquiry">
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('zh_enquiry')); ?>">
        <div class="field"><label for="f-name">Имя</label><input id="f-name" name="name" required maxlength="100" autocomplete="name"></div>
        <div class="field-row">
          <div class="field"><label for="f-contact">Телефон или email</label><input id="f-contact" name="contact" required maxlength="120" autocomplete="tel" inputmode="email"></div>
          <div class="field"><label for="f-city">Город</label><input id="f-city" name="city" required maxlength="100" autocomplete="address-level2"></div>
        </div>
        <div class="field"><label for="f-breed">Что вас интересует</label><select id="f-breed" name="breed"><option>Консультация</option><?php foreach (zh_breeds() as $b) { echo '<option>' . esc_html($b->post_title) . '</option>'; } ?><option>Ближайшие помёты</option></select></div>
        <div class="field"><label for="f-message">Сообщение</label><textarea id="f-message" name="message" rows="3" maxlength="2000" placeholder="Расскажите о доме и пожеланиях"></textarea></div>
        <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="consent"><input type="checkbox" name="consent" value="1" required><span>Согласен на <?php if (zh_privacy_url()) : ?><a href="<?php echo esc_url(zh_privacy_url()); ?>" target="_blank" rel="noopener">обработку данных</a><?php else : ?>обработку данных<?php endif; ?> для ответа на заявку</span></label>
        <button type="submit" class="btn btn-primary btn-block"><span>Отправить</span><?php echo zh_arrow(); ?></button>
        <p id="form-result" class="form-result" role="status" aria-live="polite"></p>
      </form>
      <div class="sent" role="status"><h2>Заявка <em>отправлена</em></h2><p class="enquiry-lead" id="sent-text">Мы свяжемся с вами по указанному контакту.</p><button class="btn btn-outline" type="button" data-close><span>Закрыть</span></button></div>
      <?php if (isset($contacts['whatsapp']) || isset($contacts['telegram'])) : ?>
        <p class="enquiry-alt">Или сразу в мессенджер:
          <?php foreach (array('whatsapp', 'telegram') as $k) { if (isset($contacts[$k])) { echo '<a href="' . esc_url($contacts[$k][1]) . '" target="_blank" rel="noopener">' . esc_html($contacts[$k][0]) . '</a>'; } } ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</dialog>
<div class="toast" role="status" aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>
