<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_order_checkout_action extends UserAuthorizedAction {

    public function execute() {
        global $cookie;

        $errors = array();
        $id_order = intval($this->getParam('order_id'));
        $payment_method_id = trim($this->getParam('payment_method_id'));

        $oldCart = new Cart(Order::getCartIdStatic($id_order, $cookie->id_customer));
        $duplication = $oldCart->duplicate();
        if (!$duplication OR !Validate::isLoadedObject($duplication['cart']))
            $errors[] = Tools::displayError('Sorry, we cannot renew your order.');
        elseif (!$duplication['success'])
            $errors[] = Tools::displayError('Missing items - we are unable to renew your order');

        if (sizeof($errors)) {
            $this->setError('', join('<br/>', $errors));
        } else if ($payment_method_id == 'paypalwpp') {
            $expressCheckout = ServiceFactory::factory('PaypalExpressCheckout');
            $paypal_express = new kancart_paypal_express($_REQUEST['return_url'], $_REQUEST['cancel_url'], 'payment_cart');
            $result = $expressCheckout->startExpressCheckout($paypal_express);
            if (isset($result['token']) && $result['token']) {
                $this->setSuccess($result);
            } else {
                $this->setError('', (join('<br/>', $paypal_express->logs)));
            }
        }
    }

}

?>
