<?php

/*
 * 2007-2013 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2013 PrestaShop SA
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

define('WPS', 1);
define('HSS', 2);
define('ECS', 4);

define('TRACKING_CODE', 'FR_PRESTASHOP_H3S');
define('SMARTPHONE_TRACKING_CODE', 'Prestashop_Cart_smartphone_EC');
define('TABLET_TRACKING_CODE', 'Prestashop_Cart_tablet_EC');
define('_PAYPAL_MODULE_DIRNAME_', 'paypal');

class KPayPal extends PaymentModule {

    public $_errors = array();
    public $context;
    public $iso_code;
    public $default_country;

    const DEFAULT_COUNTRY_ISO = 'GB';

    public function __construct() {
        $this->name = 'paypal';
        $this->tab = 'payments_gateways';
        $this->version = '3.5.1';

        $this->currencies = true;
        $this->getContext();

        parent::__construct();
        $this->displayName = $this->l('PayPal');
        $this->description = $this->l('Accepts payments by credit cards (CB, Visa, MasterCard, Amex, Aurore, Cofinoga, 4 stars) with PayPal.');

        if (self::isInstalled($this->name)) {
            $this->loadDefaults();
        }
    }

    public function getContext() {
        if (is_null($this->context)) {
            global $cookie, $cart, $smarty, $link, $context;

            if ($context) {
                $this->context = $context;
            } else {
                $this->context = new stdClass();
                $this->context->cookie = $cookie;
                $this->context->cart = $cart;
                $this->context->smarty = $smarty;
                $this->context->link = $link;
                $this->context->customer = new Customer($cookie->id_customer);
            }
        }

        return $this->context;
    }

    public function getTrackingCode() {

        return SMARTPHONE_TRACKING_CODE;
    }

    /**
     * Initialize default values
     */
    protected function loadDefaults() {
        $paypal_country_default = (int) Configuration::get('PAYPAL_COUNTRY_DEFAULT');
        $this->default_country = ($paypal_country_default ? (int) $paypal_country_default : (int) Configuration::get('PS_COUNTRY_DEFAULT'));
        $this->iso_code = $this->getCountryDependency(Country::getIsoById((int) $this->default_country));

        if ($this->context->cart === false)
            unset($this->context->cookie->express_checkout);
    }

    public function getPayPalURL() {
        return 'www' . (Configuration::get('PAYPAL_SANDBOX') ? '.sandbox' : '') . '.paypal.com';
    }

    public function getPaypalIntegralEvolutionUrl() {
        if (Configuration::get('PAYPAL_SANDBOX'))
            return 'https://' . $this->getPayPalURL() . '/cgi-bin/acquiringweb';
        return 'https://securepayments.paypal.com/acquiringweb?cmd=_hosted-payment';
    }

    public function getPaypalStandardUrl() {
        return 'https://' . $this->getPayPalURL() . '/cgi-bin/webscr';
    }

    public function getAPIURL() {
        return 'api-3t' . (Configuration::get('PAYPAL_SANDBOX') ? '.sandbox' : '') . '.paypal.com';
    }

    public function getAPIScript() {
        return '/nvp';
    }

    public function getCountryDependency($iso_code) {
        $localizations = array(
            'AU' => array('AU'), 'BE' => array('BE'), 'CN' => array('CN', 'MO'), 'CZ' => array('CZ'), 'DE' => array('DE'), 'ES' => array('ES'),
            'FR' => array('FR'), 'GB' => array('GB'), 'HK' => array('HK'), 'IL' => array('IL'), 'IN' => array('IN'), 'IT' => array('IT', 'VA'),
            'JP' => array('JP'), 'MY' => array('MY'), 'NL' => array('AN', 'NL'), 'NZ' => array('NZ'), 'PL' => array('PL'), 'PT' => array('PT', 'BR'),
            'RA' => array('AF', 'AS', 'BD', 'BN', 'BT', 'CC', 'CK', 'CX', 'FM', 'HM', 'ID', 'KH', 'KI', 'KN', 'KP', 'KR', 'KZ', 'LA', 'LK', 'MH',
                'MM', 'MN', 'MV', 'MX', 'NF', 'NP', 'NU', 'OM', 'PG', 'PH', 'PW', 'QA', 'SB', 'TJ', 'TK', 'TL', 'TM', 'TO', 'TV', 'TZ', 'UZ', 'VN',
                'VU', 'WF', 'WS'),
            'RE' => array('IE', 'ZA', 'GP', 'GG', 'JE', 'MC', 'MS', 'MP', 'PA', 'PY', 'PE', 'PN', 'PR', 'LC', 'SR', 'TT',
                'UY', 'VE', 'VI', 'AG', 'AR', 'CA', 'BO', 'BS', 'BB', 'BZ', 'CL', 'CO', 'CR', 'CU', 'SV', 'GD', 'GT', 'HN', 'JM', 'NI', 'AD', 'AE',
                'AI', 'AL', 'AM', 'AO', 'AQ', 'AT', 'AW', 'AX', 'AZ', 'BA', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BV', 'BW', 'BY', 'CD', 'CF', 'CG',
                'CH', 'CI', 'CM', 'CV', 'CY', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ET', 'FI', 'FJ', 'FK', 'FO', 'GA', 'GE', 'GF',
                'GH', 'GI', 'GL', 'GM', 'GN', 'GQ', 'GR', 'GS', 'GU', 'GW', 'GY', 'HR', 'HT', 'HU', 'IM', 'IO', 'IQ', 'IR', 'IS', 'JO', 'KE', 'KM', 'KW',
                'KY', 'LB', 'LI', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MD', 'ME', 'MF', 'MG', 'MK', 'ML', 'MQ', 'MR', 'MT', 'MU', 'MW', 'MZ', 'NA',
                'NC', 'NE', 'NG', 'NO', 'NR', 'PF', 'PK', 'PM', 'PS', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SC', 'SD', 'SE', 'SI', 'SJ', 'SK', 'SL',
                'SM', 'SN', 'SO', 'ST', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TN', 'UA', 'UG', 'VC', 'VG', 'YE', 'YT', 'ZM', 'ZW'),
            'SG' => array('SG'), 'TH' => array('TH'), 'TR' => array('TR'), 'TW' => array('TW'), 'US' => array('US'));

        foreach ($localizations as $key => $value)
            if (in_array($iso_code, $value))
                return $key;

        return $this->getCountryDependency(self::DEFAULT_COUNTRY_ISO);
    }

    public function getCountryCode() {
        $cart = new Cart((int) $this->context->cookie->id_cart);
        $address = new Address((int) $cart->id_address_invoice);
        $country = new Country((int) $address->id_country);

        return $country->iso_code;
    }

    public function configure() {
        Configuration::updateValue('PAYPAL_BUSINESS', (int) Tools::getValue('business'));
        Configuration::updateValue('PAYPAL_PAYMENT_METHOD', (int) Tools::getValue('paypal_payment_method'));
        Configuration::updateValue('PAYPAL_API_USER', trim(Tools::getValue('api_username')));
        Configuration::updateValue('PAYPAL_API_PASSWORD', trim(Tools::getValue('api_password')));
        Configuration::updateValue('PAYPAL_API_SIGNATURE', trim(Tools::getValue('api_signature')));
        Configuration::updateValue('PAYPAL_BUSINESS_ACCOUNT', trim(Tools::getValue('api_business_account')));
        Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT', (int) Tools::getValue('express_checkout_shortcut'));
        Configuration::updateValue('PAYPAL_SANDBOX', (int) Tools::getValue('sandbox_mode'));
        Configuration::updateValue('PAYPAL_CAPTURE', (int) Tools::getValue('payment_capture'));
        $paypal_country_default = (int) Configuration::get('PAYPAL_COUNTRY_DEFAULT');
        $this->default_country = ($paypal_country_default ? (int) $paypal_country_default : (int) Configuration::get('PS_COUNTRY_DEFAULT'));
        $this->iso_code = $this->getCountryDependency(Country::getIsoById((int) $this->default_country));
    }

    public static function getPayPalCustomerIdByEmail($email) {
        return Db::getInstance()->getValue('
			SELECT `id_customer`
			FROM `' . _DB_PREFIX_ . 'paypal_customer`
			WHERE paypal_email = \'' . pSQL($email) . '\'');
    }

    public static function getPayPalEmailByIdCustomer($id_customer) {
        return Db::getInstance()->getValue('
			SELECT `paypal_email`
			FROM `' . _DB_PREFIX_ . 'paypal_customer`
			WHERE `id_customer` = ' . (int) $id_customer);
    }

    public static function addPayPalCustomer($id_customer, $email) {
        if (!self::getPayPalEmailByIdCustomer($id_customer)) {
            Db::getInstance()->Execute('
				INSERT INTO `' . _DB_PREFIX_ . 'paypal_customer` (`id_customer`, `paypal_email`)
				VALUES(' . (int) $id_customer . ', \'' . pSQL($email) . '\')');

            return Db::getInstance()->Insert_ID();
        }

        return false;
    }

    public function formatMessage($response, &$message) {
        foreach ($response as $key => $value)
            $message .= $key . ': ' . $value . '<br>';
    }

    public function validateOrder($id_cart, $id_order_state, $amountPaid, $paymentMethod = 'Unknown', $message = null, $transaction = array(), $currency_special = null, $dont_touch_amount = false, $secure_key = false, Shop $shop = null) {
        if ($this->active) {
            // Set transaction details if pcc is defined in PaymentModule class_exists
            if (isset($this->pcc))
                $this->pcc->transaction_id = (isset($transaction['transaction_id']) ? $transaction['transaction_id'] : '');

            if (_PS_VERSION_ < '1.5')
                parent::validateOrder((int) $id_cart, (int) $id_order_state, (float) $amountPaid, $paymentMethod, $message, $transaction, $currency_special, $dont_touch_amount, $secure_key);
            else
                parent::validateOrder((int) $id_cart, (int) $id_order_state, (float) $amountPaid, $paymentMethod, $message, $transaction, $currency_special, $dont_touch_amount, $secure_key, $shop);

            if (count($transaction) > 0)
                KPayPalOrder::saveOrder((int) $this->currentOrder, $transaction);
        }
    }

    protected function getGiftWrappingPrice() {
        if (_PS_VERSION_ >= '1.5')
            $wrapping_fees_tax_inc = $this->context->cart->getGiftWrappingPrice();
        else {
            $wrapping_fees = (float) (Configuration::get('PS_GIFT_WRAPPING_PRICE'));
            $wrapping_fees_tax = new Tax((int) (Configuration::get('PS_GIFT_WRAPPING_TAX')));
            $wrapping_fees_tax_inc = $wrapping_fees * (1 + (((float) ($wrapping_fees_tax->rate) / 100)));
        }

        return (float) Tools::convertPrice($wrapping_fees_tax_inc, $this->context->currency);
    }

}
