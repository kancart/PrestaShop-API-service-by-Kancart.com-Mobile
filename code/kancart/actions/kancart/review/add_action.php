<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_review_add_action extends UserAuthorizedAction {

    public function validate() {
        if (parent::validate()) {
            $content = $this->getParam('content');
            if (!isset($content) || $content == '') {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER);
                return false;
            }
            $item_id = $this->getParam('item_id');
            if (!isset($item_id) || $item_id == '') {
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
