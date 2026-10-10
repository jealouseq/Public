<?php
if (!defined('ABSPATH')) exit;

/** One plain-text manager notification, built at send time from private WooCommerce data. */
final class JRTG_Message {
    public static function for_order(WC_Order $order): string {
        $reference='JR-'.self::text((string)$order->get_order_number(),60);
        $lines=['Новая бронь JOYRENT · '.$reference,'Ожидает подтверждения. Оплата не получена.','',
            'Имя: '.self::text($order->get_billing_first_name(),140),
            'Телефон: '.self::text($order->get_billing_phone(),50)];
        $telegram=self::text((string)$order->get_meta('_joyrent_telegram'),50);
        if ($telegram!=='') $lines[]='Telegram клиента: '.$telegram;
        $language=(string)$order->get_meta('_joyrent_language');
        $lines[]='Язык клиента: '.($language==='ru'?'русский':'украинский');
        $lines[]='';
        $lines[]='Консоль: '.strtoupper(self::text((string)$order->get_meta('_joyrent_console'),12));
        $lines[]='Даты: '.self::date((string)$order->get_meta('_joyrent_start_date')).' — '.self::date((string)$order->get_meta('_joyrent_return_date'));
        $lines[]='Срок: '.(int)$order->get_meta('_joyrent_days').' дн.';
        $lines[]='Геймпады: '.(int)$order->get_meta('_joyrent_controllers');
        $pickup=$order->get_meta('_joyrent_method')==='pickup';
        $lines[]='Получение: '.($pickup?'самовывоз':'доставка');
        if (!$pickup) $lines[]='Адрес: '.self::text($order->get_billing_address_1(),300);
        $lines[]='';
        $lines[]='Аренда: '.self::money($order->get_meta('_joyrent_rental_amount'));
        $delivery=$order->get_meta('_joyrent_delivery');
        $lines[]='Доставка: '.($pickup?'самовывоз':(($delivery===''||$delivery==='pending')?'согласуем по адресу':self::money($delivery)));
        $extra=$order->get_meta('_joyrent_extra_controller');
        if ($extra==='pending'||(is_numeric($extra)&&(float)$extra>0)) $lines[]='Доп. геймпад: '.($extra==='pending'?'согласуем':self::money($extra));
        if ($order->get_meta('_joyrent_security_mode')==='contract') {
            $lines[]='Оформление: по договору — решение после проверки документов.';
            $lines[]='Залог: условия ещё не подтверждены.';
        } else {
            $deposit=$order->get_meta('_joyrent_deposit');
            $lines[]='Возвратный залог: '.(($deposit===''||$deposit==='pending')?'согласуем':self::money($deposit));
        }
        $lines[]='Итог и наличие подтвердим до аренды.';
        $titles=[];
        if (class_exists('JR_Games')) {
            foreach (JR_Games::records() as $game) if (is_array($game)&&isset($game['id'],$game['title'])) $titles[(string)$game['id']]=self::text((string)$game['title'],100);
        }
        $games=[];
        foreach ((array)$order->get_meta('_joyrent_game_ids') as $id) {
            if (!is_scalar($id)) continue;
            $id=(string)$id;
            $games[]=$titles[$id]??self::text($id,80);
        }
        if ($games) $lines[]='Игры: '.self::text(implode(', ',$games),950);
        $wish=self::text((string)$order->get_meta('_joyrent_requested_game'),450);
        if ($wish!=='') $lines[]='Пожелание по игре: '.$wish;
        $lines[]='';
        $lines[]='Открыть бронь: '.$order->get_edit_order_url();
        return self::text(implode("\n",$lines),3900,true);
    }

    private static function date(string $value): string {
        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D',$value,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])) return self::text($value,20);
        return $m[3].'.'.$m[2].'.'.$m[1];
    }
    private static function money($value): string {
        return is_numeric($value)?number_format((float)$value,0,',',' ').' грн':'согласуем';
    }
    private static function text(string $value,int $budget,bool $multiline=false): string {
        $value=wp_strip_all_tags($value);
        $value=preg_replace($multiline?'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u':'/[\x00-\x1F\x7F]/u',' ',$value)??'';
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
