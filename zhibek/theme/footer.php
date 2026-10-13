<?php if (!defined('ABSPATH')) { exit; }
$s = zh_settings();
$contacts = zh_contacts();
$c = zh_content();
$show_cta = !is_front_page() && !(function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page()));
?>
<?php if ($show_cta) : ?>
<section class="cta-band" data-header="dark">
  <div class="wrap cta-band-inner">
    <div>
      <span class="eyebrow">ZHIBEK · Контакты</span>
      <h2 class="display-m">Расскажем о котятах<br><em>и ближайших помётах</em></h2>
    </div>
    <div class="cta-band-actions">
      <button class="btn btn-gold" type="button" data-enquire="Консультация" data-magnetic><span>Написать нам</span><?php echo zh_arrow(); ?></button>
      <?php foreach (array('whatsapp', 'telegram') as $k) : if (isset($contacts[$k])) : ?>
        <a class="btn btn-ghost" href="<?php echo esc_url($contacts[$k][1]); ?>" target="_blank" rel="noopener"><?php echo zh_icon($k); ?><span><?php echo esc_html($contacts[$k][0]); ?></span></a>
      <?php endif; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<footer class="site-footer" data-header="dark">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a class="brand brand-lg" href="<?php echo esc_url(home_url('/')); ?>" aria-label="ZHIBEK — на главную"><?php echo zh_brand(); ?></a>
      <p><?php echo esc_html($c['footer']['tagline']); ?></p>
    </div>
    <nav class="footer-col" aria-label="Питомник">
      <span class="label">Питомник</span>
      <a href="<?php echo esc_url(home_url('/breeds/')); ?>">Породы</a>
      <a href="<?php echo esc_url(home_url('/kittens/')); ?>">Котята</a>
      <a href="<?php echo esc_url(home_url('/#process')); ?>">Как это происходит</a>
      <a href="<?php echo esc_url(home_url('/#journal')); ?>">Журнал</a>
    </nav>
    <nav class="footer-col" aria-label="Магазин">
      <span class="label">Магазин</span>
      <a href="<?php echo esc_url(zh_wc_url('shop')); ?>">Вещи для дома</a>
      <a href="<?php echo esc_url(zh_wc_url('myaccount')); ?>">Личный кабинет</a>
      <a href="<?php echo esc_url(home_url('/order-terms/')); ?>">Доставка и оплата</a>
    </nav>
    <div class="footer-col">
      <span class="label">Связаться</span>
      <?php foreach ($contacts as $key => $ct) : ?>
        <a href="<?php echo esc_url($ct[1]); ?>" <?php echo in_array($key, array('whatsapp', 'telegram', 'instagram'), true) ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($ct[0]); ?></a>
      <?php endforeach; ?>
      <button class="link-btn" type="button" data-enquire="Вопрос питомнику">Написать через форму</button>
      <span class="footer-city"><?php echo esc_html($s['city']); ?></span>
    </div>
  </div>
  <div class="footer-mega" aria-hidden="true"><?php echo zh_wordmark('wm wm-mega'); ?></div>
  <div class="wrap footer-bottom">
    <span>© <?php echo esc_html(wp_date('Y')); ?> ZHIBEK</span>
    <a href="<?php echo esc_url(zh_privacy_url()); ?>">Конфиденциальность</a>
    <button class="motion-toggle" type="button" aria-pressed="false" data-motion-toggle>Анимации: включены</button>
  </div>
  <?php if (zh_is_demo()) : ?><p class="wrap demo-note">Демо-режим: котята, родословные и товары на сайте — примеры. Отключается в Настройки → ZHIBEK.</p><?php endif; ?>
</footer>

<dialog id="enquiry" class="enquiry" aria-label="Заявка питомнику">
  <img class="enquiry-peek" src="<?php echo esc_url(zh_img('cats/peek-bengal.webp')); ?>" alt="" width="761" height="762" loading="lazy" decoding="async" aria-hidden="true">
  <button class="enquiry-close" type="button" data-close aria-label="Закрыть форму"><?php echo zh_icon('close'); ?></button>
  <div class="enquiry-body">
    <span class="eyebrow">ZHIBEK · Заявка</span>
    <h2 class="display-s">Напишите нам</h2>
    <p class="enquiry-lead">Оставьте контакт — ответим в течение дня. Заявка ни к чему вас не обязывает.</p>
    <form id="enquiry-form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" novalidate>
      <input type="hidden" name="action" value="zhibek_enquiry">
      <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('zh_enquiry')); ?>">
      <div class="field"><input id="f-name" name="name" required maxlength="100" autocomplete="name" placeholder=" "><label for="f-name">Ваше имя</label></div>
      <div class="field-row">
        <div class="field"><input id="f-contact" name="contact" required maxlength="120" autocomplete="tel" inputmode="email" placeholder=" "><label for="f-contact">Телефон или email</label></div>
        <div class="field"><input id="f-city" name="city" required maxlength="100" autocomplete="address-level2" placeholder=" "><label for="f-city">Город</label></div>
      </div>
      <div class="field field-select"><select id="f-breed" name="breed"><option>Консультация</option><?php foreach (zh_breeds() as $b) { echo '<option>' . esc_html($b->post_title) . '</option>'; } ?><option>Ближайшие помёты</option></select><label for="f-breed">Что вас интересует</label></div>
      <div class="field"><textarea id="f-message" name="message" rows="3" maxlength="2000" placeholder=" "></textarea><label for="f-message">Расскажите о доме и пожеланиях</label></div>
      <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
      <label class="consent"><input type="checkbox" name="consent" value="1" required><span>Согласен на <a href="<?php echo esc_url(zh_privacy_url()); ?>" target="_blank" rel="noopener">обработку данных</a> для ответа на заявку</span></label>
      <button type="submit" class="btn btn-gold btn-block"><span>Отправить</span><?php echo zh_arrow(); ?></button>
      <p id="form-result" class="form-result" role="status" aria-live="polite"></p>
    </form>
    <?php if (isset($contacts['whatsapp']) || isset($contacts['telegram'])) : ?>
      <p class="enquiry-alt">Или сразу в мессенджер:
        <?php foreach (array('whatsapp', 'telegram') as $k) { if (isset($contacts[$k])) { echo '<a href="' . esc_url($contacts[$k][1]) . '" target="_blank" rel="noopener">' . esc_html($contacts[$k][0]) . '</a> '; } } ?>
      </p>
    <?php endif; ?>
  </div>
</dialog>
<div class="toast" role="status" aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>
