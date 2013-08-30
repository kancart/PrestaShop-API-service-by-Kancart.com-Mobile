<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_user_login_action extends BaseAction {

    public function execute() {
        global $cookie;

        if (!$cookie->logged) {
            $userService = ServiceFactory::factory('User');
            $username = is_null($this->getParam('uname')) ? '' : trim($this->getParam('uname'));
            if (empty($username)) {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, 'E-mail address required.');
                return;
            } else if (!Validate::isEmail($username)) {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, 'Invalid e-mail address.');
                return;
            }

            $encryptedPassword = is_null($this->getParam('pwd')) ? '' : trim($this->getParam('pwd'));
            $password = CryptoUtil::Crypto($encryptedPassword, 'AES-256', KANCART_APP_SECRET, false);
            if (empty($password)) {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, 'Password is required.');
                return;
            } else if (Tools::strlen($password) > 32) {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, 'Password is too long.');
                return;
            } else if (!Validate::isPasswd($password)) {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, 'Invalid password.');
                return;
            }

            $loginInfo = array(
                'email' => $username,
                'password' => $password
            );

            if (!$userService->login($loginInfo)) {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, 'email address or pasword worng.');
                return;
            }
        }
        $info = array('sessionkey' => md5($username . uniqid(mt_rand(), true)));
        $this->setSuccess($info);
    }

}

?>
