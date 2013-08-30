<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * Checkout utility
 * @package services
 * @author hujs
 */
class CheckoutService extends BaseService {

    private $errors = array();
    private $isFreeShipping = false;
    private $isVirtual = false;
    public $name = 'blockcart';

    /**
     * get checkout detail information
     * 
     */
    public function detail() {

        $detail = array();
        $detail['shipping_address'] = $this->getShippingAddress();
        $detail['billing_address'] = $this->getBillingAddress();
        $detail['review_orders'] = array($this->getReviewOrder());
        $detail['need_billing_address'] = defined('NEED_BILLING_ADDRESS') ? NEED_BILLING_ADDRESS : FALSE;
        $detail['need_shipping_address'] = FALSE;
        $detail['need_select_shipping_method'] = (!$this->isVirtual && !$detail['review_orders'][0]['selected_shipping_method_id']);

        $detail['payment_methods'] = $this->getPaymentMethods();
        $detail['price_infos'] = $this->getPriceInfos();
        $detail['is_virtual'] = $this->cart->isVirtualCart();
        $detail['messages'] = $this->errors;
        if (isset($this->cookie->guest_uname)) {
            $detail['uname'] = $this->cookie->guest_uname;
            unset($this->cookie->guest_uname);
        }
        return $detail;
    }

    public function getReviewOrder() {
        $order = array();
        $carriers = array();
        $order['cart_items'] = $this->getOrderItems();
        $order['shipping_methods'] = $this->getOrderShippingMethods($carriers);
        $selectedShippingMethod = $this->selectShippingMethod($carriers);
        $order['selected_shipping_method_id'] = $selectedShippingMethod;
        $this->cookie->id_carrier = $this->cart->id_carrier;   //save id_carrier
        $this->cookie->coupon && $order['coupon_code'] = $this->cookie->coupon;

        return $order;
    }

    private function getSendToAddressId() {

        if (!$this->cart->id_address_invoice) {
            $this->cart->id_address_invoice = Address::getFirstCustomerAddressId($this->cookie->id_customer);
        }

        return $this->cart->id_address_invoice;
    }

    public function getShippingAddress() {
        $sendTo = $this->getSendToAddressId();
        if ($sendTo) {
            $userService = ServiceFactory::factory('User');
            $addr = $userService->getAddress($sendTo);
            $addr['country_id'] = strval($addr['country_id']);
            return $addr;
        }
        return array();
    }

    public function getBillingAddress() {
        $billto = $this->getBillToAddressId();
        if ($billto) {
            $userService = ServiceFactory::factory('User');
            $billingAddress = $userService->getAddress($billto);
            $billingAddress['country_id'] = strval($billingAddress['country_id']);
            return $billingAddress;
        }
        return array();
    }

    public function getBillToAddressId() {

        if (!$this->cart->id_address_delivery) {
            $this->cart->id_address_delivery = Address::getFirstCustomerAddressId($this->cookie->id_customer);
        }

        return $this->cart->id_address_delivery;
    }

    public function getPaymentMethods() {
        $availablePayment = array();
        $paypalPayment = array();

        $modules = PaymentModule::getInstalledPaymentModules();
        foreach ($modules as $value) {
            if ($value['name'] == 'paypal') {
                $method = (int) Configuration::get('PAYPAL_PAYMENT_METHOD');
                if ($method) {
                    $paypalwpp['pm_id'] = 'paypalwpp';
                    $paypalwpp['pm_title'] = '';
                    $paypalwpp['pm_code'] = 'paypal_express';
                    $paypalwpp['pm_description'] = '';
                    $paypalwpp['img_url'] = '';
                    $paypalPayment = $paypalwpp;
                }
            }
        }

        if ($paypalPayment) {
            $availablePayment[] = $paypalPayment;
        }
        return $availablePayment;
    }

    public function selectShippingMethod($carriers) {
        if ($this->isFreeShipping || $this->isVirtual) {
            $this->cart->id_carrier = Carrier::getDefaultCarrierSelection($carriers, $this->cart->id_carrier);
            return 1;
        } else {
            $this->cart->id_carrier = Carrier::getDefaultCarrierSelection($carriers, $this->cart->id_carrier);
            if ($this->cart->update())
                return $this->cart->id_carrier;
        }
        return $this->cart->id_carrier;
    }

    public function getOrderShippingMethods(&$carriers) {
        $availableShippingMethods = array();

        if ($this->cart->isVirtualCart()) { //virtual cart
            $this->isVirtual = true;
        } else {  // Free fees
            $freePrice = Configuration::get('PS_SHIPPING_FREE_PRICE');
            $freeWeight = Configuration::get('PS_SHIPPING_FREE_WEIGHT');
            if (isset($freeWeight) && $this->cart->getTotalWeight() >= (float) ($freeWeight) && (float) ($freeWeight) > 0) {
                $title = $this->l('Free shipping!');
                $this->isFreeShipping = true;
            } else if (isset($freePrice)) {
                $freePrice = Tools::convertPrice((float) ($freePrice), Currency::getCurrencyInstance((int) ($this->cart->id_currency)));
                $orderTotalwithDiscounts = $this->cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);
                if ($orderTotalwithDiscounts >= (float) ($freePrice) && (float) ($freePrice) > 0) {
                    $title = $this->l('Free shipping!');
                    $this->isFreeShipping = true;
                }
            }
        }

