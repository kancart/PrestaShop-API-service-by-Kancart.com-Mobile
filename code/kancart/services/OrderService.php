<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * Order Service, Utility
 * @package services 
 */
class OrderService extends BaseService {

    /** new
     * get user orders information
     * @param type $userId
     * @return type 
     * @author hujs
     */
    public function getOrderInfos(array $parameter) {
        $orderInfos = array();

        $pageNo = $parameter['page_no'];
        $pageSize = $parameter['page_size'];

        $orders = $this->getOrderList($pageNo, $pageSize);
        foreach ($orders as $value) {
            $order = new Order($value['id_order']);
            $orderItem = array();

            $this->initOrderDetail($orderItem, $order);
            $orderItem['price_infos'] = $this->getPriceInfos($orderItem, $order);
            $orderItem['order_items'] = $this->getOrderItems($order);
            $orderItem['order_status'] = $this->getOrderHistory($order);

            $orderInfos[] = $orderItem;
        }

        return array('total_results' => $this->getUserOrderCounts(), 'orders' => $orderInfos);
    }

    /**
     * get order detail information
     * @param type $orderId
     * @return type
     * @author hujs
     */
    public function getOneOrderInfoById($orderId) {
        $orderItem = array();

        $order = $this->getOneOrder($orderId);
        $this->initOrderDetail($orderItem, $order);
        $orderItem['price_infos'] = $this->getPriceInfos($orderItem, $order);
        $orderItem['order_items'] = $this->getOrderItems($order);
        $orderItem['order_status'] = $this->getOrderHistory($order);
        $orderItem['shipping_address'] = $this->getShippingAddress($order);
        $orderItem['billing_address'] = $this->getBillingAddress($order, $orderItem['shipping_address']);

        return $orderItem;
    }

    public function getPaymentOrderInfo($order, $tx = '') {
        $orderItem = array();
        $orderId = false;

        if ($order) {
            $orderItem['display_id'] = $orderId = $order->id;
            $orderItem['shipping_address'] = $this->getPaymentAddress($order);
            $orderItem['price_infos'] = $this->getPaymentPriceInfos($order);
            $orderItem['order_items'] = $this->getPaymentOrderItems($order);

            $total = $order->total_paid;
            $currencyDate = Currency::getCurrency($order->id_currency);
            $currency = $currencyDate['iso_code'];
        } else {
            $total = 0;
            $currency = $this->cookie->currency;
        }

        return array(
            'transaction_id' => $tx,
            'payment_total' => $total,
            'currency' => $currency,
            'order_id' => $orderId,
            'orders' => sizeof($orderItem) ? array($orderItem) : false
        );
    }

    public function getPaymentAddress($order) {
        $addr = ServiceFactory::factory('User')->getAddress($order->id_address_invoice);
        $addrress = array(
            'city' => $addr['city'],
            'country_id' => $addr['country_id'],
            'zone_id' => '', //1
            'zone_name' => '', //2
            'state' => $addr['state'], //3
            'address1' => $addr['address1'],
            'address2' => $addr['address2'],
        );

        return $addrress;
    }

    public function getPaymentPriceInfos($order) {
        $info = array();

        $info[] = array(
            'type' => 'total',
            'home_currency_price' => $order->total_paid
        );

        $info[] = array(
            'type' => 'shipping',
            'home_currency_price' => $order->total_shipping
        );

        $tax = $order->getTotalProductsWithTaxes() - $order->getTotalProductsWithoutTaxes();
        $info[] = array(
            'type' => 'tax',
            'home_currency_price' => $tax
        );

        return $info;
    }

    public function getPaymentOrderItems($order) {
        $items = array();
        $cart = new Cart($order->id_cart);
        $products = $cart->getProducts();
        foreach ($products as $product) {
            $items[] = array(
                'order_item_key' => $product['id_product'],
                'item_title' => $product['name'],
                'category_name' => '',
                'home_currency_price' => $product['price_wt'],
                'qty' => $product['cart_quantity']
            );
        }

        return $items;
    }

