<?php
if (!defined('ABSPATH')) exit;

/** Compact Telegram HTML, built at send time from private WooCommerce data. */
final class JRTG_Message {
    public static function for_order(WC_Order $order): string {
        $reference='JR-'.self::text((string)$order->get_order_number(),40);
        $days=max(0,(int)$order->get_meta('_joyrent_days'));
        $controllers=max(0,(int)$order->get_meta('_joyrent_controllers'));
        $console=strtoupper(self::text((string)$order->get_meta('_joyrent_console'),12));
        $lines=['🎮 <b>Новая бронь · '.self::escape($reference).'</b>','<i>Ожидает подтверждения</i>','',
            '<b>'.self::escape($console!==''?$console:'Консоль согласуем').'</b> · '.
                ($days>0?$days.' '.self::plural($days,['день','дня','дней']):'срок согласуем').' · '.
                ($controllers>0?$controllers.' '.self::plural($controllers,['геймпад','геймпада','геймпадов']):'геймпады согласуем'),
            '<b>'.self::escape(self::dates((string)$order->get_meta('_joyrent_start_date'),(string)$order->get_meta('_joyrent_return_date'))).'</b>'];
        $pickup=$order->get_meta('_joyrent_method')==='pickup';
        if ($pickup) {
            $lines[]='Самовывоз';
        } else {
            $delivery=$order->get_meta('_joyrent_delivery');
            $price=is_numeric($delivery)?((float)$delivery===0.0?'включена':self::money($delivery)):'стоимость согласуем';
            $lines[]='Доставка · '.self::escape($price);
            $address=self::text($order->get_billing_address_1(),240);
            if ($address!=='') $lines[]=self::escape($address);
        }
        $lines[]='';
        $name=self::text($order->get_billing_first_name(),120);
        $lines[]='<b>'.self::escape($name!==''?$name:'Клиент').'</b> · '.($order->get_meta('_joyrent_language')==='ru'?'RU':'UA');
        $phone=self::text($order->get_billing_phone(),50);
        if ($phone!=='') $lines[]=self::escape($phone);
        $telegram=self::text((string)$order->get_meta('_joyrent_telegram'),50);
        if ($telegram!=='') $lines[]='Telegram: '.self::escape($telegram);
        $lines[]='';
        $lines[]='<b>Аренда · '.self::escape(self::money($order->get_meta('_joyrent_rental_amount'))).'</b>';
        $extra=$order->get_meta('_joyrent_extra_controller');
        if ($extra==='pending'||(is_numeric($extra)&&(float)$extra>0)) $lines[]='Доп. геймпад · '.self::escape($extra==='pending'?'согласуем':self::money($extra));
        if ($order->get_meta('_joyrent_security_mode')==='contract') {
            $lines[]='Договор и залог: согласуем после проверки документов.';
        } else {
            $lines[]='Возвратный залог · '.self::escape(self::money($order->get_meta('_joyrent_deposit')));
        }
        $ids=[];
        foreach ((array)$order->get_meta('_joyrent_game_ids') as $id) if (is_scalar($id)&&(string)$id!=='') $ids[]=(string)$id;
        if ($ids) {
            $titles=[];
            if (class_exists('JR_Games')) {
                foreach (JR_Games::records() as $game) if (is_array($game)&&isset($game['id'],$game['title'])) $titles[(string)$game['id']]=self::text((string)$game['title'],100);
            }
            $lines[]='';
            $lines[]='<b>Игры</b>';
            foreach (array_slice($ids,0,3) as $id) $lines[]='• '.self::escape($titles[$id]??self::text($id,100));
            if (count($ids)>3) $lines[]='<i>и ещё '.(count($ids)-3).' — в броне</i>';
        }
        $wish=self::text((string)$order->get_meta('_joyrent_requested_game'),300);
        if ($wish!=='') {
            $lines[]='';
            $lines[]='<b>Пожелание по игре</b>';
            $lines[]=self::escape($wish);
        }
        // Each dynamic field is normalized and bounded before escaping. Never cut serialized HTML.
        return implode("\n",$lines);
    }

    /** A single native button works on mobile and desktop without exposing a long URL in text. */
    public static function options_for_order(WC_Order $order): array {
        $options=['parse_mode'=>'HTML'];
        $url=(string)$order->get_edit_order_url();
        if (self::trusted_admin_url($url)) $options['reply_markup']=['inline_keyboard'=>[[['text'=>'Открыть бронь','url'=>$url]]]];
        return $options;
    }

    private static function trusted_admin_url(string $url): bool {
        if ($url===''||strlen($url)>2048||str_contains($url,'\\')||preg_match('/[\s\x00-\x1F\x7F]/u',$url)!==0) return false;
        $parts=wp_parse_url($url);$admin=wp_parse_url(admin_url('/'));
        if (!is_array($parts)||!is_array($admin)||!in_array($parts['scheme']??'',['http','https'],true)
            ||($parts['scheme']??'')!==($admin['scheme']??'')||empty($parts['host'])
            ||strcasecmp($parts['host'],(string)($admin['host']??''))!==0
            ||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])) return false;
        $default=$parts['scheme']==='https'?443:80;
        if (($parts['port']??$default)!==($admin['port']??$default)) return false;
        $root=rtrim((string)($admin['path']??'/wp-admin/'),'/').'/';
        return in_array($parts['path']??'',[$root.'admin.php',$root.'post.php'],true);
    }

    private static function dates(string $start,string $end): string {
        $a=self::date_parts($start);$b=self::date_parts($end);
        if (!$a||!$b) return (self::text($start,20)?:'согласуем').' — '.(self::text($end,20)?:'согласуем');
        $left=$a[3].'.'.$a[2].($a[1]===$b[1]?'':'.'.$a[1]);
        return $left.' — '.$b[3].'.'.$b[2].'.'.$b[1];
    }
    private static function date_parts(string $value): array {
        return preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D',$value,$m)&&checkdate((int)$m[2],(int)$m[3],(int)$m[1])?$m:[];
    }
    private static function plural(int $number,array $forms): string {
        $last=$number%10;$lastTwo=$number%100;
        return $last===1&&$lastTwo!==11?$forms[0]:($last>=2&&$last<=4&&($lastTwo<12||$lastTwo>14)?$forms[1]:$forms[2]);
    }
    private static function money($value): string {
        return is_numeric($value)&&is_finite((float)$value)&&(float)$value>=0?number_format((float)$value,0,',',' ').' грн':'согласуем';
    }
    private static function escape(string $value): string {
        return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
    private static function text(string $value,int $budget): string {
        $value=wp_strip_all_tags($value);
        $value=preg_replace('/[\x00-\x1F\x7F]/u',' ',$value)??'';
        $chars=preg_split('//u',$value,-1,PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) return '';
        $out='';$units=0;
        foreach ($chars as $char) {
            $next=strlen($char)===4?2:1;
            if ($units+$next>$budget-1) return rtrim($out).'…';
            $out.=$char;$units+=$next;
        }
        return trim($out);
    }
}