        if ($this->cart->id_address_delivery)
            $deliveryAddress = new Address($this->cart->id_address_delivery);
        if ($this->cookie->id_customer) {
            $customer = new Customer((int) ($this->cookie->id_customer));
            $groups = $customer->getGroups();
        } else {
            $groups = array(1);
        }
        $carriers = Carrier::getCarriersForOrder((int) Country::getIdZone((isset($deliveryAddress) && (int) $deliveryAddress->id) ? (int) $deliveryAddress->id_country : (int) _PS_COUNTRY_DEFAULT_), $groups);

        if ($this->isVirtual) {
            return $availableShippingMethods;
        } else if (isset($title)) {
            $availableShippingMethods[] = array(
                'sm_id' => 1,
                'title' => $title,
                'price' => 0,
                'currency' => $this->cookie->currency,
                'description' => ''
            );

            return $availableShippingMethods;
        }

        foreach ($carriers as $carrier) {
            if (file_exists(_PS_ROOT_DIR_ . $carrier['img'])) {
                $title = '<img src="' . Tools::getHttpHost(true) . $carrier['img'] . '" alt="My carrier"> ' . $carrier['name'];
            } else {
                $title = $carrier['name'];
            }
            $shippingMethod = array();
            $shippingMethod['sm_id'] = $carrier['id_carrier'];
            $shippingMethod['title'] = $title;
            $shippingMethod['price'] = $carrier['price'];
            $shippingMethod['currency'] = $this->cookie->currency;
            $shippingMethod['description'] = $carrier['delay'];
            $availableShippingMethods[] = $shippingMethod;
        }

        return $availableShippingMethods;
    }

    public function getPriceInfos() {
        $shoppingCart = ServiceFactory::factory('ShoppingCart');
        return $shoppingCart->getPriceInfo();
    }

    public function getOrderItems() {
        $shoppingCart = ServiceFactory::factory('ShoppingCart');
        $cartInfo = $shoppingCart->get();
        return $cartInfo['cart_items'];
    }

    public function addAddress($address) {
        if ($this->cookie->isLogged()) {
            $result = ServiceFactory::factory('User')->addAddress($address);

            if (is_numeric($result)) {
                //for now,keep the two address same
                $this->cart->id_address_delivery = $result;
                $this->cart->id_address_invoice = $result;
            }

            return $result;
        }
    }

    public function updateAddress($addressBookId, $address = array()) {
        if ($this->cookie->isLogged()) {
            if ($addressBookId) {
                if ($address) {
                    $address['address_id'] = $addressBookId;
                    $userService = ServiceFactory::factory('User');
                    $userService->updateAddress($address);
                } else {
                    $this->cart->update();
                }
                //for now,keep the two address same
                $this->cart->id_address_delivery = $addressBookId;
                $this->cart->id_address_invoice = $addressBookId;
            }
        }
    }

    public function updateShippingMethod($shippingMethod) {
        if (is_numeric($shippingMethod) && $shippingMethod > 0) {
            $this->cart->id_carrier = intval($shippingMethod);  //affect cart shipping price calculate
            $this->cookie->id_carrier = $this->cart->id_carrier;   //save id_carrier
        }
    }

    public function updateCoupon($couponCode) {
        if (empty($couponCode)) {
            if (Validate::isUnsignedId($this->cookie->id_coupon)) {
                $this->cart->deleteDiscount($this->cookie->id_coupon);
                unset($this->cookie->coupon);
                unset($this->cookie->id_coupon);
            }
        } else {
            $errors = array();
            if (!Validate::isDiscountName($couponCode))
                $errors[] = Tools::displayError('Voucher name invalid.');
            else {
                $discount = new Discount((int) (Discount::getIdByName($couponCode)));
                if (Validate::isLoadedObject($discount)) {
                    if (($tmpError = $this->cart->checkDiscountValidity($discount, $this->cart->getDiscounts(), $this->cart->getOrderTotal(), $this->cart->getProducts(), true)))
                        $errors[] = $tmpError;
                }
                else
                    $errors[] = Tools::displayError('Voucher name invalid.');
                if (!sizeof($errors)) {
                    $this->cart->addDiscount((int) ($discount->id));
                    $this->cookie->coupon = $couponCode;
                    $this->cookie->id_coupon = $discount->id;
                    $this->cart->getDiscounts(false, true); //reflash cart discount
                }
            }
        }

        /* Is there only virtual product in cart */
        if ($this->cart->isVirtualCart()) {
            $this->cart->id_carrier = 0;
            $this->cart->update();
        }

        return $errors;
    }

}

?>
