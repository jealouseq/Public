<?php
/** Фотографии животных: админ-блок галереи и публичный вывод. */
defined('ABSPATH') || exit;

function zh_gallery_sanitize_ids($value) {
    if (!is_array($value)) { return array(); }
    $ids = array();
    foreach (array_slice($value, 0, 40) as $candidate) {
        if ((!is_int($candidate) && !is_string($candidate)) || !preg_match('/^[1-9][0-9]*$/D', (string) $candidate)) { continue; }
        $id = (int) $candidate;
        if (get_post_type($id) === 'attachment' && wp_attachment_is_image($id)) { $ids[] = $id; }
    }
    return array_values(array_unique($ids));
}

function zh_gallery_video_sanitize_id($value) {
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]+$/D', (string) $value)) { return 0; }
    $id = (int) $value;
    return $id && get_post_type($id) === 'attachment' && in_array(get_post_mime_type($id), array('video/mp4', 'video/webm', 'video/ogg'), true) ? $id : 0;
}

add_action('init', function () {
    foreach (array('zhibek_breed', 'zhibek_cat') as $type) {
        register_post_meta($type, '_zhibek_gallery_ids', array(
            'type' => 'array', 'single' => true, 'default' => array(),
            'show_in_rest' => array('schema' => array('type' => 'array', 'items' => array('type' => 'integer'), 'maxItems' => 40)),
            'sanitize_callback' => 'zh_gallery_sanitize_ids',
            'auth_callback' => function ($allowed, $key, $post_id) { return current_user_can('edit_post', $post_id); },
        ));
        register_post_meta($type, '_zhibek_video_id', array(
            'type' => 'integer', 'single' => true, 'default' => 0, 'show_in_rest' => true,
            'sanitize_callback' => 'zh_gallery_video_sanitize_id',
            'auth_callback' => function ($allowed, $key, $post_id) { return current_user_can('edit_post', $post_id); },
        ));
    }
}, 20);

add_action('add_meta_boxes', function () {
    foreach (array('zhibek_breed', 'zhibek_cat') as $type) {
        add_meta_box('zhibek-photo-gallery', 'Фотогалерея', 'zh_gallery_metabox', $type, 'normal', 'default');
    }
});

function zh_gallery_metabox($post) {
    $ids = zh_gallery_sanitize_ids(get_post_meta($post->ID, '_zhibek_gallery_ids', true));
    wp_nonce_field('zhibek_save_gallery', 'zhibek_gallery_nonce');
    echo '<p>Основная фотография добавляется первой. Здесь можно выбрать дополнительные фотографии из медиатеки и изменить их порядок. До 40 снимков.</p>';
    echo '<input type="hidden" id="zhibek-gallery-ids" name="zhibek_gallery_ids" value="' . esc_attr(implode(',', $ids)) . '">';
    echo '<button type="button" class="button" id="zhibek-gallery-select">Выбрать фотографии</button>';
    echo '<ul id="zhibek-gallery-admin" class="zhibek-gallery-admin">';
    foreach ($ids as $id) {
        echo '<li data-id="' . esc_attr($id) . '">' . wp_get_attachment_image($id, 'thumbnail', false, array('alt' => '')) . '<span>' . esc_html(get_the_title($id)) . '</span>';
        echo '<div><button type="button" class="button" data-gallery-move="-1" aria-label="Переместить фотографию влево">←</button> <button type="button" class="button" data-gallery-move="1" aria-label="Переместить фотографию вправо">→</button> <button type="button" class="button" data-gallery-remove aria-label="Удалить фотографию из галереи">Убрать</button></div></li>';
    }
    echo '</ul><p class="description">Используйте собственные фотографии животных. Иллюстрации демо-каталога автоматически исчезнут после добавления основной фотографии.</p>';
    $video = zh_gallery_video_sanitize_id(get_post_meta($post->ID, '_zhibek_video_id', true));
    echo '<hr><h4>Видео животного — необязательно</h4><p>Загрузите собственный короткий ролик в формате MP4, WebM или OGG. Видео не будет запускаться со звуком автоматически.</p><input type="hidden" id="zhibek-gallery-video" name="zhibek_video_id" value="' . esc_attr($video) . '">';
    echo '<p id="zhibek-video-name">' . esc_html($video ? get_the_title($video) : 'Видео не выбрано') . '</p><button type="button" class="button" id="zhibek-video-select">Выбрать видео</button> <button type="button" class="button" id="zhibek-video-remove">Убрать видео</button>';
}

