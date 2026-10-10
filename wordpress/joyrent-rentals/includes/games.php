<?php
if (!defined('ABSPATH')) exit;

final class JR_Games {
    public static function register(): void {
        register_post_type('joyrent_game',['labels'=>['name'=>'Ігри JOYRENT','singular_name'=>'Гра','add_new_item'=>'Додати гру','edit_item'=>'Редагувати гру'],'public'=>false,'show_ui'=>true,'show_in_rest'=>false,'menu_icon'=>'dashicons-games','supports'=>['title','editor','thumbnail','page-attributes'],'capability_type'=>'post','map_meta_cap'=>true]);
    }
    public static function meta_boxes(): void { add_meta_box('joyrent-game','Параметри гри',[self::class,'box'],'joyrent_game','normal'); }
    public static function box(WP_Post $post): void {
        $data=self::normalize((array)get_post_meta($post->ID,'_jr_game',true));
        wp_nonce_field('joyrent_game','joyrent_game_nonce');
        foreach (['genre'=>'Жанр','rating'=>'Віковий рейтинг (наприклад 12+)','players'=>'Локальних гравців (1–4)','eyebrow'=>'Короткий підпис','genreRu'=>'Жанр російською'] as $key=>$label) echo '<p><label>'.esc_html($label).'<br><input class="widefat" name="jr_game['.esc_attr($key).']" value="'.esc_attr((string)($data[$key]??'')).'"></label></p>';
        echo '<p><label>Опис російською<br><textarea class="widefat" rows="4" name="jr_game[descriptionRu]">'.esc_textarea((string)($data['descriptionRu']??'')).'</textarea></label></p>';
        foreach (['ps5'=>'Локальних гравців PS5','ps4'=>'Локальних гравців PS4'] as $platform=>$label) echo '<p><label>'.esc_html($label).' (порожнє поле — загальне значення)<br><input type="number" min="1" max="4" name="jr_game[playersByPlatform]['.esc_attr($platform).']" value="'.esc_attr((string)($data['playersByPlatform'][$platform]??'')).'"></label></p>';
        echo '<p><label><input type="checkbox" name="jr_game[requiresInternet]" value="1" '.checked($data['requiresInternet']??false,true,false).'> Потрібне підключення до інтернету</label></p>';
        foreach (['ps5'=>'PlayStation 5','ps4'=>'PlayStation 4'] as $key=>$label) echo '<label style="margin-right:20px"><input type="checkbox" name="jr_game[platforms][]" value="'.esc_attr($key).'" '.checked(in_array($key,$data['platforms']??[],true),true,false).'> '.esc_html($label).'</label>';
        echo '<p>';
        foreach (['two'=>'На двох','party'=>'Для компанії','racing'=>'Перегони','sport'=>'Спорт','story'=>'Сюжетні','kids'=>'Дітям'] as $key=>$label) echo '<label style="margin-right:15px"><input type="checkbox" name="jr_game[filters][]" value="'.esc_attr($key).'" '.checked(in_array($key,$data['filters']??[],true),true,false).'> '.esc_html($label).'</label>';
        echo '</p><p><label><input type="checkbox" name="jr_game[available]" value="1" '.checked($data['available']??false,true,false).'> Наявність і ліцензію перевірено</label></p><p>Зображення обкладинки можна встановити через «Головне зображення». Початкові обкладинки — офіційні матеріали відповідних ігор. Наявність та видання підтверджує магазин.</p><p>Пріоритет у добірці: поле «Порядок» у блоці «Атрибути». Менше число — вище у списку.</p>';
    }
    public static function save(int $id, WP_Post $post): void {
        $nonce=$_POST['joyrent_game_nonce']??null;
        if (defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE || wp_is_post_revision($id) || !current_user_can('edit_post',$id) || !is_string($nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)),'joyrent_game')) return;
        $input=isset($_POST['jr_game'])&&is_array($_POST['jr_game'])?wp_unslash($_POST['jr_game']):[];
        $old=self::normalize((array)get_post_meta($id,'_jr_game',true));
        $data=['id'=>$old['id']!==''?$old['id']:'game-'.$id,'title'=>$post->post_title,'shortTitle'=>$post->post_title,'description'=>wp_strip_all_tags($post->post_content),'image'=>$old['image']??'','available'=>!empty($input['available'])];
        foreach (['genre','rating','eyebrow','genreRu'] as $key) $data[$key]=sanitize_text_field(is_scalar($input[$key]??null)?(string)$input[$key]:'');
        $data['descriptionRu']=sanitize_textarea_field(is_string($input['descriptionRu']??null)?$input['descriptionRu']:'');
        $data['players']=self::players($input['players']??1);
        $per_platform=is_array($input['playersByPlatform']??null)?$input['playersByPlatform']:[];
        foreach (['ps5','ps4'] as $platform) if (isset($per_platform[$platform])&&$per_platform[$platform]!=='') $data['playersByPlatform'][$platform]=self::players($per_platform[$platform]);
        $data['requiresInternet']=!empty($input['requiresInternet']);
        $data['platforms']=self::choices($input['platforms']??[],['ps5','ps4']);
        $data['filters']=self::choices($input['filters']??[],['two','party','racing','sport','story','kids']);
        update_post_meta($id,'_jr_game',$data);
    }
    private static function players(mixed $value): int {
        $number=is_scalar($value)&&!is_bool($value)?filter_var($value,FILTER_VALIDATE_INT):false;
        return $number===false?1:max(1,min(4,$number));
    }
    private static function choices(mixed $values, array $allowed): array {
        if (!is_array($values)) return [];
        return array_values(array_filter($allowed,fn($value)=>in_array($value,$values,true)));
    }
    private static function normalize(array $data): array {
        // Project malformed imported metadata safely; reads never rewrite owner records.
        $data['id']=is_string($data['id']??null)?$data['id']:'';
        foreach (['genre','rating','eyebrow','genreRu','descriptionRu','shortTitle','image'] as $key) {
            $data[$key]=is_scalar($data[$key]??null)?(string)$data[$key]:'';
        }
        $data['players']=self::players($data['players']??1);
        $data['platforms']=self::choices($data['platforms']??[],['ps5','ps4']);
        $data['filters']=self::choices($data['filters']??[],['two','party','racing','sport','story','kids']);
        foreach (['available','requiresInternet'] as $key) $data[$key]=in_array($data[$key]??false,[true,1,'1'],true);
        $per_platform=is_array($data['playersByPlatform']??null)?$data['playersByPlatform']:[];
        $data['playersByPlatform']=[];
        foreach (['ps5','ps4'] as $platform) if (isset($per_platform[$platform])&&$per_platform[$platform]!=='') {
            $data['playersByPlatform'][$platform]=self::players($per_platform[$platform]);
        }
        return $data;
    }
    public static function records(): array {
        $games=[]; $seen=[]; $initial=array_column(JR_Domain::catalog()['games'],null,'id');
        $batch=100; $page=1;
        do {
            // Prime metadata for at most one bounded batch, retaining later valid unique games.
            $posts=get_posts(['post_type'=>'joyrent_game','post_status'=>'publish','numberposts'=>$batch,'paged'=>$page++,'orderby'=>'menu_order ID','order'=>'ASC','update_post_term_cache'=>false]);
            foreach ($posts as $post) {
                $raw=get_post_meta($post->ID,'_jr_game',true);
                if (!is_array($raw)||!is_string($raw['id']??null)||$raw['id']==='') continue;
                $data=self::normalize($raw);
                if (isset($seen[$data['id']])) continue;
                $seen[$data['id']]=true;
                $data['title']=wp_strip_all_tags($post->post_title); $data['description']=wp_strip_all_tags($post->post_content);
                $source=$initial[$data['id']]??null;
                if ($source) {
                    // Correct exact shipped labels for public output without rewriting owner records.
                    if ($data['id']==='resident-evil-requiem') {
                        foreach (['genre','eyebrow'] as $field) if (($data[$field]??'')==='Горор') $data[$field]=$source[$field];
                    }
                    // Replace only bundled legacy artwork; owner thumbnails remain authoritative.
                    if (!empty($source['legacyImage'])&&($data['image']??'')===$source['legacyImage']) $data['image']=$source['image'];
                    if (empty($data['genreRu'])&&($data['genre']??'')===$source['genre']) $data['genreRu']=$source['genreRu'];
                    if (empty($data['descriptionRu'])&&$data['description']===$source['description']) $data['descriptionRu']=$source['descriptionRu'];
                }
                $url=get_the_post_thumbnail_url($post,'large'); if ($url) $data['imageUrl']=$url;
                $games[]=$data;
                if (count($games)>=100) return $games;
            }
        } while (count($posts)===$batch);
        if ($games) return $games;
        // An intentionally hidden catalogue must stay empty, including games in the trash.
        $managed=get_posts(['post_type'=>'joyrent_game','post_status'=>array_values(get_post_stati()),'numberposts'=>1,'fields'=>'ids']);
        return $managed ? [] : JR_Domain::catalog()['games'];
    }
}
