<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_checkout_done_action extends UserAuthorizedAction {

    public function execute() {
        switch ($_REQUEST['checkout_type']) {
            case 'cart':
                if ($_REQUEST['payment_method_id'] == 'paypal') {
                    $actionInstance = ActionFactory::factory('KanCart.ShoppingCart.PayPalWPS.Done');
                    $actionInstance->init();
                    $actionInstance->execute();
                    $this->result = $actionInstance->getResult();
                } else if ($_REQUEST['payment_method_id'] == 'paypalwpp') {
                    $actionInstance = ActionFactory::factory('KanCart.ShoppingCart.PayPalEC.Pay');
                    $actionInstance->init();
                    $actionInstance->execute();
                    $this->result = $actionInstance->getResult();
                } else {
                    $kancartPaymentService = ServiceFactory::factory('KancartPayment');
                    list($result, $order) = $kancartPaymentService->kancartPaymentDone($_REQUEST['order_id'], $_REQUEST['custom_kc_comments'], $_REQUEST['payment_status']);
                    if ($result === TRUE) {
                        $orderService = ServiceFactory::factory('Order');
                        $info = $orderService->getPaymentOrderInfo($order);
                        $this->setSuccess($info);
                    } else {
                        $this->setError('0xFFFF', $order);
                    }
                }
            case 'order':
                break;
            default : break;
        }
    }

}

?>
