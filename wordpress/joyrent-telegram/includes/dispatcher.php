<?php
defined('ABSPATH') || exit;

/** Event-driven, short shared-host worker. No polling loop or daemon. */
final class JRTG_Dispatcher {
    private const OPTION='joyrent_telegram_dispatch';
    private const MAX_STEPS=3;
    private const BUDGET=2.0;
    private const LEASE=45;
    private static bool $pending=false;
    private static bool $running=false;

    public static function boot(): void {
        add_action('rest_api_init',static function():void {
            register_rest_route('joyrent-telegram/v1','/dispatch',[
                'methods'=>'POST','permission_callback'=>[self::class,'authorize'],
                'callback'=>static function():WP_REST_Response {self::run();return new WP_REST_Response(['accepted'=>true],202);},
            ]);
        });
        add_action('shutdown',[self::class,'shutdown'],0);
    }

    /** Only durable order IDs enter this queue; no HTTP during booking acceptance. */
    public static function wake(int $id): void {
        if ($id<=0||self::$running) return;
        try {
            $at=JRTG_Notifications::next_due($id);
            if ($at===null) return;
            self::$pending=true; // A contended registry write is retried at shutdown from durable backup work.
            self::change(static function(array &$state)use($id,$at):void {
                $state['jobs'][$id]=isset($state['jobs'][$id])?min((int)$state['jobs'][$id],$at):$at;
                $state['generations'][$id]=bin2hex(random_bytes(16));
            });
        } catch (Throwable $ignored) {}
    }

    public static function forget(int $id): void {
        self::change(static function(array &$s)use($id):void {unset($s['jobs'][$id],$s['generations'][$id]);});
    }

    /** FPM/LSAPI finish the customer response before any Telegram API call. */
    public static function shutdown(): void {
        if (!self::$pending||self::$running||PHP_SAPI==='cli') return;
        self::$pending=false;
        if (!self::ready()) return;
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {fastcgi_finish_request();self::run();}
        elseif (function_exists('litespeed_finish_request')) {litespeed_finish_request();self::run();}
        else self::kick();
    }

