<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * @author hujs
 */
class StoreService {

    /**
     * get store info
     * @author hujs
     */
    public function getStoreInfo() {
        $storeInfo = array();
        $storeInfo['general'] = $this->getGeneralInfo();
        $storeInfo['currencies'] = $this->getCurrencies();
        $storeInfo['countries'] = $this->getCountries();
        $storeInfo['zones'] = $this->getZones();
        $storeInfo['languages'] = $this->getLanguages();
        //$storeInfo['order_statuses'] = $this->getOrderStatauses();
        $storeInfo['register_fields'] = $this->getRegisterFields();
        $storeInfo['address_fields'] = $this->getAddressFields();
        $storeInfo['category_sort_options'] = $this->getCategorySortOptions();
        $storeInfo['search_sort_options'] = $this->getSearchSortOptions();
        return $storeInfo;
    }

    /**
     * get Languages
     * @global type $languages
     * @return string
     * @author hujs
     */
    public function getLanguages() {

        $info = array();
        $position = 0;
        $languages = Language::getLanguages();
        $defaultLanguageId = (int) Configuration::get('PS_LANG_DEFAULT');
        foreach ($languages as $language)
            $info[] = array(
                'default' => $defaultLanguageId == $language['id_lang'],
                'language_id' => $language['id_lang'],
                'language_code' => $language['iso_code'],
                'language_name' => $language['name'],
                'language_text' => $language['name'],
                'position' => $position++,
            );

        return $info;
    }

    /**
     * Only one can use Depending on the installation options
     * @return type
     * @author hujs
     */
    public function getCountries() {
        global $cookie;

        $countries = Country::getCountries($cookie->id_lang, true, null, null);
        foreach ($countries as $country) {
            $shopCountries[] = array(
                'country_id' => $country['id_country'],
                'country_name' => $country['name'],
                'country_iso_code_2' => $country['iso_code'],
                'country_iso_code_3' => $country['iso_code']
            );
        }
        return $shopCountries;
    }

    /**
     * get zones
     * @return type
     * @author hujs
     */
    public function getZones() {
        global $languages_id;

        $shopZones = array();
        $zones = State::getStates($languages_id);
        foreach ($zones as $zone) {
            $shopZones[] = array(
                'zone_id' => $zone['id_state'],
                'country_id' => $zone['id_country'],
                'zone_name' => $zone['name'],
                'zone_code' => $zone['iso_code']
            );
        }
        return $shopZones;
    }

    /**
     * get currencies
     * @return type
     * @author hujs
     */
    public function getCurrencies() {
        $currencies = Currency::getCurrencies();
        $shopCurencies = array();

        foreach ($currencies as $currency) {
            $format = (int) $currency['format'];
            $shopCurencies[] = array(
                'currency_code' => $currency['iso_code'],
                'default' => _PS_CURRENCY_DEFAULT_ == $currency['id_currency'],
                'currency_symbol' => $currency['sign'],
                'currency_symbol_right' => $format & 0x01 ? false : true,
                'decimal_symbol' => $format % 3 == 1 ? '.' : ',',
                'group_symbol' => ($currency['blank'] ? ' ' : ($format % 3 == 1 ? ',' : '.')),
                'decimal_places' => $currency['decimals'] * _PS_PRICE_DISPLAY_PRECISION_,
                'description' => $currency['name'],
            );
        }

        return $shopCurencies;
    }

    public function getOrderStatauses() {

        $orderStatuses = array();

        return $orderStatuses;
    }

    /**
     * affect auto login after register
     * @return type
     */
    public function getGeneralInfo() {
        return array(
            'cart_type' => 'prestashop',
            'cart_version' => _PS_VERSION_,
            'plugin_version' => KANCART_PLUGIN_VERSION,
            'support_kancart_payment' => true,
            'login_by_mail' => true
        );
    }

    /**
     * get register fields
     * @return type
     * @author hujs
     */
    public function getRegisterFields() {
        $registerFields = array(
            array('type' => 'firstname', 'required' => true),
            array('type' => 'lastname', 'required' => true),
            array('type' => 'email', 'required' => true),
            array('type' => 'pwd', 'required' => true),
        );
        return $registerFields;
    }

    /**
     * get address fileds
     * @return array
     * @author hujs
     */
    public function getAddressFields() {
        $addressFields = array(
            array('type' => 'firstname', 'required' => true),
            array('type' => 'lastname', 'required' => true),
            array('type' => 'country', 'required' => true),
            array('type' => 'zone', 'required' => false),
            array('type' => 'city', 'required' => true),
            array('type' => 'address1', 'required' => true),
            array('type' => 'address2', 'required' => false),
            array('type' => 'postcode', 'required' => false),
            array('type' => 'telephone', 'required' => false),
        );
        return $addressFields;
    }

    /**
     * get category sort options
     * @global type $language
     * @return string
     * @author hujs
     */
    public function getCategorySortOptions() {

        $PS_CATALOG_MODE = Configuration::get('PS_CATALOG_MODE');
        $categorySortOptions = array();

        $categorySortOptions[] = array(array(
                'title' => '--    ',
                'code' => 'name:asc',
                'arrow_type' => ''
                ));

        if (!$PS_CATALOG_MODE) {
            $categorySortOptions[] = array(array(
                    'title' => 'Price: lowest first',
                    'code' => 'price:asc',
                    'arrow_type' => ''
                    ));

            $categorySortOptions[] = array(array(
                    'title' => 'Price: highest first',
                    'code' => 'price:desc',
                    'arrow_type' => ''
                    ));
        }

        $categorySortOptions[] = array(array(
                'title' => 'Product Name: A to Z',
                'code' => 'name:asc',
                'arrow_type' => ''
                ));

        $categorySortOptions[] = array(array(
                'title' => 'Product Name: Z to A',
                'code' => 'name:desc',
                'arrow_type' => ''
                ));

        if (!$PS_CATALOG_MODE) {
            $categorySortOptions[] = array(array(
                    'title' => 'In stock',
                    'code' => 'quantity:desc',
                    'arrow_type' => ''
                    ));
        }

        return $categorySortOptions;
    }

    public function getSearchSortOptions() {
        return $this->getCategorySortOptions();
    }

}

?>