function zh_gallery_save($post_id, $post) {
    if (!in_array($post->post_type, array('zhibek_breed', 'zhibek_cat'), true) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
    if (!isset($_POST['zhibek_gallery_nonce']) || !is_string($_POST['zhibek_gallery_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zhibek_gallery_nonce'])), 'zhibek_save_gallery') || !current_user_can('edit_post', $post_id)) { return; }
    if (isset($_POST['zhibek_gallery_ids']) && is_string($_POST['zhibek_gallery_ids'])) {
        $value = wp_unslash($_POST['zhibek_gallery_ids']);
        update_post_meta($post_id, '_zhibek_gallery_ids', zh_gallery_sanitize_ids($value === '' ? array() : explode(',', $value)));
    }
    if (isset($_POST['zhibek_video_id']) && is_string($_POST['zhibek_video_id'])) {
        update_post_meta($post_id, '_zhibek_video_id', zh_gallery_video_sanitize_id(wp_unslash($_POST['zhibek_video_id'])));
    }
}
add_action('save_post', 'zh_gallery_save', 20, 2);

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'), true)) { return; }
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, array('zhibek_breed', 'zhibek_cat'), true)) { return; }
    wp_enqueue_media();
    wp_add_inline_style('common', '.zhibek-gallery-admin{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0}.zhibek-gallery-admin li{width:170px;padding:10px;border:1px solid #dcdcde;background:#fff}.zhibek-gallery-admin img{display:block;width:100%;height:130px;object-fit:cover}.zhibek-gallery-admin span{display:block;overflow-wrap:anywhere;margin:8px 0}.zhibek-gallery-admin button{margin:0 0 4px}');
    wp_add_inline_script('media-editor', <<<'JS'
(() => {
  const start = () => {
    const list = document.getElementById('zhibek-gallery-admin');
    const input = document.getElementById('zhibek-gallery-ids');
    const trigger = document.getElementById('zhibek-gallery-select');
    if (!list || !input || !trigger || !window.wp?.media) return;
    const sync = () => {
      const rows = [...list.children]; input.value = rows.map(row => row.dataset.id).join(',');
      rows.forEach((row, index) => { row.querySelector('[data-gallery-move="-1"]').disabled = index === 0; row.querySelector('[data-gallery-move="1"]').disabled = index === rows.length - 1; });
    };
    const rowFor = item => {
      const row = document.createElement('li'); row.dataset.id = item.id;
      const image = document.createElement('img'); image.alt = ''; image.src = item.sizes?.thumbnail?.url || item.url;
      const name = document.createElement('span'); name.textContent = item.title || 'Фотография';
      const controls = document.createElement('div');
      [['←', 'data-gallery-move', '-1', 'Переместить фотографию влево'], ['→', 'data-gallery-move', '1', 'Переместить фотографию вправо'], ['Убрать', 'data-gallery-remove', '', 'Удалить фотографию из галереи']].forEach(([text, attr, value, label]) => {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.textContent = text; button.setAttribute(attr, value); button.setAttribute('aria-label', label); controls.append(button, document.createTextNode(' '));
      });
      row.append(image, name, controls); return row;
    };
    list.addEventListener('click', event => {
      const button = event.target.closest('button'); if (!button) return;
      const row = button.closest('li');
      if (button.hasAttribute('data-gallery-remove')) { row.remove(); trigger.focus(); }
      else if (button.dataset.galleryMove === '-1' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      else if (button.dataset.galleryMove === '1' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
      sync();
      if (button.disabled) row.querySelector('[data-gallery-remove]')?.focus();
    });
    let frame;
    trigger.addEventListener('click', () => {
      if (!frame) {
        frame = wp.media({title: 'Фотографии галереи', button: {text: 'Добавить в галерею'}, library: {type: 'image'}, multiple: true});
        frame.on('open', () => frame.state().get('selection').reset());
        frame.on('select', () => {
          const selected = frame.state().get('selection').toJSON();
          selected.forEach(item => { if (list.children.length < 40 && !list.querySelector(`[data-id="${Number(item.id)}"]`)) list.append(rowFor(item)); }); sync();
        });
      }
      frame.open();
    });
    sync();
    const videoInput = document.getElementById('zhibek-gallery-video');
    const videoName = document.getElementById('zhibek-video-name');
    const videoTrigger = document.getElementById('zhibek-video-select');
    const videoRemove = document.getElementById('zhibek-video-remove');
    let videoFrame;
    videoTrigger?.addEventListener('click', () => {
      if (!videoFrame) {
        videoFrame = wp.media({title: 'Видео животного', button: {text: 'Добавить видео'}, library: {type: 'video'}, multiple: false});
        videoFrame.on('select', () => {
          const selected = videoFrame.state().get('selection').first()?.toJSON();
          if (!selected || !['video/mp4', 'video/webm', 'video/ogg'].includes(selected.mime)) { videoName.textContent = 'Выберите видео в формате MP4, WebM или OGG.'; return; }
          videoInput.value = selected.id; videoName.textContent = selected.title || 'Видео';
        });
      }
      videoFrame.open();
    });
    videoRemove?.addEventListener('click', () => { videoInput.value = '0'; videoName.textContent = 'Видео не выбрано'; });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true}); else start();
})();
JS
    );
});

