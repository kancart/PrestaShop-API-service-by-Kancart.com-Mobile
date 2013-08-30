<?php

error_reporting(0);
ini_set('display_errors', false);
define('IN_KANCART', true);
define('API_VERSION', '1.1');

define('KANCART_ROOT', str_replace('\\', '/', dirname(__FILE__)));
define('PS_ROOT', str_replace('\\', '/', dirname(dirname(__FILE__))));

require_once PS_ROOT . '/config/config.inc.php';

$_POST['SubmitCurrency'] = true;
if (isset($_REQUEST['language'])) {
    if (is_numeric($_REQUEST['language'])) {
        $_POST['id_lang'] = intval($_REQUEST['language']);
    } elseif (Validate::isLanguageIsoCode($_REQUEST['language'])) {
        $_POST['id_lang'] = Language::getIdByIso($_REQUEST['language']);
    } else {
        $_POST['id_lang'] = (int) Configuration::get('PS_LANG_DEFAULT');
    }
} else {
    $_POST['id_lang'] = (int) Configuration::get('PS_LANG_DEFAULT');
}
if (isset($_REQUEST['currency'])) {
    $_POST['id_currency'] = is_numeric($_REQUEST['currency']) ? intval($_REQUEST['currency']) : Currency::getIdByIsoCode($_REQUEST['currency']);
} else {
    $_POST['id_currency'] = (int) _PS_CURRENCY_DEFAULT_;
}

require_once PS_ROOT . '/init.php';
require_once KANCART_ROOT . '/KancartHelper.php';

kc_include_once(KANCART_ROOT . '/ErrorHandler.php');
kc_include_once(KANCART_ROOT . '/Logger.php');
kc_include_once(KANCART_ROOT . '/configure.php');
kc_include_once(KANCART_ROOT . '/Exceptions.php');
kc_include_once(KANCART_ROOT . '/ActionFactory.php');
kc_include_once(KANCART_ROOT . '/ServiceFactory.php');
kc_include_once(KANCART_ROOT . '/services/BaseService.php');
kc_include_once(KANCART_ROOT . '/actions/BaseAction.php');
kc_include_once(KANCART_ROOT . '/actions/UserAuthorizedAction.php');
kc_include_once(KANCART_ROOT . '/util/CryptoUtil.php');
kc_include_once(KANCART_ROOT . '/KancartResult.php');
kc_include_once(KANCART_ROOT . '/common-functions.php');

try {
    $actionInstance = ActionFactory::factory(isset($_REQUEST['method']) ? $_REQUEST['method'] : '');
    $actionInstance->init();
    if ($actionInstance->validate()) {
        $actionInstance->execute();
    }
    $result = $actionInstance->getResult();
    die(json_encode($result->returnResult()));
} catch (EmptyMethodException $e) {
    die('KanCart OpenAPI v' . API_VERSION . ' is installed on Prestashop v' . _PS_VERSION_ . '. Prestashop Plugin v' . KANCART_PLUGIN_VERSION);
} catch (Exception $e) {
    die(json_encode(array('result' => KancartResult::STATUS_FAIL, 'code' => KancartResult::ERROR_UNKNOWN_ERROR, 'info' => $e->getMessage() . ',' . $e->getTraceAsString())));
}
?>
