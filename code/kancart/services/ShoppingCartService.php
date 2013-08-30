<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * ShoppingCart Service,Utility
 * @package services 
 * @author hujs
 */
class ShoppingCartService extends BaseService {
    
    public $name = 'blockcart';

    /**
     * Get Cart detailed information
     * @author hujs
     */
    public function get() {
        $result = array();
        $this->initShoppingCartGetReslut($result);
        $result['is_virtual'] = $this->cart->isVirtualCart();
        $result['cart_items'] = $this->getProducts();
        $result['price_infos'] = $this->getPriceInfo();
        $result['cart_items_count'] = $this->cart->nbProducts();
        $result['payment_methods'] = $this->getPaymentMethods();

        return $result;
    }

    private function getPaymentMethods() {
        $availablePayment = array();
        $modules = PaymentModule::getInstalledPaymentModules();
        foreach ($modules as $value) {
            if ($value['name'] == 'paypal') {
                $availablePayment[] = 'paypalec';
                break;
            }
        }

        return $availablePayment;
    }

    /**
     * get products information
     * @param type $this->cart
     * @return array
     * @author hujs
     */
    private function getProducts() {
        $items = array();
        $products = $this->cart->getProducts();

        foreach ($products as $product) {
            $item = array(
                'cart_item_id' => $product['id_product'] . ':' . $product['id_product_attribute'] . ':' . $product['minimal_quantity'],
                'cart_item_key' => '',
                'item_id' => $product['id_product'],
                'item_title' => $product['name'],
                'thumbnail_pic_url' => $this->link->getImageLink($product['link_rewrite'], $product['id_image'], 'home' . PIC_NAME_SUFFIX),
                'currency' => $this->cookie->currency,
                'item_price' => $product['price'],
                'item_original_price' => $product['price'],
                'qty' => $product['quantity'],
                'display_attributes' => empty($product['attributes']) ? '' : str_replace(',', '<br/> -', ' - ' . $product['attributes']),
                'item_url' => $this->link->getProductLink($product['id_product']),
                'short_description' => $product['description_short'],
                'post_free' => '',
            );
            $items[] = $item;
        }
        return $items;
    }

    /**
     * initialization of cart information
     * @param type $result
     * @author hujs
     */
    public function initShoppingCartGetReslut(&$result) {
        $result['cart_items_count'] = 0;
        $result['cart_items'] = array();
        $result['messages'] = array();
        $result['price_infos'] = array();
        $result['valid_to_checkout'] = true;
        $result['is_virtual'] = false;
    }

    /**
     * get price information
     * @return int
     * @author hujs
     */
    public function getPriceInfo() {
        $postion = 0;
        $total = $this->cart->getOrderTotal();
        $totalWithoutTax = $this->cart->getOrderTotal(false);
        $shipping = $this->cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        $priceInfo = array();
        $priceInfo[] = array(
            'title' => $this->l('Shipping'),
            'type' => 'shipping',
            'price' => $shipping,
            'home_currency_price' => '',
            'currency' => $this->cookie->currency,
            'position' => $postion++,
        );
        if ($this->withTax && $total > $totalWithoutTax) {
            $priceInfo[] = array(
                'title' => $this->l('Tax'),
                'type' => 'tax',
                'price' => $total - $totalWithoutTax,
                'home_currency_price' => '',
                'currency' => $this->cookie->currency,
                'position' => $postion++,
            );
        }
        if ($this->cart->gift) {
            $wrapping_fees = (float) (Configuration::get('PS_GIFT_WRAPPING_PRICE'));
            if ($this->withTax) {
                $wrapping_fees_tax = new Tax((int) (Configuration::get('PS_GIFT_WRAPPING_TAX')));
                $wrapping_fees *= 1 + (((float) ($wrapping_fees_tax->rate) / 100));
            }
            $wrapping_fees = Tools::convertPrice(Tools::ps_round($wrapping_fees, 2), Currency::getCurrencyInstance((int) ($this->cookie->id_currency)));
            $priceInfo[] = array(
                'title' => $this->l('Wrapping'),
                'type' => 'wrapping',
                'price' => $wrapping_fees,
                'home_currency_price' => '',
                'currency' => $this->cookie->currency,
                'position' => $postion++,
            );
        }
        $priceInfo[] = array(
            'title' => $this->l('Total'),
            'type' => 'total',
            'price' => $total,
            'home_currency_price' => '',
            'currency' => $this->cookie->currency,
            'position' => $postion++,
        );
        return $priceInfo;
    }

    /**
     * add goods into cart
     * @param type $goods
     * @return type 
     * @author hujs
     */
    public function add($productId, $idProductAttribute, $quantity = 1) {
        /* Product addition to the cart */
        if (!isset($this->cart->id) OR !$this->cart->id) {
            $this->cart->add();
            if ($this->cart->id)
                $this->cookie->id_cart = (int) ($this->cart->id);
        }
        return $this->cart->updateQty($quantity, $productId, $idProductAttribute);
    }

    /**
     * update product's quantity and attributes
     * in cart by product id
     * @param type $arr
     * @return type 
     * @author hujs
     */
    public function update($cartItemId, $quantity) {
        $newCartItemId = explode(":", $cartItemId);
        $itemId = $newCartItemId[0];
        $itemAttr = $newCartItemId[1];
        $this->cartItemNb = $this->cart->containsProduct($itemId, $itemAttr, FALSE);
        /* Product addition to the cart */
        if (!isset($this->cart->id) OR !$this->cart->id) {
            $this->cart->add();
            if ($this->cart->id)
                $this->cookie->id_cart = (int) ($this->cart->id);
        }
        if ($quantity < $this->cartItemNb['quantity']) {
            $operator = 'down';
            $qty = $this->cartItemNb['quantity'] - $quantity;
        } else {
            $operator = 'up';
            $qty = $quantity - $this->cartItemNb['quantity'];
        }
        return $this->cart->updateQty($qty, $itemId, $itemAttr, false, $operator);
    }

    /**
     * remove goods from cart by product key
     * @access  public
     * @param   integer $id
     * @return  void
     * @author hujs
     */
    public function remove($cartItemId) {
        $newCartItemId = explode(":", $cartItemId);
        $itemId = $newCartItemId[0];
        $itemAttr = $newCartItemId[1];
        /* Product addition to the cart */
        if (!isset($this->cart->id) OR !$this->cart->id) {
            $this->cart->add();
            if ($this->cart->id)
                $this->cookie->id_cart = (int) ($this->cart->id);
        }
        $this->cart->deleteProduct($itemId, $itemAttr);
    }

}

?>
