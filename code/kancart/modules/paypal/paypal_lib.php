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

define('PAYPAL_API_VERSION', '94.0');

class KPaypalLib {

    private $_logs = array();
    protected $paypal = null;

    public function __construct($paypal) {
        $this->paypal = $paypal;
    }

    public function getLogs() {
        return $this->_logs;
    }

    public function makeCall($host, $script, $methodName, $data, $method_version = '') {
        // Making request string
        $method_version = (!empty($method_version)) ? $method_version : PAYPAL_API_VERSION;

        $params = array(
            'METHOD' => $methodName,
            'VERSION' => $method_version,
            'PWD' => Configuration::get('PAYPAL_API_PASSWORD'),
            'USER' => Configuration::get('PAYPAL_API_USER'),
            'SIGNATURE' => Configuration::get('PAYPAL_API_SIGNATURE')
        );

        $request = http_build_query($params, '', '&');
        $request .= '&' . (!is_array($data) ? $data : http_build_query($data, '', '&'));

        // Making connection
        $result = $this->makeSimpleCall($host, $script, $request, true);
        $response = explode('&', $result);

        foreach ($response as $value) {
            $tmp = explode('=', $value);
            $return[$tmp[0]] = urldecode(!isset($tmp[1]) ? $tmp[0] : $tmp[1]);
        }

        if (!Configuration::get('PAYPAL_DEBUG_MODE'))
            $this->_logs = array();

        $toExclude = array('TOKEN', 'SUCCESSPAGEREDIRECTREQUESTED', 'VERSION', 'BUILD', 'ACK', 'CORRELATIONID');
        $this->_logs[] = '<b>' . $this->paypal->l('PayPal response:') . '</b>';

        foreach ($return as $key => $value) {
            if (!Configuration::get('PAYPAL_DEBUG_MODE') && in_array($key, $toExclude))
                continue;
            $this->_logs[] = $key . ' -> ' . $value;
        }

        return $return;
    }

    public function makeSimpleCall($host, $script, $body, $simple_mode = false) {
        $this->_logs[] = $this->paypal->l('Making new connection to') . ' \'' . $host . $script . '\'';

        if (function_exists('curl_exec'))
            $return = $this->_connectByCURL($host . $script, $body);

        if (isset($return) && $return)
            return $return;

        $tmp = $this->_connectByFSOCK($host, $script, $body);

        if (!$simple_mode || !preg_match('/[A-Z]+=/', $tmp, $result))
            return $tmp;

        return substr($tmp, strpos($tmp, $result[0]));
    }

    /*     * ********************************************************* */
    /*     * ******************** CONNECT METHODS ******************** */
    /*     * ********************************************************* */

    private function _connectByCURL($url, $body) {
        $ch = @curl_init();

        if (!$ch)
            $this->_logs[] = $this->paypal->l('Connect failed with CURL method');
        else {
            $this->_logs[] = $this->paypal->l('Connect with CURL method successful');
            $this->_logs[] = '<b>' . $this->paypal->l('Sending this params:') . '</b>';
            $this->_logs[] = $body;

            @curl_setopt($ch, CURLOPT_URL, 'https://' . $url);
            @curl_setopt($ch, CURLOPT_POST, true);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_HEADER, false);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            @curl_setopt($ch, CURLOPT_SSLVERSION, 3);
            @curl_setopt($ch, CURLOPT_VERBOSE, true);

            $result = @curl_exec($ch);

            if (!$result)
                $this->_logs[] = $this->paypal->l('Send with CURL method failed ! Error:') . ' ' . curl_error($ch);
            else
                $this->_logs[] = $this->paypal->l('Send with CURL method successful');

            @curl_close($ch);
        }
        return $result ? $result : false;
    }

    private function _connectByFSOCK($host, $script, $body) {
        $fp = @fsockopen('sslv3://' . $host, 443, $errno, $errstr, 4);

        if (!$fp)
            $this->_logs[] = $this->paypal->l('Connect failed with fsockopen method');
        else {
            $header = $this->_makeHeader($host, $script, strlen($body));
            $this->_logs[] = $this->paypal->l('Connect with fsockopen method successful');
            $this->_logs[] = $this->paypal->l('Sending this params:') . ' ' . $header . $body;

            @fputs($fp, $header . $body);

            $tmp = '';
            while (!feof($fp))
                $tmp .= trim(fgets($fp, 1024));

            fclose($fp);

            if (!isset($result) || $result == false)
                $this->_logs[] = $this->paypal->l('Send with fsockopen method failed !');
            else
                $this->_logs[] = $this->paypal->l('Send with fsockopen method successful');
        }
        return $tmp ? $tmp : false;
    }

    private function _makeHeader($host, $script, $lenght) {
        return 'POST ' . (string) $script . ' HTTP/1.0' . "\r\n" .
                'Host: ' . (string) $host . "\r\n" .
                'Content-Type: application/x-www-form-urlencoded' . "\r\n" .
                'Content-Length: ' . (int) $lenght . "\r\n" .
                'Connection: close' . "\r\n\r\n";
    }

}
