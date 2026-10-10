<?php
if (!defined('ABSPATH')) exit;

/** The landing and the six native reading pages share JOYRENT metadata/assets. */
function joyrent_public_page(): string {
    if (is_front_page()) return 'home';
    foreach (['faq'=>['faq','faq-ru'], 'terms'=>['umovy-orendy','usloviya-arendy'], 'privacy'=>['konfidentsiinist','konfidentsialnost']] as $kind=>$slugs) {
        if (is_page($slugs)) return $kind;
    }
    return '';
}

function joyrent_public_page_url(string $kind, string $language): string {
    if ($kind==='home') return $language==='ru' ? add_query_arg('lang','ru',home_url('/')) : home_url('/');
    $slugs=['faq'=>['uk'=>'faq','ru'=>'faq-ru'], 'terms'=>['uk'=>'umovy-orendy','ru'=>'usloviya-arendy'], 'privacy'=>['uk'=>'konfidentsiinist','ru'=>'konfidentsialnost']];
    $slug=$slugs[$kind][$language] ?? '';
    $page=$slug ? get_page_by_path($slug) : null;
    return $page&&$page->post_status==='publish' ? get_permalink($page) : '';
}

/** Shared by the server head and the client's language switch. */
function joyrent_home_metadata(): array {
    $metadata=[
        'uk'=>['title'=>'JOYRENT — оренда PlayStation 5 та PlayStation 4 в Одесі','description'=>'Оренда PS5 та PS4 в Одесі від JOYRENT. Обирайте консоль, дати та ігри; доставку й наявність підтвердимо перед орендою.'],
        'ru'=>['title'=>'JOYRENT — аренда PlayStation 5 и PlayStation 4 в Одессе','description'=>'Аренда PS5 и PS4 в Одессе от JOYRENT. Выбирайте консоль, даты и игры; доставку и наличие подтвердим перед арендой.'],
    ];
    foreach ($metadata as $language=>&$entry) {
        $entry['url']=joyrent_public_page_url('home',$language);
        $entry['locale']=$language==='ru'?'ru_UA':'uk_UA';
    }
    unset($entry);
    return $metadata;
}

function joyrent_page_metadata(): array {
    $kind=joyrent_public_page();
    if (!$kind) return [];
    $language=joyrent_language();
    if ($kind==='home') return ['kind'=>$kind,'language'=>$language]+joyrent_home_metadata()[$language];
    $copy=[
        'faq'=>[
            'uk'=>['Питання про оренду PlayStation — JOYRENT','Відповіді JOYRENT про оренду PS5 та PS4: бронювання, комплект, ігри, доставка та оформлення із заставою або за договором.'],
            'ru'=>['Вопросы об аренде PlayStation — JOYRENT','Ответы JOYRENT об аренде PS5 и PS4: бронь, комплект, игры, доставка и оформление с залогом или по договору.'],
        ],
        'terms'=>[
            'uk'=>['Умови оренди PlayStation — JOYRENT','Умови оренди PS5 та PS4 у JOYRENT: підтвердження бронювання, доставка, застава або договір, користування та повернення консолі.'],
            'ru'=>['Условия аренды PlayStation — JOYRENT','Условия аренды PS5 и PS4 в JOYRENT: подтверждение брони, доставка, залог или договор, использование и возврат консоли.'],
        ],
        'privacy'=>[
            'uk'=>['Конфіденційність — JOYRENT','Як JOYRENT обробляє контактні дані з форми бронювання, для чого їх використовує та як звернутися щодо своїх даних.'],
            'ru'=>['Конфиденциальность — JOYRENT','Как JOYRENT обрабатывает контактные данные из формы брони, для чего их использует и как обратиться по поводу своих данных.'],
        ],
    ];
    [$title,$description]=$copy[$kind][$language];
    $url=$kind==='home' ? joyrent_public_page_url($kind,$language) : get_permalink(get_queried_object_id());
    return compact('kind','language','title','description','url');
}

add_filter('document_title_parts', function (array $parts): array {
    $metadata=joyrent_page_metadata();
    return $metadata ? ['title'=>$metadata['title']] : $parts;
});