/** Фото из закрытых или защищённых паролем записей публично не показываем. */
function zh_gallery_attachment_is_public($id) {
    $attachment = get_post($id);
    if (!$attachment || $attachment->post_type !== 'attachment' || $attachment->post_password || !in_array($attachment->post_status, array('inherit', 'publish'), true) || !wp_attachment_is_image($id)) { return false; }
    if ($attachment->post_parent) {
        $parent = get_post($attachment->post_parent);
        if (!$parent || $parent->post_status !== 'publish' || $parent->post_password || !is_post_publicly_viewable($parent)) { return false; }
    }
    return true;
}

function zh_gallery_video($post) {
    $post = get_post($post);
    if (!$post || !in_array($post->post_type, array('zhibek_breed', 'zhibek_cat'), true) || $post->post_status !== 'publish' || $post->post_password) { return null; }
    $id = zh_gallery_video_sanitize_id(get_post_meta($post->ID, '_zhibek_video_id', true));
    $attachment = $id ? get_post($id) : null;
    if (!$attachment || $attachment->post_password || !in_array($attachment->post_status, array('inherit', 'publish'), true)) { return null; }
    if ($attachment->post_parent) {
        $parent = get_post($attachment->post_parent);
        if (!$parent || $parent->post_status !== 'publish' || $parent->post_password || !is_post_publicly_viewable($parent)) { return null; }
    }
    $source = wp_get_attachment_url($id);
    if (!$source) { return null; }
    $featured = get_post_thumbnail_id($post);
    return array('id' => $id, 'src' => $source, 'mime' => get_post_mime_type($id), 'poster' => $featured && zh_gallery_attachment_is_public($featured) ? wp_get_attachment_image_url($featured, 'large') : '');
}

/** Снимки записи: основная фотография + галерея. Если ничего не загружено, берётся вырезка из темы. */
function zh_gallery_items($post) {
    $post = get_post($post);
    if (!$post || !in_array($post->post_type, array('zhibek_breed', 'zhibek_cat'), true) || $post->post_status !== 'publish' || $post->post_password) { return array(); }
    $ids = zh_gallery_sanitize_ids(get_post_meta($post->ID, '_zhibek_gallery_ids', true));
    $featured = get_post_thumbnail_id($post);
    if ($featured) { array_unshift($ids, $featured); }
    $items = array();
    foreach (array_unique($ids) as $id) {
        if (!zh_gallery_attachment_is_public($id)) { continue; }
        $full = wp_get_attachment_image_src($id, 'full');
        $preview = wp_get_attachment_image_src($id, 'large');
        $thumb = wp_get_attachment_image_src($id, 'thumbnail');
        if (!$full) { continue; }
        $items[] = array(
            'src' => $full[0], 'preview' => $preview ? $preview[0] : $full[0], 'thumb' => $thumb ? $thumb[0] : $full[0],
            'alt' => get_post_meta($id, '_wp_attachment_image_alt', true) ?: $post->post_title, 'caption' => wp_strip_all_tags(wp_get_attachment_caption($id)),
            'w' => (int) $full[1], 'h' => (int) $full[2], 'cutout' => $id === (int) $featured && (bool) get_post_meta($post->ID, '_zhibek_cutout', true),
        );
    }
    if ($items) { return $items; }
    $m = zh_media($post);
    if ($m['url']) {
        return array(array('src' => $m['url'], 'preview' => $m['url'], 'thumb' => $m['url'], 'alt' => $post->post_title, 'caption' => '', 'w' => (int) $m['w'], 'h' => (int) $m['h'], 'cutout' => true));
    }
    return array();
}

/** Галерея: большой кадр, миниатюры, полноэкранный просмотр (site.js). */
function zh_render_gallery($post) {
    $record = get_post($post);
    $items = zh_gallery_items($record);
    if (!$items) { return false; }
    echo '<div class="zg" data-gallery><a class="zg-main" href="' . esc_url($items[0]['src']) . '" aria-label="Открыть фотографию на весь экран" data-type="' . ($items[0]['cutout'] ? 'cutout' : 'photo') . '">';
    echo '<img src="' . esc_url($items[0]['preview']) . '" alt="' . esc_attr($items[0]['alt']) . '" width="' . (int) $items[0]['w'] . '" height="' . (int) $items[0]['h'] . '" decoding="async" fetchpriority="high"></a>';
    if (count($items) > 1) {
        echo '<div class="zg-thumbs" role="group" aria-label="Фотографии">';
        foreach ($items as $i => $item) {
            echo '<button type="button" data-zg-thumb="' . (int) $i . '" aria-pressed="' . ($i === 0 ? 'true' : 'false') . '" aria-label="Фото ' . ($i + 1) . '"><img src="' . esc_url($item['thumb']) . '" alt="" loading="lazy" decoding="async"></button>';
        }
        echo '</div>';
    }
    echo '<script type="application/json" class="zg-data">' . wp_json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . '</script></div>';
    return true;
}
