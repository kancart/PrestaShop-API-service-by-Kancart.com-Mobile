<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_reviews_get_action extends BaseAction {

    public function validate() {
        if (parent::validate()) {
            $itemId = $this->getParam('item_id');
            if (!isset($itemId) || $itemId == '') {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER);
                return false;
            }
        }
        return true;
    }

    public function execute() {
       $this->setError (array(array('This system does not support comments.')));
    }

}

?>
