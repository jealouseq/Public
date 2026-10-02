<?php
if (!defined('ABSPATH')) exit;

final class JR_Orders {
    public static function register_status(): void {
        register_post_status('wc-jr-request',['label'=>'Заявка на оренду','public'=>true,'exclude_from_search'=>false,'show_in_admin_all_list'=>true,'show_in_admin_status_list'=>true,'label_count'=>_n_noop('Заявка на оренду <span class="count">(%s)</span>','Заявки на оренду <span class="count">(%s)</span>','joyrent-rentals')]);
    }
    public static function statuses(array $statuses): array { $statuses['wc-jr-request']='Заявка на оренду'; return $statuses; }
    public static function existing(string $key, string $fingerprint): ?array {
        $orders=wc_get_orders(['limit'=>1,'joyrent_request_key'=>$key,'meta_query'=>[['key'=>'_joyrent_request_key','value'=>$key]]]);
        if (!$orders) return null;
        $order=$orders[0];
        if (!hash_equals((string)$order->get_meta('_joyrent_fingerprint'),$fingerprint)) throw new InvalidArgumentException('Параметри заявки змінилися. Онови сторінку та спробуй ще раз.');
        if ($order->get_meta('_joyrent_completed')!=='yes') throw new RuntimeException('Заявка потребує перевірки магазином.');
        return ['reference'=>'JR-'.$order->get_order_number(),'rentalAmount'=>(float)$order->get_meta('_joyrent_rental_amount'),'status'=>'awaiting_confirmation'];
    }
    public static function create(array $data, string $key, string $fingerprint): array {
        $product=JR_Store::product($data['console'],$data['days']);
        if (!$product||$product->get_status()!=='publish'||$product->get_price()===''||(float)$product->get_price()<=0) throw new RuntimeException('Цей комплект тимчасово недоступний.');
        $amount=(float)$product->get_price(); $settings=JR_Settings::public();
        $order=new WC_Order();
        $order->set_status('checkout-draft'); $order->set_created_via('joyrent');
        // A durable key is stored in the first save, before customer/order items.
        // An interrupted worker can never create a second order on retry.
        $order->update_meta_data('_joyrent_request_key',$key);
        $order->update_meta_data('_joyrent_fingerprint',$fingerprint);
        $order->save();
        try {
            $order->set_currency('UAH'); $order->set_billing_first_name($data['name']); $order->set_billing_phone($data['phone']); $order->set_billing_address_1($data['address']); $order->set_billing_country('UA');
            $item_id=$order->add_product($product,1,['subtotal'=>$amount,'total'=>$amount]);
            $item=$order->get_item($item_id);
            foreach (['Консоль'=>strtoupper($data['console']),'Термін'=>$data['days'].' дн.','Отримання'=>$data['startDate'],'Повернення'=>$data['returnDate'],'Геймпади'=>$data['controllers']] as $key=>$value) $item->add_meta_data($key,$value,true);
            $item->save();
            $delivery=$data['method']==='pickup'?0:($settings['deliveryFee']===null?null:($data['days']>=$settings['freeDeliveryFrom']?0:$settings['deliveryFee']));
            $extra_count=max(0,$data['controllers']-$settings['baseControllers']);
            $extra=$extra_count?($settings['extraControllerFee']===null?null:$settings['extraControllerFee']*$extra_count):0;
            foreach (['Доставка'=>$delivery,'Додатковий геймпад'=>$extra] as $title=>$value) if ($value!==null&&$value>0) {
                $fee=new WC_Order_Item_Fee(); $fee->set_name($title); $fee->set_amount($value); $fee->set_total($value); $fee->set_tax_status('none'); $order->add_item($fee);
            }
            $deposit=$data['console']==='ps5'?$settings['depositPs5']:$settings['depositPs4'];
            foreach (['console'=>$data['console'],'days'=>$data['days'],'start_date'=>$data['startDate'],'return_date'=>$data['returnDate'],'controllers'=>$data['controllers'],'game_ids'=>$data['gameIds'],'method'=>$data['method'],'deposit'=>$deposit===null?'pending':$deposit,'delivery'=>$delivery===null?'pending':$delivery,'extra_controller'=>$extra===null?'pending':$extra,'consent'=>'yes','consent_version'=>'1.0','language'=>$data['language']??'uk'] as $key=>$value) $order->update_meta_data('_joyrent_'.$key,$value);
            $order->add_order_note('Мова клієнта: '.(($data['language']??'uk')==='ru'?'Російська':'Українська'));
            $order->add_order_note('Заявка JOYRENT: доступність консолі, ігор, адреса доставки та умови застави потребують підтвердження. Оплату не отримано. Бажані ігри: '.implode(', ',$data['gameIds']));
            $order->set_customer_note('Дата отримання: '.$data['startDate'].'. Повернення: '.$data['returnDate'].'. Геймпадів: '.$data['controllers'].'. Спосіб отримання: '.$data['method']);
            $order->update_meta_data('_joyrent_rental_amount',$amount);
            $order->update_meta_data('_joyrent_completed','yes');
            $order->set_status('jr-request');
            $order->calculate_totals(false); $order->save();
            return ['reference'=>'JR-'.$order->get_order_number(),'rentalAmount'=>$amount,'status'=>'awaiting_confirmation'];
        } catch (Throwable $exception) { $order->delete(true); throw $exception; }
    }
}
