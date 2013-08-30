<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_add_action extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        $itemId = $this->getParam('item_id');
        $qty = $this->getParam('qty');

        if (!isset($itemId)) {
            $errMesg = 'Item id is not specified .';
        }
        if (!is_numeric($qty) || intval($qty) <= 0) {
            $errMesg = 'Incorrect number of product.';
        }

        if ($errMesg) {
            $this->setError(KancartResult::ERROR_CART_INPUT_PARAMETER, $errMesg);
            return false;
        }
        return true;
    }

    public function execute() {
        global $cookie;
        $itemId = $this->getParam('item_id');
        $qty = $this->getParam('qty');
        $attributes = $_REQUEST['attributes'];
        $option = array();
        $errMesg = array();
        if ($attributes) {
            $attributes = json_decode(stripslashes(urldecode($attributes)));
            foreach ($attributes as $attribute) {
                $optionId = $attribute->attribute_id;
                $option[$optionId] = $attribute->value;
            }
        }
        $producToAdd = new Product($itemId, true, $cookie->id_lang);
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $idProductAttribute = $productTranslator->getIdProductAttribut($option);
        if (!$producToAdd->id OR !$producToAdd->active){
            $errMesg[] = Tools::displayError('Product is no longer available.', false);
        }
        else {
            if (!$producToAdd->isAvailableWhenOutOfStock($producToAdd->out_of_stock) && $producToAdd->hasAttributes() && !Attribute::checkAttributeQty($idProductAttribute, $qty)){
                $errMesg[] = Tools::displayError('There is not enough product in stock.');
            }
            $cartService = ServiceFactory::factory('ShoppingCart');
            $addResult = $cartService->add($itemId, $idProductAttribute, (int)$qty);
            if ($addResult < 0 && empty($errMesg)) {
                $errMesg[] = Tools::displayError('You must add', false) . ' ' . $producToAdd->minimal_quantity . ' ' . Tools::displayError('Minimum quantity', false);
            }
            if (!$addResult && empty($errMesg)) {
                $errMesg[] = Tools::displayError('You already have the maximum quantity available for this product.', false);
            }
            $info = $cartService->get();
            $info['messages'] = $errMesg;
            $this->setSuccess($info);
        }
    }

}

?>
