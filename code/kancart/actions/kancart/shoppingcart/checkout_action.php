<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_checkout_action extends UserAuthorizedAction {

    public function execute() {
        $payment = trim($_REQUEST['payment_method_id']);
        switch ($payment) {
            case 'paypalwpp':
                $this->paypalwpp();
                break;
            case 'paypal':
                $this->paypal();
                break;
            default:
                $this->payorder($payment);
                break;
        }
    }

    public function paypalwpp() { //paypal express checkout
        $expressCheckout = ServiceFactory::factory('PaypalExpressCheckout');
        $paypal_express = new PaypalExpressCheckout($_REQUEST['return_url'], $_REQUEST['cancel_url'], 'payment_cart');
        $result = $expressCheckout->startExpressCheckout($paypal_express);
        $result['paypal_redirect_url'] = str_replace('continue', 'commit', $result['paypal_redirect_url']);

        if (is_array($result) && isset($result['token'])) {
            $this->setSuccess($result);
        } else {
            $this->setError('', (join('<br/>', $paypal_express->logs)));
        }
    }

    public function paypal() { //Website Payments Standard
        $this->paypalwpp();
    }

    public function payorder($method) {
        global $cart;

        if (empty($method)) {
            $this->setError('', 'Error: payment_method_id is empty.');
        } elseif (!$cart->id || $cart->nbProducts() < 1) {
            $this->setError('', 'Error: ShoppingCart is empty.');
        } else {
            $payment = ServiceFactory::factory('KancartPayment');
            list($result, $order, $message) = $payment->placeOrder($method);
            if ($result === true) {
                $orderService = ServiceFactory::factory('Order');
                $info = $orderService->getPaymentOrderInfo($order);
                $this->setSuccess($info);
            } else {
                is_array($message) && $message = join('<br>', $message);
                $this->setError('', $message);
            }
        }
    }

}

?>