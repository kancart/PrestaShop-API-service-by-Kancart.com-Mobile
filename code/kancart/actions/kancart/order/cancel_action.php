<?php
if (!defined('IN_KANCART'))
{
    header('HTTP/1.1 404 Not Found');
    die();
}


class kancart_order_cancel_action extends UserAuthorizedAction {

    public function execute() {
        $orderId = $this->getParam('order_id');
        if (isset($orderId)) {
            $this->setSuccess();
        }
    }

}

?>
