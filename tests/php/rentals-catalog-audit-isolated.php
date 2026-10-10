<?php
// Isolated catalogue and editor robustness checks; no WP/database/network/mail.
define('ABSPATH','/isolated/');
class WP_Post {
    public function __construct(public int $ID,public string $post_title='Owner game',public string $post_content='Owner description',public string $post_status='publish',public int $menu_order=0) {}
}
class WC_Product {
    public function __construct(public string $price='600',public string $status='publish',public bool $stock=true,public bool $enough=true) {}
    public function get_price(): string {return $this->price;}
    public function get_status(): string {return $this->status;}
    public function is_in_stock(): bool {return $this->stock;}
    public function has_enough_stock($count): bool {return $this->enough;}
}
$posts=[];$meta=[];$queries=[];$sku=123;$product=false;$saved=[];$thumbnail=false;$checks=0;$failures=[];
function get_posts(array $args): array {
    $GLOBALS['queries'][]=$args;
    $posts=array_values(array_filter($GLOBALS['posts'],fn($post)=>!isset($args['post_status'])||in_array($post->post_status,(array)$args['post_status'],true)));
    usort($posts,fn($a,$b)=>[$a->menu_order,$a->ID]<=>[$b->menu_order,$b->ID]);
    $size=$args['numberposts']??-1;$offset=$args['offset']??(($args['paged']??1)-1)*max(0,$size);
    if($size>0)$posts=array_slice($posts,$offset,$size);
    return ($args['fields']??'')==='ids'?array_column($posts,'ID'):$posts;
}
function get_post_meta(int $id,string $key='',bool $single=false): mixed {return $GLOBALS['meta'][$id]??'';}
function update_post_meta(int $id,string $key,mixed $data): void {$GLOBALS['saved'][$id]=$data;}
function get_the_post_thumbnail_url(...$args): mixed {return $GLOBALS['thumbnail'];}
function get_post_stati(): array {return ['publish'=>'publish','draft'=>'draft','trash'=>'trash'];}
function wc_get_product_id_by_sku($sku): int {return $GLOBALS['sku'];}
function wc_get_product($id): mixed {return $GLOBALS['product'];}
function wp_strip_all_tags(string $s): string {return strip_tags($s);}
function sanitize_text_field(string $s): string {return trim(strip_tags($s));}
function sanitize_textarea_field(string $s): string {return trim(strip_tags($s));}
function wp_unslash(mixed $s): mixed {return $s;}
function esc_html(mixed $s): string {return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function esc_attr(mixed $s): string {return esc_html($s);}
function esc_textarea(mixed $s): string {return esc_html($s);}
function checked(mixed $a,mixed $b=true,bool $echo=true): string {return $a===$b?'checked':'';}
function wp_nonce_field(...$args): void {}
function wp_is_post_revision(...$args): bool {return false;}
function current_user_can(...$args): bool {return $GLOBALS['manager']??true;}
function wp_verify_nonce(...$args): bool {return true;}
function check(bool $value,string $name): void {$GLOBALS['checks']++;if(!$value)$GLOBALS['failures'][]=$name;}
function fixture(array $data,int $id=1,int $order=0,string $status='publish'): void {$GLOBALS['posts'][$id]=new WP_Post($id,'Owner title '.$id,'Owner content '.$id,$status,$order);$GLOBALS['meta'][$id]=$data;}
require dirname(__DIR__,2).'/wordpress/joyrent-rentals/includes/domain.php';
require dirname(__DIR__,2).'/wordpress/joyrent-rentals/includes/store.php';
require dirname(__DIR__,2).'/wordpress/joyrent-rentals/includes/games.php';
set_error_handler(function($severity,$message){throw new ErrorException($message,0,$severity);});
try {
 try {check(JR_Store::product('ps5',1)===null,'Stale SKU or orphan product object is safely unavailable');}catch(Throwable $e){check(false,'Stale SKU lookup crashed: '.get_class($e));}
 $sku=0;check(JR_Store::product('ps5',1)===null,'Unknown SKU is null');$sku=123;$product=new WC_Product();check(JR_Store::product('ps5',1)===$product,'Real product returned unchanged');
 foreach(['600','200.50','0.01'] as $price)check(JR_Store::requestable(new WC_Product($price)),'Finite positive price accepted '.$price);
 foreach(['','0','-1','oops','INF','NAN','1e9999'] as $price)check(!JR_Store::requestable(new WC_Product($price)),'Nonpositive/nonnumeric/nonfinite price rejected '.$price);
 $base=JR_Domain::catalog()['games'][0];
 $invalid=$base;$invalid['id']=['corrupt'];fixture($invalid);
 try {check(JR_Games::records()===[],'Invalid game ID is skipped without catalog fallback');}catch(Throwable $e){check(false,'Malformed ID crashed catalogue: '.get_class($e));}
 $posts=[];$meta=[];$queries=[];
 fixture($base,1,-10);fixture($base,2,-9);
 $invalid=$base;$invalid['id']=[];fixture($invalid,3,-8);
 for($i=4;$i<=250;$i++){ $g=$base;$g['id']='game-'.$i;fixture($g,$i,$i); }
 $before=serialize([$posts,$meta]);$games=JR_Games::records();
 check(count($games)===100&&count(array_unique(array_column($games,'id')))===100,'Output has 100 valid unique records despite early corrupt/duplicate rows');
 check($games[0]['id']===$base['id']&&end($games)['id']==='game-102','Menu ranking and later valid records preserved');
 check(count($queries)<=3&&array_reduce($queries,fn($ok,$q)=>$ok&&($q['numberposts']??-1)>0,true),'Database retrieval uses bounded batches and stops after 100 valid records');
 check(serialize([$posts,$meta])===$before&&$saved===[],'Public projection never modifies owner metadata');
 $posts=[];$meta=[];$queries=[];$broken=$base;
 $broken['platforms']='ps5';$broken['filters']=['sport',['nested']];$broken['players']=['bad'];$broken['playersByPlatform']='bad';$broken['requiresInternet']='false';$broken['available']='false';$broken['genre']=['bad'];$broken['descriptionRu']=['bad'];$broken['image']=['bad'];$broken['owner_field']='keep';
 fixture($broken);
 try {$g=JR_Games::records()[0];check(is_array($g['platforms'])&&is_array($g['filters'])&&$g['filters']===['sport'],'Public platform/filter shapes are safe for frontend includes');check(is_int($g['players'])&&$g['players']>=1&&$g['players']<=4,'Malformed player count becomes usable integer');check(is_array($g['playersByPlatform']??[])&&!$g['requiresInternet']&&!$g['available'],'Malformed optional fields are safe typed values');check(is_string($g['genre'])&&is_string($g['descriptionRu'])&&is_string($g['image'])&&$g['owner_field']==='keep','Known public strings normalized, unknown owner data preserved');}catch(Throwable $e){check(false,'Malformed field projection crashed: '.get_class($e));}
 try {ob_start();JR_Games::box($posts[1]);$html=ob_get_clean();check(str_contains($html,'name="jr_game[genre]"'),'Editor opens even with malformed imported metadata');}catch(Throwable $e){if(ob_get_level())ob_end_clean();check(false,'Malformed metadata crashed editor: '.get_class($e));}
 $posts=[];$meta=[];$queries=[];$owned=$base;$owned['image']='owner-custom-cover';$owned['genre']='Owner genre';$owned['playersByPlatform']=['ps5'=>4,'ps4'=>2];fixture($owned);$thumbnail='https://example.invalid/owner-cover.webp';$g=JR_Games::records()[0];check($g['image']==='owner-custom-cover'&&$g['imageUrl']===$thumbnail&&$g['genre']==='Owner genre'&&$g['playersByPlatform']===$owned['playersByPlatform'],'Owner artwork, text, per-platform players and custom thumbnail preserved');$thumbnail=false;
 foreach(['draft','trash'] as $status){$posts[1]->post_status=$status;check(JR_Games::records()===[],'Intentionally hidden catalogue stays empty: '.$status);}
 $posts=[];$meta=[];check(JR_Games::records()===JR_Domain::catalog()['games'],'Brand-new store keeps bundled fallback catalogue');
 $posts=[];$meta=[];$corrupt=$base;$corrupt['id']=['bad'];$corrupt['image']=['bad'];fixture($corrupt);
 $_POST=['joyrent_game_nonce'=>'valid','jr_game'=>['players'=>[],'playersByPlatform'=>['ps5'=>[]],'platforms'=>[['bad'],'ps5'],'filters'=>[['bad'],'sport']]];
 try {JR_Games::save(1,$posts[1]);check(($saved[1]['id']??'')==='game-1'&&is_string($saved[1]['image'])&&$saved[1]['platforms']===['ps5']&&$saved[1]['filters']===['sport'],'Editor save repairs malformed ID and accepts only typed platform/filter lists');}catch(Throwable $e){check(false,'Malformed admin save crashed: '.get_class($e));}
 $before=$saved;$GLOBALS['manager']=false;JR_Games::save(1,$posts[1]);check($saved===$before,'Unauthorized user cannot save game metadata');$GLOBALS['manager']=true;
 $_POST=['joyrent_game_nonce'=>[],'jr_game'=>[]];$before=$saved;
 try {JR_Games::save(1,$posts[1]);check($saved===$before,'Malformed nonce safely rejects save');}catch(Throwable $e){check(false,'Malformed nonce crashes save: '.get_class($e));}
} finally {restore_error_handler();}
echo json_encode(['checks'=>$checks,'failures'=>$failures,'realDatabaseWrites'=>0,'realMailCalls'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";exit($failures?1:0);
