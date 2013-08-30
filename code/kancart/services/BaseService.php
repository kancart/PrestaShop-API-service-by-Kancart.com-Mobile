<?php

abstract class BaseService extends Module{

    protected $cookie;
    protected $cart;
    protected $priceDisplay;
    protected $withTax;
    protected $link;

    public function __construct() {
        global $cookie, $cart, $link;

        parent::__construct($this->name);

        $this->priceDisplay = Product::getTaxCalculationMethod(isset($cookie->id_customer) ? $cookie->id_customer : null);
        $this->withTax = (int) Configuration::get('PS_TAX');
        $this->cookie = $cookie;
        $this->cart = $cart;
  
        if ($link) {
            $this->link = $link;
        } elseif ($this->context && isset($this->context->link)) {
            $this->link = $this->context->link;
        } elseif (_PS_VERSION_ > '1.5') {
            $protocol_link = (Configuration::get('PS_SSL_ENABLED') OR Tools::usingSecureMode()) ? 'https://' : 'http://';
            $useSSL = ((isset($this->ssl) AND $this->ssl AND Configuration::get('PS_SSL_ENABLED')) OR Tools::usingSecureMode()) ? true : false;
            $protocol_content = ($useSSL) ? 'https://' : 'http://';
            $this->link = new Link($protocol_link, $protocol_content);
        } else {
            $this->link = new Link();
        }
    }  

    protected function languageTranslate($string) {
        global $_LANG;

        if (!isset($this->cache[$string])) {
            if (empty($_LANG)) {
                include_once _PS_THEME_DIR_ . 'lang/' . $this->cookie->iso . '.php';
            }

            if (strlen(key($_LANG)) > 32) {
                $langs = array();
                foreach ($_LANG as $key => $value) {
                    $langs[substr($key, -32)] = $value;
                }
                $_LANG = $langs;
            }

            $key = md5(str_replace('\'', '\\\'', $string));
            if (isset($_LANG[$key])) {
                $ret = stripslashes($_LANG[$key]);
            } else {
                $ret = stripslashes($string);
            }

            $this->cache[$string] = $ret;
        }

        return $this->cache[$string];
    }

}

?>