// Emit one canonical, including the RU landing's language query parameter.
add_action('wp', function (): void {
    if (joyrent_public_page()) remove_action('wp_head','rel_canonical');
});
add_action('wp_head', function (): void {
    $metadata=joyrent_page_metadata();
    if (!$metadata) return;
    echo '<meta name="description" content="'.esc_attr($metadata['description']).'">';
    echo '<link rel="canonical" href="'.esc_url($metadata['url']).'">';
    $alternates=[];
    foreach (['uk','ru'] as $language) {
        $url=joyrent_public_page_url($metadata['kind'],$language);
        if ($url) {
            $alternates[$language]=$url;
            echo '<link rel="alternate" hreflang="'.esc_attr($language).'" href="'.esc_url($url).'">';
        }
    }
    if (!empty($alternates['uk'])) echo '<link rel="alternate" hreflang="x-default" href="'.esc_url($alternates['uk']).'">';
    // A JPEG export of existing hero artwork, without cropping or design changes.
    $image=get_template_directory_uri().'/assets/images/ps5-share.jpg';
    $image_path=get_template_directory().'/assets/images/ps5-share.jpg';
    $dimensions=is_readable($image_path)?@getimagesize($image_path):false;
    $width=(string)($dimensions[0] ?? 1200); $height=(string)($dimensions[1] ?? 1000);
    foreach (['og:type'=>'website','og:site_name'=>'JOYRENT','og:title'=>$metadata['title'],'og:description'=>$metadata['description'],'og:url'=>$metadata['url'],'og:locale'=>$metadata['language']==='ru'?'ru_UA':'uk_UA','og:image'=>$image,'og:image:type'=>'image/jpeg','og:image:width'=>$width,'og:image:height'=>$height,'og:image:alt'=>'PlayStation 5 — JOYRENT'] as $property=>$value) {
        echo '<meta property="'.esc_attr($property).'" content="'.esc_attr($value).'">';
    }
    $other=$metadata['language']==='ru'?'uk':'ru';
    if (isset($alternates[$other])) echo '<meta property="og:locale:alternate" content="'.($other==='ru'?'ru_UA':'uk_UA').'">';
    foreach (['twitter:card'=>'summary_large_image','twitter:title'=>$metadata['title'],'twitter:description'=>$metadata['description'],'twitter:image'=>$image,'twitter:image:alt'=>'PlayStation 5 — JOYRENT'] as $name=>$value) {
        echo '<meta name="'.esc_attr($name).'" content="'.esc_attr($value).'">';
    }
}, 5);

function joyrent_search_indexing_enabled(): bool {
    if ((string)get_option('blog_public','1')!=='1') return false;
    if (class_exists('JR_Settings')&&method_exists('JR_Settings','search_indexing')) return JR_Settings::search_indexing();
    // An older/missing plugin stays closed unless the owner explicitly opted in.
    $settings=(array)get_option('joyrent_settings',[]);
    return in_array($settings['search_indexing'] ?? false,[true,1,'1'],true);
}

function joyrent_is_internal_tariff(int $id): bool {
    if (get_post_type($id)!=='product') return false;
    return in_array(get_post_meta($id,'_joyrent_console',true),['ps5','ps4'],true)
        || preg_match('/^joyrent-ps[45]-\d+$/',(string)get_post_meta($id,'_sku',true))===1;
}

function joyrent_noindex_request(): bool {
    if (!joyrent_search_indexing_enabled()||is_search()||is_404()||is_author()||is_date()||is_attachment()) return true;
    if (function_exists('is_shop')&&(is_shop()||is_product_taxonomy()||is_cart()||is_checkout()||is_account_page())) return true;
    $post=get_queried_object();
    return $post instanceof WP_Post && (joyrent_is_internal_tariff($post->ID)||($post->post_type==='post'&&$post->post_name==='hello-world'));
}

add_filter('wp_robots', function (array $robots): array {
    if (joyrent_noindex_request()) {
        unset($robots['index']);
        $robots['noindex']=true;
    }
    return $robots;
}, 100);

