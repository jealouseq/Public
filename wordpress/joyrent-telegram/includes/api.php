<?php
if (!defined('ABSPATH')) exit;

/** Fixed Telegram Bot API endpoints; neither token nor raw remote errors leave this class. */
final class JRTG_Api {
    public static function send_message(string $message,?array $snapshot=null): array {
        $settings=$snapshot??JRTG_Settings::get();
        if (!preg_match('/^[0-9]{6,15}:[A-Za-z0-9_-]{20,100}$/D',(string)($settings['token']??''))||!preg_match('/^-?[1-9][0-9]{0,19}$/D',(string)($settings['chat_id']??''))) return self::failure('not_configured');
        if ($message==='') return self::failure('invalid_message');
        $response=self::request('sendMessage',[
            'chat_id'=>$settings['chat_id'],'text'=>$message,
            'disable_web_page_preview'=>true,
        ],$settings);
        if (($response['status']??'')!=='ok') return $response;
        $result=$response['result']??null;
        if (!is_array($result)||!isset($result['message_id'])||!is_int($result['message_id'])||$result['message_id']<=0) {
            return ['status'=>'unknown','error'=>'invalid_ack'];
        }
        return ['status'=>'sent','message_id'=>$result['message_id']];
    }

    /** Read recent bot updates without acknowledging/removing them or changing any webhook. */
    public static function get_chats(): array {
        $response=self::request('getUpdates',['timeout'=>0,'limit'=>100,'allowed_updates'=>['message','channel_post','my_chat_member']]);
        if (($response['status']??'')!=='ok') return ['status'=>$response['status']??'failed','chats'=>[],'error'=>$response['error']??'invalid_response'];
        if (!is_array($response['result']??null)) return ['status'=>'unknown','chats'=>[],'error'=>'invalid_response'];
        $chats=[];
        foreach ($response['result'] as $update) {
            if (!is_array($update)) continue;
            foreach (['message','channel_post','my_chat_member'] as $kind) {
                $chat=$update[$kind]['chat']??null;
                if (!is_array($chat)||!isset($chat['id'])||(!is_int($chat['id'])&&!is_string($chat['id']))) continue;
                $id=(string)$chat['id'];
                if (!preg_match('/^-?[1-9][0-9]{0,19}$/D',$id)) continue;
                $name=$chat['title']??trim(($chat['first_name']??'').' '.($chat['last_name']??''));
                if (!is_string($name)) $name='';
                $label=sanitize_text_field($name);
                $label=self::cut($label,100);
                $chats[$id]=['id'=>$id,'label'=>$label!==''?$label:$id];
            }
        }
        return ['status'=>'ok','chats'=>array_values($chats)];
    }

    private static function request(string $method,array $payload,?array $snapshot=null): array {
        $token=(string)(($snapshot??JRTG_Settings::get())['token']??'');
        if (!preg_match('/^[0-9]{6,15}:[A-Za-z0-9_-]{20,100}$/D',$token)) return self::failure('not_configured');
        if (!in_array($method,['sendMessage','getUpdates'],true)) return self::failure('invalid_message');
        try {
            $response=wp_remote_post('https://api.telegram.org/bot'.$token.'/'.$method,[
                'timeout'=>15,'redirection'=>0,'sslverify'=>true,'blocking'=>true,
                'limit_response_size'=>65536,'headers'=>['Content-Type'=>'application/json'],
                'body'=>wp_json_encode($payload),'data_format'=>'body',
            ]);
        } catch (Throwable $e) {
            // The request could have reached Telegram; its outcome is uncertain.
            return ['status'=>'unknown','error'=>'http_unknown'];
        }
        if (is_wp_error($response)) return ['status'=>'unknown','error'=>'http_unknown'];
        $http=(int)wp_remote_retrieve_response_code($response);
        $body=wp_remote_retrieve_body($response);
        if (!is_string($body)||strlen($body)>=65536) return ['status'=>'unknown','error'=>'invalid_response'];
        $json=json_decode($body,true);
        if (!is_array($json)||!array_key_exists('ok',$json)||!is_bool($json['ok'])) return ['status'=>'unknown','error'=>'invalid_response'];
        if ($json['ok']===true) {
            if ($http<200||$http>=300||!array_key_exists('result',$json)) return ['status'=>'unknown','error'=>'invalid_ack'];
            return ['status'=>'ok','result'=>$json['result']];
        }
        $code=$json['error_code']??null;
        if (!is_int($code)) return ['status'=>'unknown','error'=>'invalid_response'];
        if ($code===429) {
            $delay=$json['parameters']['retry_after']??60;
            $delay=is_int($delay)?max(1,min(3600,$delay)):60;
            return ['status'=>'retry','error'=>'rate_limited','retry_after'=>$delay];
        }
        if ($code>=500&&$code<=599) return ['status'=>'retry','error'=>'telegram_server_error','retry_after'=>60];
        return self::failure(match($code){401=>'bot_unauthorized',403=>'chat_forbidden',400=>'bad_request',409=>'bot_webhook_conflict',default=>'telegram_error'});
    }

    private static function failure(string $error): array { return ['status'=>'failed','error'=>$error]; }
    private static function cut(string $text,int $length): string {
        $chars=preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY);
        return is_array($chars)?implode('',array_slice($chars,0,$length)):'';
    }
}
