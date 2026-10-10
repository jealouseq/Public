<?php
declare(strict_types=1);
require '/var/www/html/wp-load.php';
if (!get_option('joyrent_qa_bot_fixture_enabled',false)) throw new RuntimeException('Disposable QA required.');
$checks=0;$failures=[];$created=[];$callbacks=[];
function audit_check(bool $ok,string $label):void{global $checks,$failures;$checks++;if(!$ok)$failures[]=$label;}
function audit_mail_count():int{return (int)get_option('joyrent_qa_intercepted_mail',0);}
function audit_data(string $name):array{
 $date=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->modify('+10 days')->format('Y-m-d');
 return JR_Domain::validate(['console'=>'ps5','days'=>3,'startDate'=>$date,'controllers'=>2,'gameIds'=>[],'name'=>$name,'phone'=>'+380500000077','method'=>'delivery','address'=>'Тестова адреса Одеса 10','consent'=>true],null,JR_Games::records());
}
function audit_create(array $data,string $key):array{
 try{return ['receipt'=>JR_Orders::create($data,$key,hash('sha256',wp_json_encode($data))),'error'=>null];}
 catch(Throwable $e){return ['receipt'=>null,'error'=>get_class($e)];}
}
function audit_find(string $key):array{return wc_get_orders(['limit'=>10,'joyrent_request_key'=>$key,'meta_query'=>[['key'=>'_joyrent_request_key','value'=>$key]]]);}
function audit_backdate(int $id):void{
 global $wpdb;$when=time()-2*DAY_IN_SECONDS;
 // Saving normally refreshes date_modified; only disposable fixture identities are backdated.
 if(\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled())$wpdb->update($wpdb->prefix.'wc_orders',['date_updated_gmt'=>gmdate('Y-m-d H:i:s',$when)],['id'=>$id]);
 else $wpdb->update($wpdb->posts,['post_modified'=>wp_date('Y-m-d H:i:s',$when),'post_modified_gmt'=>gmdate('Y-m-d H:i:s',$when)],['ID'=>$id]);
 clean_post_cache($id);wp_cache_flush();
}
$orphansBefore=$wpdb->get_col("SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id=0");
try{
 $key=hash('sha256',wp_generate_uuid4());$before=audit_mail_count();
 $failInitial=function($o)use($key){if($o instanceof WC_Order&&$o->get_meta('_joyrent_request_key')===$key)throw new RuntimeException('Fixture first-save failure');};
 add_action('woocommerce_before_order_object_save',$failInitial,PHP_INT_MAX,1);
 $r=audit_create(audit_data('Initial Save Fixture'),$key);
 remove_action('woocommerce_before_order_object_save',$failInitial,PHP_INT_MAX);
 foreach(audit_find($key)as$o)$created[]=$o->get_id();
 audit_check($r['receipt']===null&&$r['error']!==null,'Failed initial WooCommerce save never returns JR-0 success');
 audit_check(audit_find($key)===[],'Initial failure has no durable booking');
 audit_check(audit_mail_count()===$before,'Initial failure makes no email attempt');
 $orphansAfter=$wpdb->get_col("SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id=0");
 audit_check(array_diff($orphansAfter,$orphansBefore)===[],'Failed first save creates no orphan order items');

 $key=hash('sha256',wp_generate_uuid4());$before=audit_mail_count();
 $failFinal=function($o)use($key){if($o instanceof WC_Order&&$o->get_meta('_joyrent_request_key')===$key&&$o->get_meta('_joyrent_completed')==='yes')throw new RuntimeException('Fixture final-save failure');};
 add_action('woocommerce_before_order_object_save',$failFinal,PHP_INT_MAX,1);
 $r=audit_create(audit_data('Final Save Fixture'),$key);
 remove_action('woocommerce_before_order_object_save',$failFinal,PHP_INT_MAX);
 $found=audit_find($key);foreach($found as$o)$created[]=$o->get_id();
 audit_check($r['receipt']===null&&$r['error']!==null,'Failed final WooCommerce save never returns accepted receipt');
 audit_check(audit_mail_count()===$before,'Failed final save sends no manager email');
 audit_check(count($found)===1&&$found[0]->get_status()==='jr-incomplete','Failed final save retains its key in a visible incomplete status');
 if(count($found)===1){
  $retainedId=$found[0]->get_id();audit_backdate($retainedId);
  $control=new WC_Order();$control->set_status('checkout-draft');$control->set_created_via('local-audit');$control->save();$controlId=$control->get_id();$created[]=$controlId;audit_backdate($controlId);
  $retained=new WC_Order($retainedId);$dated=$retained->get_date_modified();$control=new WC_Order($controlId);$controlDated=$control->get_date_modified();
  audit_check($dated&&$controlDated&&$dated->getTimestamp()<time()-DAY_IN_SECONDS&&$controlDated->getTimestamp()<time()-DAY_IN_SECONDS,'Cleanup fixture and ordinary draft are both older than one day');
  do_action('woocommerce_cleanup_draft_orders');
  $retained=wc_get_order($retainedId);
  audit_check($retained instanceof WC_Order&&$retained->get_status()==='jr-incomplete'&&$retained->get_meta('_joyrent_request_key')===$key,'Actual WooCommerce daily draft cleanup preserves incomplete booking and idempotency key');
  audit_check(wc_get_order($controlId)===false,'Actual WooCommerce cleanup removes its ordinary expired draft control');
 }

 // A partial CPT save can persist completed metadata before the final post status.
 if(!\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()){
  $key=hash('sha256',wp_generate_uuid4());$data=audit_data('Partial CPT Save Fixture');$fingerprint=hash('sha256',wp_json_encode($data));$before=audit_mail_count();
  $failPostStatus=function($fields,$postarr)use($key){
   $id=(int)($postarr['ID']??0);
   if($id>0&&($fields['post_status']??'')==='wc-jr-request'&&get_post_meta($id,'_joyrent_request_key',true)===$key)throw new RuntimeException('Fixture interrupted final post status');
   return $fields;
  };
  add_filter('wp_insert_post_data',$failPostStatus,PHP_INT_MAX,2);
  try{$r=audit_create($data,$key);}finally{remove_filter('wp_insert_post_data',$failPostStatus,PHP_INT_MAX);}
  $found=audit_find($key);foreach($found as$o)$created[]=$o->get_id();
  audit_check($r['receipt']===null&&$r['error']!==null,'Partial CPT final save never returns an accepted receipt');
  audit_check(count($found)===1&&$found[0]->get_status()==='jr-incomplete','Interrupted CPT record is retained visibly for manager review');
  audit_check(count($found)===1&&$found[0]->get_meta('_joyrent_completed')==='yes','Fixture actually persisted completed metadata before final status');
  $rejected=false;try{JR_Orders::existing($key,$fingerprint);}catch(JR_Incomplete_Booking $e){$rejected=true;}
  audit_check($rejected,'Partial completed CPT save cannot be replayed as accepted');
  $sent=count($found)===1?JR_Orders::notify($found[0]->get_id()):false;
  audit_check(!$sent&&audit_mail_count()===$before,'Partial CPT save never emits a manager notification');
 }
 // A stale completed flag alone is not proof that booking acceptance was durable.
 $key=hash('sha256',wp_generate_uuid4());$fingerprint=hash('sha256',wp_generate_uuid4());$before=audit_mail_count();
 $unfinished=new WC_Order();$unfinished->set_status('checkout-draft');$unfinished->set_created_via('joyrent');
 foreach(['_joyrent_request_key'=>$key,'_joyrent_fingerprint'=>$fingerprint,'_joyrent_completed'=>'yes','_joyrent_rental_amount'=>1400]as$meta=>$value)$unfinished->update_meta_data($meta,$value);
 $unfinished->save();$created[]=$unfinished->get_id();
 $rejected=false;try{JR_Orders::existing($key,$fingerprint);}catch(JR_Incomplete_Booking $e){$rejected=true;}
 audit_check($rejected,'Synthetic completed checkout draft cannot be replayed as accepted in either storage');
 audit_check(!JR_Orders::notify($unfinished->get_id())&&audit_mail_count()===$before,'Synthetic unfinished booking cannot send a manager notification');

 $key=hash('sha256',wp_generate_uuid4());$data=audit_data('Durable Save Fixture');$before=audit_mail_count();$r=audit_create($data,$key);$found=audit_find($key);
 foreach($found as$o)$created[]=$o->get_id();
 audit_check($r['error']===null&&count($found)===1&&$r['receipt']['reference']!=='JR-0','Normal booking is durably accepted');
 $order=$found[0];$id=$order->get_id();
 audit_check($order->get_meta('_joyrent_completed')==='yes'&&$order->get_status()==='jr-request','Confirmed receipt has completed durable booking');
 audit_check((float)$order->get_meta('_joyrent_rental_amount')===1400.0&&count($order->get_items('line_item'))===1,'Rental and its item persist');
 audit_check(audit_mail_count()===$before+1,'Successful booking triggers one intercepted email');
 audit_check(JR_Orders::notify($id)&&audit_mail_count()===$before+1,'Repeated notification does not resend');
 $order->set_status('processing');$order->save();$beforeProcessing=audit_mail_count();
 audit_check(JR_Orders::existing($key,hash('sha256',wp_json_encode($data)))===$r['receipt'],'Accepted booking remains idempotent after manager advances its status');
 audit_check(JR_Orders::notify($id)&&audit_mail_count()===$beforeProcessing,'Advanced accepted booking does not resend manager notification');
 $order=wc_get_order($id);$order->update_meta_data('_joyrent_notification_status','failed');$order->save();
 $before=audit_mail_count();
 $failMarker=function($o)use($id){if($o instanceof WC_Order&&$o->get_id()===$id&&$o->get_meta('_joyrent_notification_status')==='sending')throw new RuntimeException('Fixture send-marker failure');};
 add_action('woocommerce_before_order_object_save',$failMarker,PHP_INT_MAX,1);
 $sent=JR_Orders::notify($id);
 remove_action('woocommerce_before_order_object_save',$failMarker,PHP_INT_MAX);
 audit_check(!$sent&&audit_mail_count()===$before,'Email requires a durable sending marker before external delivery');
}finally{
 foreach($created as$id){$o=wc_get_order($id);if($o)$o->delete(true);}
 $orphansAfter=$wpdb->get_col("SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id=0");
 foreach(array_diff($orphansAfter,$orphansBefore)as$id){
  $wpdb->delete($wpdb->prefix.'woocommerce_order_itemmeta',['order_item_id'=>(int)$id]);
  $wpdb->delete($wpdb->prefix.'woocommerce_order_items',['order_item_id'=>(int)$id]);
 }
}
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures],JSON_PRETTY_PRINT)."\n";
exit($failures?1:0);