add_filter('wp_headers', function (array $headers): array {
    if (is_admin()) return $headers;
    $existing=array_change_key_case($headers,CASE_LOWER);
    foreach (['X-Content-Type-Options'=>'nosniff','Referrer-Policy'=>'strict-origin-when-cross-origin','Permissions-Policy'=>'camera=(), microphone=(), geolocation=()'] as $name=>$value) {
        if (!isset($existing[strtolower($name)])) $headers[$name]=$value;
    }
    if (joyrent_noindex_request()) {
        $name='X-Robots-Tag'; $found=false; $generic_values=[]; $scoped_values=[];
        // Parameter directives contain colons too; only a bot prefix starts a scope.
        $scope='/(?:^|,)\s*(?!(?:max-snippet|max-image-preview|max-video-preview|unavailable_after)\s*:)[a-z][a-z0-9_-]*\s*:/i';
        foreach ($headers as $key=>$value) {
            if (strtolower($key)!=='x-robots-tag') continue;
            if (!$found) { $name=$key; $found=true; }
            $value=(string)$value;
            if (preg_match($scope,$value,$match,PREG_OFFSET_CAPTURE)) {
                $offset=$match[0][1];
                $generic=trim(substr($value,0,$offset));
                $scoped_values[]=ltrim(trim(substr($value,$offset)),', ');
            } else $generic=trim($value);
            if ($generic!=='') $generic_values[]=$generic;
            unset($headers[$key]);
        }
        // Keep global restrictions before scoped directives so they apply to every crawler.
        $value=implode(', ',array_unique($generic_values));
        if (!preg_match('/(?:^|,)\s*noindex\s*(?:,|$)/i',$value)) $value='noindex'.($value!==''?', '.$value:'');
        if ($scoped_values) $value.=', '.implode(', ',array_unique($scoped_values));
        $headers[$name]=$value;
    }
    return $headers;
});

// Staging is not advertised to crawlers. The WordPress privacy switch is respected.
add_filter('wp_sitemaps_enabled', fn(bool $enabled): bool => $enabled&&joyrent_search_indexing_enabled());
// JOYRENT has no public author profiles or storefront taxonomy destinations.
add_filter('wp_sitemaps_add_provider', function ($provider, string $name) {
    return $name==='users' ? false : $provider;
}, 10, 2);
add_filter('wp_sitemaps_taxonomies', function (array $taxonomies): array {
    foreach (['product_cat','product_tag','product_shipping_class','product_brand'] as $taxonomy) unset($taxonomies[$taxonomy]);
    return $taxonomies;
});

function joyrent_internal_post_ids(string $type='any'): array {
    $ids=[];
    if ($type==='any'||$type==='product') {
        $ids=get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','meta_query'=>['relation'=>'OR',['key'=>'_joyrent_console','value'=>['ps5','ps4'],'compare'=>'IN'],['key'=>'_sku','value'=>'^joyrent-ps[45]-[0-9]+$','compare'=>'REGEXP']]]);
    }
    if ($type==='any'||$type==='post') {
        $default=get_page_by_path('hello-world',OBJECT,'post');
        if ($default) $ids[]=(int)$default->ID;
    }
    if ($type==='any'||$type==='page') {
        foreach (['shop','cart','checkout','myaccount'] as $page) {
            $id=(int)get_option('woocommerce_'.$page.'_page_id');
            if ($id>0) $ids[]=$id;
        }
    }
    return array_values(array_unique(array_map('intval',$ids)));
}

add_filter('wp_sitemaps_posts_query_args', function (array $args, string $post_type): array {
    $args['post__not_in']=array_values(array_unique(array_merge($args['post__not_in'] ?? [],joyrent_internal_post_ids($post_type))));
    return $args;
}, 10, 2);

add_action('pre_get_posts', function (WP_Query $query): void {
    if (is_admin()||!$query->is_main_query()||!$query->is_search()) return;
    $query->set('post__not_in',array_values(array_unique(array_merge((array)$query->get('post__not_in'),joyrent_internal_post_ids()))));
});
