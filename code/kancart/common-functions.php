<?php

init();

/**
 * used to initializing the store
 * @global type $currency
 * @global type $currencies
 */
function init() {
    global $cookie, $cart;

    init_lanaguage();
    init_currency();

    if (method_exists('ImageType', 'getFormatedName')) {
        define('PIC_NAME_SUFFIX', ImageType::getFormatedName(''));
    } else {
        define('PIC_NAME_SUFFIX', '');
    }


    /* Cart already exists read cart id from cookie */
    if ((int) $cookie->id_cart && $cart) {
        if (isset($cookie->id_carrier)) {
            $cart->id_carrier = $cookie->id_carrier;   //save id_carrier
        }
    }
}

/**
 * Tools::setCurrency()
 * Currency::getCurrent()
 * @global type $currency
 */
function init_currency() {
    //here we expose the $currency to the globals
    global $cookie;

    if (isset($cookie->id_currency)) {
        $id_currency = Tools::getValue('id_currency');
        if (intval($id_currency) && $cookie->id_currency != $id_currency) {
            $cookie->id_currency = $id_currency;
            _PS_VERSION_ > '1.5' && Context::getContext()->currency = new Currency($cookie->id_currency);
        }
    } else {
        $cookie->id_currency = (int) _PS_CURRENCY_DEFAULT_;
    }
    $currency = Currency::getCurrency($cookie->id_currency);
    $cookie->currency = $currency['iso_code'];
    $cookie->decimals = $currency['decimals'] * _PS_PRICE_DISPLAY_PRECISION_;

    unset($_POST['SubmitCurrency']);
    unset($_POST['id_currency']);
}

/**
 * set current language
 * @global type $language
 * @global type $languages_id
 * @global type $config
 * @author hujs
 */
function init_lanaguage() {
    global $cookie;

    if (isset($cookie->id_lang)) {
        $id_lang = Tools::getValue('id_lang');
        if (intval($id_lang) && $cookie->id_lang != $id_lang) {
            $cookie->id_lang = $id_lang;
        }
    } else {
        $cookie->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
    }

    $cookie->iso = Language::getIsoById($cookie->id_lang);
    unset($_POST['id_lang']);
}

/**
 * get current currecy price
 * @global type $currency
 * @global type $currencies
 * @param type $price
 * @return type
 * @author hujs
 */
function currency_price_value($price) {
    global $cookie;

    $price = Tools::convertPrice($price, $cookie->id_currency);

    return Tools::ps_round($price, $cookie->decimals);
}

function prepare_address() {
    $address = array(
        'lastname' => isset($_REQUEST['lastname']) ? trim($_REQUEST['lastname']) : '',
        'firstname' => isset($_REQUEST['firstname']) ? trim($_REQUEST['firstname']) : '',
        'country_id' => isset($_REQUEST['country_id']) ? intval($_REQUEST['country_id']) : 0,
        'zone_id' => isset($_REQUEST['zone_id']) ? intval($_REQUEST['zone_id']) : 0,
        'city' => isset($_REQUEST['city']) ? trim($_REQUEST['city']) : '',
        'address_1' => isset($_REQUEST['address1']) ? trim($_REQUEST['address1']) : '',
        'address_2' => isset($_REQUEST['address2']) ? trim($_REQUEST['address2']) : '',
        'postcode' => isset($_REQUEST['postcode']) ? trim($_REQUEST['postcode']) : '',
        'telephone' => isset($_REQUEST['telephone']) ? trim($_REQUEST['telephone']) : ''
    );

    $address['state'] = isset($_REQUEST['state']) ? trim($_REQUEST['state']) : $_REQUEST['zone_name'];
    if (isset($_REQUEST['address_book_id']) && intval($_REQUEST['address_book_id']) > 0) {
        $address['address_id'] = intval($_REQUEST['address_book_id']);
    }
    return $address;
}

?>
