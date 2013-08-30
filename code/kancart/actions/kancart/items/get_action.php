<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_items_get_action extends BaseAction {

    public function execute() {
        if ($_REQUEST['order_by']) {
            $sortOptions = explode(':', $_REQUEST['order_by']);
            $_REQUEST['sort_by'] = $sortOptions[0];
            $sortOptions[1] ? $_REQUEST['sort_order'] = $sortOptions[1] : $_REQUEST['sort_order'] = 'desc';
        }
        if ($_REQUEST['item_ids']) {
            $_REQUEST['item_ids'] = explode(',', $_REQUEST['item_ids']);
        }
        $productService = ServiceFactory::factory('Product');
        $this->setSuccess($productService->getProducts($_REQUEST));
    }

}

?>