    /**
     * get one order detail information
     * @param type $orderItem
     * @param type $order
     * @author hujs
     */
    public function initOrderDetail(&$orderItem, $order) {

        $payMethod = array('pm_id' => '',
            'title' => $order->payment,
            'description' => '');

        $orderState = OrderHistory::getLastOrderState($order->id);
        $currency = Currency::getCurrency($order->id_currency);
        $orderItem = array('order_id' => $order->id,
            'display_id' => $order->id, //show id
            'uname' => $this->cookie->customer_firstname . ' ' . $this->cookie->customer_lastname,
            'currency' => $currency['iso_code'],
            'shipping_address' => array(),
            'billing_address' => array(),
            'payment_method' => $payMethod,
            'shipping_insurance' => 0,
            'coupon' => '',
            'order_status' => array(),
            'last_status_id' => $orderState->id,
            'order_tax' => $order->cattier_tax_rate,
            'order_date_start' => $order->date_added,
            'order_date_finish' => '',
            'order_date_purchased' => $order->date_added);
    }

    /**
     * get order ship address
     * @param $order
     * @return array
     * @author hujs
     */
    private function getShippingAddress($order) {

        $addr = ServiceFactory::factory('User')->getAddress($order->id_address_invoice);
        $country = new Country($addr['country_id']);
        $zone = new Zone($country->id_zone);

        $address = array('address_book_id' => $addr['address_book_id'],
            'address_type' => 'ship',
            'lastname' => $addr['lastname'],
            'firstname' => $addr['firstname'],
            'telephone' => $addr['telephone'],
            'mobile' => $addr['mobile'],
            'gender' => '',
            'postcode' => $addr['postcode'],
            'city' => $addr['city'],
            'zone_id' => $zone->id,
            'zone_code' => $zone->iso_code,
            'zone_name' => $zone->name,
            'state' => $addr['state'],
            'address1' => $addr['address1'],
            'address2' => $addr['address2'],
            'country_id' => strval($country->id),
            'country_code' => $country->iso_code,
            'country_name' => is_array($country->name) ? $country->name[1] : $country->name,
            'company' => $addr['company']);

        return $address;
    }

    /**
     * get order bill address
     * @param $order
     * @return array
     * @author hujs
     */
    private function getBillingAddress($order, $shippingAddress = false) {

        if ($order->id_address_delivery == $order->id_address_invoice && $shippingAddress) {
            $address = $shippingAddress;
            $address['address_type'] = 'bill';

            return $address;
        }

        $addr = ServiceFactory::factory('User')->getAddress($order->id_address_delivery);
        $country = new Country($addr['country_id']);
        $zone = new Zone($country->id_zone);

        $address = array('address_book_id' => $addr['address_book_id'],
            'address_type' => 'bill',
            'lastname' => $addr['lastname'],
            'firstname' => $addr['firstname'],
            'telephone' => $addr['telephone'],
            'mobile' => $addr['mobile'],
            'gender' => '',
            'postcode' => $addr['postcode'],
            'city' => $addr['city'],
            'zone_id' => $zone->id,
            'zone_code' => $zone->iso_code,
            'zone_name' => $zone->name,
            'state' => $addr['state'],
            'address1' => $addr['address1'],
            'address2' => $addr['address2'],
            'country_id' => strval($country->id),
            'country_code' => $country->iso_code,
            'country_name' => is_array($country->name) ? $country->name[1] : $country->name,
            'company' => $addr['company']);

        return $address;
    }

