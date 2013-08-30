<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_checkout_start_action extends UserAuthorizedAction {

    public function validate() {
        if ($this->getParam('payment_method_id') == 'paypalec') {
            return true;
        }else{
            return parent::validate();
        }
    }

    public function execute() {
        switch ($_REQUEST['checkout_type']) {
            case 'cart':
                $paymentMethodID = $this->getParam('payment_method_id');
                if (!$paymentMethodID) {
                    $this->setError('', 'Payment_method_id is empty.');
                } else if ($paymentMethodID == 'paypalec') {
                    $actionInstance = ActionFactory::factory('KanCart.ShoppingCart.PaypalEC.Start');
                    $actionInstance->init();
                    $actionInstance->execute();
                    $this->result = $actionInstance->getResult();
                } else {
                    $actionInstance = ActionFactory::factory('KanCart.ShoppingCart.Checkout');
                    $actionInstance->init();
                    $actionInstance->execute();
                    $this->result = $actionInstance->getResult();
                }
                break;
            case 'order':
                break;
            default : break;
        }
    }

}

?>