    public static function authorize(WP_REST_Request $request) {
        if (strlen($request->get_body())>512) return self::forbidden();
        $body=$request->get_json_params();
        if (!is_array($body)) return self::forbidden();
        $at=$body['at']??null;$nonce=$body['nonce']??null;
        $key=$request->get_header('x-joyrent-dispatch');
        if (!is_int($at)||$at<time()-60||$at>time()+5
            ||!is_string($nonce)||!preg_match('/^[a-f0-9]{32}$/D',$nonce)
            ||!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key)
            ||!hash_equals(self::signature($at,$nonce),$key)) return self::forbidden();
        return true;
    }

    /** At most three recipient steps; an in-flight API call has its own eight-second timeout. */
    public static function run(): void {
        $owner=false;$continue=false;$start=microtime(true);
        try {
            $owner=self::acquire_briefly('jrtg_dispatch_worker',self::LEASE);
            if (!$owner) return;
            self::$running=true;
            if (!self::change(static function(array &$s):void {
                $s['last_start']=time();unset($s['error'],$s['launch_at']);
            })) return;
            $continue=true;
            for ($step=0;$step<self::MAX_STEPS&&microtime(true)-$start<self::BUDGET;$step++) {
                $id=self::next_job();
                if ($id===null) break;
                $state=self::status();$generation=(string)($state['generations'][$id]??'');
                $before=JRTG_Notifications::inspect($id);
                if ($before['due']!==null&&$before['due']<=time()) JRTG_Notifications::deliver($id);
                // Compare authoritative outbox state under the registry lock. A newer wake wins.
                if (!self::replace_job($id,$generation,$before)) {$continue=false;break;}
            }
            if (!self::change(static function(array &$s):void {$s['last_finish']=time();})) $continue=false;
        } catch (Throwable $ignored) {
            $continue=false;
            self::change(static function(array &$s):void {$s['error']='worker_failed';});
        } finally {
            self::$running=false;
            if ($owner) {
                try {JR_Lock::release('jrtg_dispatch_worker',$owner);} catch (Throwable $ignored) {}
                if ($continue&&self::ready()) self::kick();
            }
        }
    }

    public static function status(): array {
        wp_cache_delete(self::OPTION,'options');wp_cache_delete('notoptions','options');
        $state=get_option(self::OPTION,[]);
        if (!is_array($state)) $state=[];
        $state['jobs']=is_array($state['jobs']??null)?$state['jobs']:[];
        $state['generations']=is_array($state['generations']??null)?$state['generations']:[];
        return $state;
    }

    private static function change(callable $update): bool {
        $owner=false;
        try {
            $owner=self::acquire_briefly('jrtg_dispatch_registry',10);
            if (!$owner) return false;
            $state=self::status();$update($state);
            update_option(self::OPTION,$state,false);
            return self::status()===$state;
        } catch (Throwable $ignored) {return false;}
        finally {if ($owner) {try {JR_Lock::release('jrtg_dispatch_registry',$owner);} catch (Throwable $ignored) {}}}
    }
    /** Hard wait budget: yield to a short launch/registry operation without spinning. */
    private static function acquire_briefly(string $name,int $ttl) {
        $deadline=microtime(true)+0.25;
        do {
            $owner=JR_Lock::acquire($name,$ttl);
            if ($owner) return $owner;
            if (microtime(true)>=$deadline) return false;
            usleep(5000);
        } while (true);
    }
    private static function replace_job(int $id,string $generation,array $before): bool {
        return self::change(static function(array &$s)use($id,$generation,$before):void {
            if ((string)($s['generations'][$id]??'')!==$generation) return;
            $after=JRTG_Notifications::inspect($id);$at=$after['due'];
            unset($s['jobs'][$id],$s['generations'][$id]);
            if ($at===null) return;
            // Lock contention or failed state mutation must never create a loopback storm.
            if ($at<=time()&&hash_equals((string)$before['marker'],(string)$after['marker'])) $at=time()+30;
            $s['jobs'][$id]=max(time(),$at);$s['generations'][$id]=$generation;
        });
    }
    private static function next_job(): ?int {
        $state=self::status();
        foreach ($state['jobs'] as $id=>$at) if ((int)$id>0&&(int)$at<=time()) return (int)$id;
        // Recover missed concurrent wakes from the existing durable scheduler, without running it.
        $ids=JRTG_Notifications::pending_due_ids(array_keys($state['jobs']));
        $missing=array_filter($ids,static fn($id):bool=>!isset($state['jobs'][$id]));
        if (!$missing) return null;
        $candidates=[];
        // Order reads stay outside the small registry write section.
        foreach ($missing as $id) {
            $at=JRTG_Notifications::next_due($id);
            if ($at!==null&&$at<=time()) $candidates[$id]=$at;
        }
        if (!$candidates) return null;
        if (!self::change(static function(array &$s)use($candidates):void {
            foreach ($candidates as $id=>$at) {
                if (isset($s['jobs'][$id])) continue; // Never bypass a worker's deliberate backoff.
                $s['jobs'][$id]=$at;$s['generations'][$id]=bin2hex(random_bytes(16));
            }
        })) return null;
        foreach (self::status()['jobs'] as $id=>$at) if ((int)$id>0&&(int)$at<=time()) return (int)$id;
        return null;
    }
    private static function ready(): bool {return self::next_job()!==null;}
    private static function signature(int $at,string $nonce): string {
        return hash_hmac('sha256','dispatch|'.$at.'|'.$nonce,wp_salt('auth'));
    }
    private static function forbidden(): WP_Error {
        return new WP_Error('jrtg_dispatch_forbidden','Доступ запрещён.',['status'=>403]);
    }

    /** Signed nonblocking self-request; coalesce concurrent launch attempts. */
    private static function kick(): void {
        $probe=false;$launch=false;
        try {
            if ((int)(self::status()['launch_at']??0)>time()-5) return;
            $probe=JR_Lock::acquire('jrtg_dispatch_worker',self::LEASE);
            if (!$probe) return;
            if (!self::change(static function(array &$s)use(&$launch):void {
                if ((int)($s['launch_at']??0)>time()-5) return;
                $s['launch_at']=time();$launch=true;
            })||!$launch) return;
            JR_Lock::release('jrtg_dispatch_worker',$probe);$probe=false;
            $at=time();$nonce=bin2hex(random_bytes(16));
            $response=wp_remote_post(rest_url('joyrent-telegram/v1/dispatch'),[
                'timeout'=>1,'blocking'=>false,'redirection'=>0,'sslverify'=>true,
                'headers'=>['Content-Type'=>'application/json','X-JOYRENT-Dispatch'=>self::signature($at,$nonce)],
                'body'=>wp_json_encode(['at'=>$at,'nonce'=>$nonce]),'data_format'=>'body',
            ]);
            if (is_wp_error($response)) self::launch_failed();
        } catch (Throwable $ignored) {self::launch_failed();}
        finally {if ($probe) {try {JR_Lock::release('jrtg_dispatch_worker',$probe);} catch (Throwable $ignored) {}}}
    }
    private static function launch_failed(): void {
        self::change(static function(array &$s):void {$s['error']='loopback_failed';unset($s['launch_at']);});
    }
}
