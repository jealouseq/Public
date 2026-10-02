<?php
if (!defined('ABSPATH')) exit;

final class JR_Games {
    public static function register(): void {
        register_post_type('joyrent_game',['labels'=>['name'=>'Ігри JOYRENT','singular_name'=>'Гра','add_new_item'=>'Додати гру','edit_item'=>'Редагувати гру'],'public'=>false,'show_ui'=>true,'show_in_rest'=>false,'menu_icon'=>'dashicons-games','supports'=>['title','editor','thumbnail'],'capability_type'=>'post','map_meta_cap'=>true]);
    }
    public static function meta_boxes(): void { add_meta_box('joyrent-game','Параметри гри',[self::class,'box'],'joyrent_game','normal'); }
    public static function box(WP_Post $post): void {
        $data=(array)get_post_meta($post->ID,'_jr_game',true);
        wp_nonce_field('joyrent_game','joyrent_game_nonce');
        foreach (['genre'=>'Жанр','rating'=>'Віковий рейтинг (наприклад 12+)','players'=>'Локальних гравців (1–4)','eyebrow'=>'Короткий підпис'] as $key=>$label) echo '<p><label>'.esc_html($label).'<br><input class="widefat" name="jr_game['.esc_attr($key).']" value="'.esc_attr((string)($data[$key]??'')).'"></label></p>';
        foreach (['ps5'=>'PlayStation 5','ps4'=>'PlayStation 4'] as $key=>$label) echo '<label style="margin-right:20px"><input type="checkbox" name="jr_game[platforms][]" value="'.esc_attr($key).'" '.checked(in_array($key,$data['platforms']??[],true),true,false).'> '.esc_html($label).'</label>';
        echo '<p>';
        foreach (['two'=>'На двох','party'=>'Для компанії','racing'=>'Перегони','sport'=>'Спорт','story'=>'Сюжетні','kids'=>'Дітям'] as $key=>$label) echo '<label style="margin-right:15px"><input type="checkbox" name="jr_game[filters][]" value="'.esc_attr($key).'" '.checked(in_array($key,$data['filters']??[],true),true,false).'> '.esc_html($label).'</label>';
        echo '</p><p><label><input type="checkbox" name="jr_game[available]" value="1" '.checked($data['available']??false,true,false).'> Наявність і ліцензію перевірено</label></p><p>Зображення обкладинки можна встановити через «Головне зображення». Початкові зображення — редакційні ілюстрації.</p>';
    }
    public static function save(int $id, WP_Post $post): void {
        if (defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE || wp_is_post_revision($id) || !current_user_can('edit_post',$id) || !isset($_POST['joyrent_game_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['joyrent_game_nonce'])),'joyrent_game')) return;
        $input=isset($_POST['jr_game'])&&is_array($_POST['jr_game'])?wp_unslash($_POST['jr_game']):[];
        $old=(array)get_post_meta($id,'_jr_game',true);
        $data=['id'=>$old['id']??'game-'.$id,'title'=>$post->post_title,'shortTitle'=>$post->post_title,'description'=>wp_strip_all_tags($post->post_content),'image'=>$old['image']??'game-family','available'=>!empty($input['available'])];
        foreach (['genre','rating','eyebrow'] as $key) $data[$key]=sanitize_text_field(is_scalar($input[$key]??null)?(string)$input[$key]:'');
        $data['players']=max(1,min(4,(int)($input['players']??1)));
        $data['platforms']=array_values(array_intersect(['ps5','ps4'],is_array($input['platforms']??null)?$input['platforms']:[]));
        $data['filters']=array_values(array_intersect(['two','party','racing','sport','story','kids'],is_array($input['filters']??null)?$input['filters']:[]));
        update_post_meta($id,'_jr_game',$data);
    }
    public static function records(): array {
        $posts=get_posts(['post_type'=>'joyrent_game','post_status'=>'publish','numberposts'=>100,'orderby'=>'menu_order ID','order'=>'ASC']);
        $games=[];
        foreach ($posts as $post) {
            $data=get_post_meta($post->ID,'_jr_game',true);
            if (!is_array($data)||empty($data['id'])) continue;
            $data['title']=wp_strip_all_tags($post->post_title); $data['description']=wp_strip_all_tags($post->post_content);
            $url=get_the_post_thumbnail_url($post,'large'); if ($url) $data['imageUrl']=$url;
            $games[]=$data;
        }
        return $games ?: JR_Domain::catalog()['games'];
    }
}
