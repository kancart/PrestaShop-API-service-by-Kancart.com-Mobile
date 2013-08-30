<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class KancartPaymentService extends PaymentModule {

    public function __construct() {
        parent::__construct();
        $this->active = true;
    }

    /**
     * payment from mobile
     * @param type $method
     * @return type\
     */
    public function placeOrder($method) {
        global  $cookie, $cart;

        $id_cart = $cookie->id_cart;
        $id_order_state = Configuration::get('PS_OS_CHEQUE');
        $message = 'from mobile payment ' . $method;
        $extraVars = array('transaction_id' => '', 'payment_status' => 'PENDING');
        $amountPaid = (float)(Tools::ps_round((float)($cart->getOrderTotal(true, Cart::BOTH)), 2));
        $result = $this->validateOrder($id_cart, $id_order_state, $amountPaid, $method, $message, $extraVars, NULL, false, $cart->secure_key);
        if ($result === true) {
            unset($cookie->id_cart);
            unset($cookie->id_order);
            $order = new Order($this->currentOrder);
        }
        return array($result, $order, NULL);
    }

    public function kancartPaymentDone($order_id, $custom_kc_comments, $payment_status) {
        $status = (strtolower($payment_status) == 'succeed') ? Configuration::get('PS_OS_PAYMENT') : Configuration::get('PS_OS_CHEQUE');
        $orderHistory = new OrderHistory();
        $orderHistory->id_order = (int)($order_id);
        $orderHistory->changeIdOrderState($status, $order_id);
        $orderHistory->addWithemail();
        $msg = new Message();
        $message = strip_tags($custom_kc_comments, '<br>');
        if (Validate::isCleanHtml($message)) {
            $msg->message = $message;
            $msg->id_order = intval($order_id);
            $msg->private = 1;
            $msg->add();
        }
        $order = new Order($order_id);
        return array(TRUE, $order);
    }

}

?>
