<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_user_register_action extends BaseAction {

    public function execute() {
        $userService = ServiceFactory::factory('User');
        $username = is_null($this->getParam('email')) ? '' : trim($this->getParam('email'));
        $enCryptedPassword = is_null($this->getParam('pwd')) ? '' : trim($this->getParam('pwd'));
        $password = CryptoUtil::Crypto($enCryptedPassword, 'AES-256', KANCART_APP_SECRET, false);
        if (empty($username) || !Validate::isEmail($username)) {
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA, 'Invalid e-mail address.');
            return;
        }
        if (empty($password)) {
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA, 'Password name is empty.');
            return;
        }
        if (!Tools::getValue('is_new_customer', 1) && !Configuration::get('PS_GUEST_CHECKOUT_ENABLED')){
             $this->setError(KancartResult::ERROR_USER_AUTHENTICATION_PROBLEM, 'You cannot create a guest account.');
             return;
        }
        $name = trim($this->getParam('lastname'));
        $firstname = is_null($this->getParam('firstname')) ? '  ' : trim($this->getParam('firstname'));
        $lastname = empty($name) ? '  ' : $name;
        $regisetInfo = array(
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $username,
            'password' => $password
        );
        $result = $userService->register($regisetInfo);
        if ($result!==true) {
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA, join('<br>', $result));
            return;
        }
        // succed registering
        $this->setSuccess();
    }

}

?>
