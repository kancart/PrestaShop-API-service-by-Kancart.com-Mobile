<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_update_action extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        $cartItemId = $this->getParam('cart_item_id');
        $qty = $this->getParam('qty');
        $validateInfo = array();
        if (!isset($cartItemId)) {
            $validateInfo[] = 'Cart item id is not specified .';
        }
        if (!isset($qty) || !is_numeric($qty) || $qty <= 0) {
            $validateInfo[] = 'Qty is not valid.';
        }
        if ($validateInfo) {
            $this->setError(KancartResult::ERROR_CART_INPUT_PARAMETER, $validateInfo);
            return false;
        }
        return true;
    }

    public function execute() {
        global $cookie;
        $cartItemId = $this->getParam('cart_item_id');
        $qty = intval($this->getParam('qty')) > 0 ? intval($this->getParam('qty')) : 1;
        $newCartItemId = explode(":", $cartItemId);
        $itemId = $newCartItemId[0];
        $itemAttr = $newCartItemId[1];
        $minQty = $newCartItemId[2];
        $producToAdd = new Product($itemId, true, $cookie->id_lang);
        if (!$producToAdd->isAvailableWhenOutOfStock($producToAdd->out_of_stock) && $producToAdd->hasAttributes() && !Attribute::checkAttributeQty($itemAttr, $qty)) {
            $errMesg[] = Tools::displayError('There is not enough product in stock.');
        }
        if ($qty < $minQty) {
            $errMesg[] = Tools::displayError('You must add', false) . ' ' . $minQty . ' ' . Tools::displayError('Minimum quantity', false);
        }
        $cartService = ServiceFactory::factory('ShoppingCart');
        $updateResult = $cartService->update($cartItemId, $qty);
        if (!$updateResult && empty($errMesg)) {
            $errMesg[] = Tools::displayError('You already have the maximum quantity available for this product.', false);
        }
        $info = $cartService->get();
        $info['messages'] = $errMesg;
        $this->setSuccess($info);
    }

}

?>
