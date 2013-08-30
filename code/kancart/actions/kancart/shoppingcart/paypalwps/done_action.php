<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalwps_done_action extends BaseAction {

    public function execute() {
        global $cookie;

        $order = new Order($cookie->id_order);
        $paypalWpsService = ServiceFactory::factory('PaypalWps');
        $paypalWpsService->paypalWpsDone();
        $tx = max($_REQUEST['tx'], $_REQUEST['txn_id']);
        $orderService = ServiceFactory::factory('Order');
        $info = $orderService->getPaymentOrderInfo($order, $tx);
        $this->setSuccess($info);
    }

}

?>
