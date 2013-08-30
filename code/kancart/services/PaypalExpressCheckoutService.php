<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

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

kc_include_once(KANCART_ROOT . '/modules/paypal/process.php');

class PaypalExpressCheckoutService {

    public function startExpressCheckout($ppec) { //step one
        if ($ppec->type) {

            if ($ppec->context->cart->id == false) {
                // Create new Cart to avoid any refresh or other bad manipulations
                $ppec->context->cart = new Cart();

                $ppec->context->cart->id_currency = (int) $ppec->context->cookie->id_currency;
                $ppec->context->cart->id_lang = (int) $ppec->context->cookie->id_lang;

                $secure_key = isset($ppec->context->customer) ? $ppec->context->customer->secure_key : '';
                $ppec->context->cart->secure_key = $secure_key;

                // Customer settings
                $ppec->context->cart->id_guest = (int) $ppec->context->cookie->id_guest;
                $ppec->context->cart->id_customer = (int) $ppec->context->customer->id;

                if (!$ppec->context->cart->add()) {
                    $ppec->logs[] = $ppec->l('Cannot create new cart');
                } else {
                    $ppec->context->cookie->id_cart = (int) $ppec->context->cart->id;
                }
            }

            // Set details for a payment
            $ppec->setExpressCheckout();
            if ($ppec->hasSucceedRequest() && !empty($ppec->token)) {
                return array(
                    'token' => $ppec->token,
                    'paypal_redirect_url' => $ppec->getRedirectUrl(),
                );
            }
        }

        return false;
    }

    public function returnFromPaypal($ppec) { //step two
        if (!empty($ppec->token) && $ppec->token) {

            // Get payment infos from paypal
            $ppec->getExpressCheckout();

            if ($ppec->hasSucceedRequest() && !empty($ppec->token)) {
                $address = $customer = null;
                $email = $ppec->result['EMAIL'];

                // Create Customer if not exist with address etc
                if ($ppec->context->cookie->logged) {
                    if (!($id_customer = KPaypal::getPayPalCustomerIdByEmail($ppec->result['EMAIL']))) {
                        KPayPal::addPayPalCustomer($ppec->context->customer->id, $email);
                    }

                    $customer = $ppec->context->customer;
                } else if (($id_customer = Customer::customerExists($email, true))) { // check whether user email already exists
                    $customer = new Customer($id_customer);
                } else { // if user email not exists
                    $customer = new Customer();

                    $customer->email = $email;
                    $customer->lastname = $ppec->result['LASTNAME'];
                    $customer->firstname = $ppec->result['FIRSTNAME'];
                    $password = Tools::passwdGen();
                    $customer->passwd = Tools::encrypt($password);
                    $ppec->context->cookie->guest_uname = $customer->email;

                    $customer->add();
                    Mail::Send($ppec->context->cookie->id_lang, 'account', Mail::l('Welcome', $ppec->context->cookie->id_lang), array('{email}' => $customer->email,
                        '{lastname}' => $customer->lastname,
                        '{firstname}' => $customer->firstname,
                        '{passwd}' => $password), $customer->email, $customer->firstname . ' ' . $customer->lastname);

                    KPayPal::addPayPalCustomer($customer->id, $ppec->result['EMAIL']);

                    $ppec->context->cookie->id_customer = (int) ($customer->id);
                    $ppec->context->cookie->customer_lastname = $customer->lastname;
                    $ppec->context->cookie->customer_firstname = $customer->firstname;
                    $ppec->context->cookie->passwd = $customer->passwd;
                    $ppec->context->cookie->logged = 1;
                    $ppec->context->cookie->email = $customer->email;
                }

                if (!$customer->id) {
                    $ppec->logs[] = $ppec->l('Cannot create customer');
                }

                if (!$ppec->context->cart->id_address_invoice) {
                    $ppec->context->cart->id_address_invoice = Address::getFirstCustomerAddressId($ppec->context->cookie->id_customer);
                }

                if ($ppec->context->cart->id_address_invoice) { //shipping address
                    $address = new Address($ppec->context->cart->id_address_invoice);
                }

                // Create address
                if ((!$address || !$address->id) && $customer->id) {
                    $address = new Address();

                    $address->id_country = isset($ppec->result['COUNTRYCODE']) ? Country::getByIso($ppec->result['COUNTRYCODE']) : (int) _PS_COUNTRY_DEFAULT_;
                    $address->alias = 'Paypal_Address';
                    $address->lastname = $customer->lastname;
                    $address->firstname = $customer->firstname;
                    $address->telephone = max($ppec->result['PHONENUM'], $ppec->result['SHIPTOPHONENUM']);
                    $address->address1 = isset($ppec->result['PAYMENTREQUEST_0_SHIPTOSTREET']) ? $ppec->result['PAYMENTREQUEST_0_SHIPTOSTREET'] : '  ';
                    $address->city = isset($ppec->result['PAYMENTREQUEST_0_SHIPTOCITY']) ? $ppec->result['PAYMENTREQUEST_0_SHIPTOCITY'] : '  ';
                    $address->postcode = isset($ppec->result['SHIPTOZIP']) ? $ppec->result['SHIPTOZIP'] : 0;
                    $address->id_customer = $customer->id;

                    $address->add();
                    $ppec->context->cart->id_address_delivery = $address->id;
                    $ppec->context->cart->id_address_invoice = $address->id;
                }

                if ($customer->id && !$address->id) {
                    $ppec->logs[] = $ppec->l('Cannot create Address');
                }

                // Create Order
                if ($address->id && $customer->id) {
                    $ppec->context->cart->id_customer = $customer->id;
                    $ppec->context->cart->id_guest = $ppec->context->cookie->id_guest;

                    if (!$ppec->context->cart->update()) {
                        $ppec->logs[] = $ppec->l('Cannot update existing cart');
                    } else {
                        $ppec->redirectToCheckout($customer);
                    }
                }
            }
        }
    }

