<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalec_detail_action extends BaseAction {

    public function execute() {
        $expressCheckout = ServiceFactory::factory('PaypalExpressCheckout');
        $paypal_express = new PaypalExpressCheckout($_REQUEST['return_url'], $_REQUEST['cancel_url']);
        $expressCheckout->returnFromPaypal($paypal_express);
        $checkoutService = ServiceFactory::factory('Checkout');
        $this->setSuccess($checkoutService->detail());
    }

}

?>
