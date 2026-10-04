<?php
if (!defined('ABSPATH')) exit;

final class JR_REST {
    private static string $language = 'uk';
    public static function register(): void {
        register_rest_route('joyrent/v1','/catalog',['methods'=>'GET','callback'=>fn()=>rest_ensure_response(JR_Store::catalog()),'permission_callback'=>'__return_true']);
        register_rest_route('joyrent/v1','/requests',['methods'=>'POST','callback'=>[self::class,'create'],'permission_callback'=>'__return_true']);
    }
    private static function error(string $code, string $message, int $status): WP_Error { return new WP_Error($code,JR_Locale::message($message,self::$language),['status'=>$status]); }
    private static function acquire(string $lock, int $ttl): string|false {
        global $wpdb;
        $old=get_option($lock);
        if ($old && (int)$old < time()-$ttl) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$lock,(string)$old));
        }
        $owner=time().':'.wp_generate_uuid4();
        // add_option uses an upsert in WordPress; INSERT IGNORE is the mutex.
        $inserted=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')",$lock,$owner));
        wp_cache_delete($lock,'options'); wp_cache_delete('notoptions','options');
        return $inserted===1 ? $owner : false;
    }
    private static function release(string $lock, string $owner): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$lock,$owner));
        wp_cache_delete($lock,'options');
    }
    private static function reserve(string $key): bool {
        $lock=$key.'_lock';
        $owner=self::acquire($lock,10);
        if (!$owner) return false;
        try {
            $count=(int)get_transient($key);
            if ($count>=8) return false;
            set_transient($key,$count+1,15*MINUTE_IN_SECONDS);
            return true;
        } finally { self::release($lock,$owner); }
    }
    private static function receipt(string $key, string $result_key, string $fingerprint, string $intent_fingerprint): ?array {
        $cached=get_option($result_key);
        if (is_array($cached)&&is_string($cached['fingerprint']??null)&&hash_equals($cached['fingerprint'],$fingerprint)) return $cached['receipt'];
        // Older receipts contain only the original hash. The order can resolve equivalent formatting/sets.
        $existing=class_exists('WooCommerce')?JR_Orders::existing($key,$fingerprint,$intent_fingerprint):null;
        if ($existing) return $existing;
        if (is_array($cached)) throw new InvalidArgumentException('Параметри бронювання змінилися. Онови сторінку та спробуй ще раз.');
        return null;
    }
    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $payload=$request->get_json_params();
        self::$language=is_array($payload)&&($payload['language']??null)==='ru'?'ru':'uk';
        if (!is_array($payload)) return self::error('jr_payload','Перевір дані бронювання.',400);
        if (!empty($payload['website'])) return self::error('jr_invalid','Не вдалося надіслати бронювання.',400);
        $id=$payload['requestId']??'';
        if (!is_string($id)||!preg_match('/^[a-f0-9-]{32,40}$/i',$id)) return self::error('jr_request_id','Онови сторінку та спробуй ще раз.',400);
        try { $data=JR_Domain::canonical($payload); } catch (InvalidArgumentException $e) { return self::error('jr_validation',$e->getMessage(),400); }
        $key=hash_hmac('sha256',$id,wp_salt('nonce')); $result_key='jr_result_'.$key;
        $fingerprint=hash('sha256',wp_json_encode($data));
        $intent_fingerprint=JR_Domain::intent_fingerprint($data);
        // Persist receipts without customer details so retries stay idempotent
        // after a day or after WordPress clears transient caches.
        try {
            $receipt=self::receipt($key,$result_key,$fingerprint,$intent_fingerprint);
            if ($receipt) return new WP_REST_Response($receipt,200);
        } catch (InvalidArgumentException $e) { return self::error('jr_conflict',$e->getMessage(),409);
        } catch (Throwable $e) { return self::error('jr_create','Не вдалося прийняти бронювання. Спробуй ще раз трохи пізніше.',503); }
        if (!class_exists('WooCommerce')||get_woocommerce_currency()!=='UAH') return self::error('jr_unavailable','Зараз бронювання недоступне. Спробуй пізніше.',503);
        try { $data=JR_Domain::validate($payload,null,JR_Games::records()); } catch (InvalidArgumentException $e) { return self::error('jr_validation',$e->getMessage(),400); }
        $settings=JR_Settings::public();
        if ($data['method']==='pickup'&&!$settings['pickup']) return self::error('jr_pickup','Самовивіз зараз не підтверджений. Обери доставку.',400);
        $rate_key='jr_rate_'.hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),wp_salt('auth'));
        $lock='jr_lock_'.$key;
        $owner=self::acquire($lock,5*MINUTE_IN_SECONDS);
        if (!$owner) return self::error('jr_busy','Це бронювання вже надсилається. Зачекай і спробуй ще раз.',409);
        try {
            // Another worker may have completed between the first lookup and lock.
            $receipt=self::receipt($key,$result_key,$fingerprint,$intent_fingerprint);
            if ($receipt) return new WP_REST_Response($receipt,200);
            if (!self::reserve($rate_key)) return self::error('jr_limit','Забагато бронювань за короткий час. Спробуй через 15 хвилин.',429);
            $data['language']=self::$language; // UI language is deliberately outside the canonical fingerprint.
            $receipt=JR_Orders::create($data,$key,$fingerprint);
            // The WooCommerce key remains authoritative if this cache write fails.
            add_option($result_key,['fingerprint'=>$fingerprint,'receipt'=>$receipt],'','no');
            return new WP_REST_Response($receipt,201);
        } catch (InvalidArgumentException $e) { return self::error('jr_conflict',$e->getMessage(),409);
        } catch (Throwable $e) { return self::error('jr_create','Не вдалося прийняти бронювання. Спробуй ще раз трохи пізніше.',503); }
        finally { self::release($lock,$owner); }
    }
}
