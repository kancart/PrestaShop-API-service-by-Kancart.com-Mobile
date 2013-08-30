<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalec_pay_action extends BaseAction {

    public function execute() {
        $expressCheckoutService = ServiceFactory::factory('PaypalExpressCheckout');
        $paypal_express = new PaypalExpressCheckout();
        /*         * * when customer checkout first we needn't return for confirm cart information ** */
        $paypal_express->payer_id || $expressCheckoutService->returnFromPaypal($paypal_express);

        $order = $expressCheckoutService->pay($paypal_express);
        if ($order) {
            $orderService = ServiceFactory::factory('Order');
            $info = $orderService->getPaymentOrderInfo($order);
            $this->setSuccess($info);
        } else {
            $this->setError('', join('<br/>', $paypal_express->logs));
        }
    }

}

?>