    /**
     * get order price information
     * @global type $currencies
     * @param array $order
     * @author hujs
     */
    public function getPriceInfos(&$orderItem, $order) {
        $info = array();
        $postion = 0;

        $carrier = new Carrier($order->id_carrier);
        $currency = new Currency($order->id_currency);
        $orderItem['shipping_method'] = array('pm_id' => '',
            'title' => $carrier->name,
            'description' => '',
            'price' => $order->total_shipping);

        if ($this->priceDisplay && $this->withTax) {
            $info[] = array(
                'title' => $this->l('Total products (tax excl.):'),
                'type' => 'tax',
                'price' => $order->getTotalProductsWithoutTaxes(),
                'currency' => $currency->iso_code,
                'position' => $postion++);
        }

        $info[] = array(
            'title' => $this->l('Total products' . ($this->withTax ? ' (tax incl.):' : ':')),
            'type' => 'subtotal',
            'price' => $order->getTotalProductsWithTaxes(),
            'currency' => $currency->iso_code,
            'position' => $postion++);

        if ($order->total_discounts > 0) {
            $info[] = array(
                'title' => $this->l('Total vouchers:'),
                'type' => 'discount',
                'price' => $order->total_discounts,
                'currency' => $currency->iso_code,
                'position' => $postion++);
        }

        if ($order->total_wrapping > 0) {
            $info[] = array(
                'title' => $this->l('Total vouchers:'),
                'type' => 'vouchers',
                'price' => $order->total_wrapping,
                'currency' => $currency->iso_code,
                'position' => $postion++);
        }

        $info[] = array(
            'title' => $this->l('Total shipping' . ($this->withTax ? ' (tax incl.):' : ':')),
            'type' => 'shipping',
            'price' => $order->total_shipping,
            'currency' => $currency->iso_code,
            'position' => $postion++);

        $info[] = array(
            'title' => $this->l('Total:'),
            'type' => 'total',
            'price' => $order->total_paid,
            'currency' => $currency->iso_code,
            'position' => $postion++);


        return $info;
    }

    /**
     * get order items
     * @param array $order
     * @return array
     * @author hujs
     */
    public function getOrderItems($order) {
        $items = array();
        $cart = new Cart($order->id_cart);
        $products = $cart->getProducts();

        foreach ($products as $row) {
            $productId = $row['id_product'];
            $items[] = array('order_item_id' => $productId . ':' . $row['product_attribute_id'],
                'item_id' => $productId,
                'display_id' => $productId,
                'order_item_key' => '',
                'display_attributes' => isset($row['attributes']) ? str_replace(',', '<br/> -', ' - ' . $row['attributes']) : '',
                'attributes' => '',
                'item_title' => $row['product_name'],
                'thumbnail_pic_url' => $this->link->getImageLink($row['link_rewrite'], $row['id_image'], 'home' . PIC_NAME_SUFFIX),
                'qty' => $row['cart_quantity'],
                'price' => $row['price_wt'],
                'final_price' => $row['price_wt'],
                'home_currency_price' => $row['price_wt'],
                'item_tax' => '',
                'shipping_method' => '',
                'post_free' => false,
                'virtual_flag' => false);
        }

        return $items;
    }

    /**
     * get order history information by id
     * @param type $orderId
     * @return type
     * @author hujs
     */
    public function getOrderHistory($order) {

        $info = array();
        $postion = 0;

        $rows = $order->getHistory($this->cookie->id_lang);
        foreach ($rows as $row) {
            $info[] = array('status_id' => $row['id_order_state'],
                'status_name' => $row['ostate_name'],
                'display_text' => $row['ostate_name'],
                'language_id' => $this->cookie->id_lang,
                'date_added' => $row['date_added'],
                'comments' => '',
                'position' => $postion++);
        }

        return $info;
    }

    /**
     * get orders information
     * @param type $userId
     * @return array
     * @author hujs
     */
    public function getOrderList($pageNo, $pageSize) {
        global $cookie;

        $start = ($pageNo - 1) * $pageSize;
        $userId = $cookie->id_customer;

        $orders = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS("
			SELECT id_order
			FROM `" . _DB_PREFIX_ . "orders`
                        WHERE id_customer =  $userId
			ORDER BY `date_add` DESC
			LIMIT $start, $pageSize");

        return $orders;
    }

    /**
     * get one order information by order id 
     * @param type $orderId
     * @return type
     */
    public function getOneOrder($orderId) {

        return new Order($orderId);
    }

    /**
     * get user order count
     * @param type $userId
     * @return int
     * @author hujs
     */
    public function getUserOrderCounts() {
        global $cookie;

        return (isset($cookie->id_customer)) ? Order::getCustomerNbOrders($cookie->id_customer) : 0;
    }
    
    public function l($string, $specific = false) {
        return parent::languageTranslate($string);
    }

}

?>