    public function pay($ppec) {

        // If Previous steps succeed, ready (means 'ready to pay') will be set to true
        if (($ppec->ready && !empty($ppec->token) && $ppec->type == 'payment_cart') && ($ppec->payer_id = Tools::getValue('payer_id'))) {
            // Check modification on the product cart / quantity
            if ($ppec->isProductsListStillRight()) {
                $order = null;
                $cart = $ppec->context->cart;
                $customer = new Customer((int) $cart->id_customer);

                // When all information are checked before, we can validate the payment to paypal
                // and create the prestashop order
                $ppec->doExpressCheckout();

                $result = FALSE;
                /// Check payment (real paid))
                if ($ppec->hasSucceedRequest() && !empty($ppec->token)) {
                    $order_total = (float) $cart->getOrderTotal(true, Cart::BOTH);
                    if ((bool) Configuration::get('PAYPAL_CAPTURE')) {
                        $payment_type = (int) Configuration::get('PS_OS_WS_PAYMENT');
                        $payment_status = 'Pending_capture';
                        $message = $ppec->l('Pending payment capture.') . '<br />';
                    } else {
                        if (isset($ppec->result['PAYMENTINFO_0_PAYMENTSTATUS']))
                            $payment_status = $ppec->result['PAYMENTINFO_0_PAYMENTSTATUS'];
                        else
                            $payment_status = 'Error';

                        if (strcmp($payment_status, 'Completed') === 0) {
                            $payment_type = (int) Configuration::get('PS_OS_PAYMENT');
                            $message = $ppec->l('Payment accepted.') . '<br />';
                        } elseif (strcmp($payment_status, 'Pending') === 0) {
                            $payment_type = (int) Configuration::get('PS_OS_PAYPAL');
                            $message = $ppec->l('Pending payment confirmation.') . '<br />';
                        }
                    }
                    $result = TRUE;
                }
                // Payment error
                else {
                    $payment_status = $ppec->result['PAYMENTINFO_0_PAYMENTSTATUS'];
                    $payment_type = (int) Configuration::get('PS_OS_ERROR');

                    if ($amount_match)
                        $message = implode('<br />', $ppec->logs) . '<br />';
                    else
                        $message = $ppec->l('Price paid on paypal is not the same that on PrestaShop.') . '<br />';
                }

                include_once(KANCART_ROOT . '/modules/paypal/paypal_orders.php');
                $transaction = KPayPalOrder::getTransactionDetails($ppec, $payment_status);
                $ppec->context->cookie->id_cart = $cart->id;
                $ppec->validateOrder((int) $cart->id, $payment_type, $order_total, $ppec->displayName, $message, $transaction, (int) $cart->id_currency, false, $customer->secure_key, $ppec->context->shop);

                if (!$ppec->currentOrder) {
                    $ppec->logs[] = $this->l('Cannot create order');
                } else {
                    $id_order = (int) $ppec->currentOrder;
                    $order = new Order($id_order);
                    $order->total_paid = $ppec->getTotalPaid();
                }

                unset($ppec->context->cookie->{PaypalExpressCheckout::$COOKIE_NAME});

                // Update for the Paypal shipping cost
                if ($order) {
                    $order->update();
                    unset($ppec->context->cookie->id_order);
                    unset($ppec->context->cookie->id_cart);
                }
            } else {
                // If Cart changed, no need to keep the paypal data
                unset($ppec->cookie->{PaypalExpressCheckout::$COOKIE_NAME});
                $ppec->logs[] = $ppec->l('Cart changed since the last checkout express, please make a new Paypal checkout payment');
            }
        }

        unset($ppec->context->cookie->returnUrl);
        unset($ppec->context->cookie->cancelUrl);
        unset($ppec->context->cookie->payer_id);

        return $result === TRUE ? $order : false;
    }

}

?>
