<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalec_start_action extends BaseAction {

    public function execute() {
        $expressCheckout = ServiceFactory::factory('PaypalExpressCheckout');
        $paypal_express = new PaypalExpressCheckout($_REQUEST['return_url'], $_REQUEST['cancel_url'], 'payment_cart');
        $result = $expressCheckout->startExpressCheckout($paypal_express);
        if (is_array($result) && isset($result['token'])) {
            $this->setSuccess($result);
        } else {
            $this->setError('', (join('<br/>', $paypal_express->logs)));
        }
    }

}

?>
