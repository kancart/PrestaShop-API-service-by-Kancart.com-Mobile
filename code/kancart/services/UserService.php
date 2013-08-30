<?php

/**
 * @author hujs
 */
class UserService extends BaseService {

    private $addressFieldsMap = array(
        'id_address' => 'address_book_id',
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'address1' => 'address1',
        'address2' => 'address2',
        'city' => 'city',
        'state' => 'state',
        'id_country' => 'country_id',
        'postcode' => 'postcode',
        'company' => 'company',
        'phone' => 'telephone',
        'phone_mobile' => 'mobile'
    );

    /**
     * check address's integrity
     * @param type $address
     * @author hujs
     */
    public function checkAddressIntegrity($address) {
        if ($address) {
            return (int) $address['address_book_id'] > 0
                    && $address['firstname']
                    && $address['lastname']
                    && (int) $address['country_id'] > 0
                    && $address['city']
                    && $address['postcode'];
        }
        return false;
    }

    /**
     * translate the osCommerce's address to kancart's address format
     * @param type $address
     * @return type
     * @author hujs
     */
    public function translateAddress($address) {
        $translatedAddress = array();
        $address['state'] = State::getNameById($address['id_state']);
        foreach ($this->addressFieldsMap as $key => $value) {
            $translatedAddress[$value] = $address[$key];
        }
        return $translatedAddress;
    }

    /**
     * Get user's addresses
     * @global type $customer_id
     * @return type
     * @author hujs
     */
    public function getAddresses() {
        $customer = new Customer($this->cookie->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            return array();
        }

        $addressesInfo = $customer->getAddresses($this->cookie->id_lang);
        $addresses = array();
        foreach ($addressesInfo as $addresse) {
            $addresses[] = $this->translateAddress($addresse);
        }
        return $addresses;
    }

    /**
     * check wheter the email has already existed in the database
     * @param type $email
     * @return boolean
     * @author hujs
     */
    public function checkEmailExists($email) {
        if (!empty($email)) {
            return Customer::customerExists($email, false, false);
        }
        return false;
    }

    /**
     * $registerInfo required email、 password 、firstname and lastname
     * @param type $registerInfo 
     * @return boolean
     * @author hujs
     */
    public function register($registerInfo) {
        /* Preparing customer */
        $customer = new Customer();
        $customer->active = 1;
        $customer->is_guest = 0;
        $customer->email = $registerInfo['email'];
        $customer->passwd = Tools::encrypt($registerInfo['password']);
        $name = explode('@', $registerInfo['email']);
        $registerInfo['firstname'] == $name[0] && !Validate::isName($registerInfo['firstname']) && $registerInfo['firstname'] = 'no';
        $customer->firstname = $registerInfo['firstname'];
        $customer->lastname = empty($registerInfo['lastname']) ? 'no' : $registerInfo['lastname'];

        $error = array();
        Validate::isEmail($customer->email) || $error[] = "email name Invalid";
        Validate::isName($customer->firstname) || $error[] = "first name Invalid";
        Validate::isName($customer->lastname) || $error[] = "lastname name Invalid";

        if (sizeof($error)) {
            return $error;
        }

        /* Preparing address */
        $address = new Address();
        $address->firstname = $customer->firstname;
        $address->lastname = $customer->lastname;
        $address->id_customer = 1;
        $address->id_country = (int) _PS_COUNTRY_DEFAULT_;
        $address->alias = 'from mobile';
        $address->city = ' ';
        $address->address1 = ' ';

        if (!$customer->add()) {
            return false;
        } else {
            $address->id_customer = (int) ($customer->id);
            $address->add();

            if (!$customer->is_guest) {
                Mail::Send($this->cookie->id_lang, 'account', Mail::l('Welcome', $this->cookie->id_lang), array('{email}' => $customer->email,
                    '{lastname}' => $customer->lastname,
                    '{firstname}' => $customer->firstname,
                    '{passwd}' => $registerInfo['password']), $customer->email, $customer->firstname . ' ' . $customer->lastname);
            }

            $this->cookie->id_customer = (int) ($customer->id);
            $this->cookie->customer_lastname = $customer->lastname;
            $this->cookie->customer_firstname = $customer->firstname;
            $this->cookie->passwd = $customer->passwd;
            $this->cookie->logged = 1;
            $this->cookie->email = $customer->email;
            $this->cookie->is_guest = $customer->is_guest;
            /* Update cart address */
            $this->cart->secure_key = $customer->secure_key;
            $this->cart->id_address_delivery = Address::getFirstCustomerAddressId((int) ($customer->id));
            $this->cart->id_address_invoice = Address::getFirstCustomerAddressId((int) ($customer->id));
            $this->cart->update();
        }

        return true;
    }

    /**
     * $loginInfo required email and password
     * @global type $this->cart
     * @param type $loginInfo
     * @return type
     * @author hujs
     */
    public function login($loginInfo) {          
        $customer = new Customer();
        $authentication = $customer->getByEmail(trim($loginInfo['email']), trim($loginInfo['password']));
        if (!$authentication || !$customer->id) {
            /* Handle brute force attacks */
            sleep(1);
            return false;
        } else {
            $this->cookie->id_compare = isset($this->cookie->id_compare) ? $this->cookie->id_compare : CompareProduct::getIdCompareByIdCustomer($customer->id);
            $this->cookie->id_customer = (int) ($customer->id);
            $this->cookie->customer_lastname = $customer->lastname;
            $this->cookie->customer_firstname = $customer->firstname;
            $this->cookie->logged = 1;
            $this->cookie->is_guest = $customer->isGuest();
            $this->cookie->passwd = $customer->passwd;
            $this->cookie->email = $customer->email;
            if (Configuration::get('PS_CART_FOLLOWING') AND (empty($this->cookie->id_cart) OR Cart::getNbProducts($this->cookie->id_cart) == 0))
                $this->cookie->id_cart = (int) (Cart::lastNoneOrderedCart((int) ($customer->id)));
            /* Update cart address */
            $this->cart->id_carrier = 0;
            $this->cart->id_address_delivery = Address::getFirstCustomerAddressId((int) ($customer->id));
            $this->cart->id_address_invoice = Address::getFirstCustomerAddressId((int) ($customer->id));
            // If a logged guest logs in as a customer, the cart secure key was already set and needs to be updated
            $this->cart->secure_key = $customer->secure_key;
            $this->cart->update();

            if ($this->context) {
                $this->context->customer = $customer;
            }

            return true;
        }
    }

    /**
     * validateForm
     * @param type $address
     * @return type
     * @author hujs
     */
    private function prepareSqlDataArrayForAddress($address) {

        return $address->validateController();
    }

    /**
     * get address by id
     * @param type $addressBookId
     * @return type
     * @author hujs
     */
    public function getAddress($addressBookId) {
        if (Address::addressExists($addressBookId)) {
            $address = new Address((int) $addressBookId, $this->cookie->id_lang);
            return $this->translateAddress($address->getFields());
        } else {
            return array();
        }
    }

    /**
     * add an address entry to address book
     * @param type $addressInfo
     * @author hujs
     */
    public function addAddress($addressInfo) {      
        $defaultAddressId = Address::getFirstCustomerAddressId($this->cookie->id_customer);
        $defaultAddress = new Address((int) $defaultAddressId, $this->cookie->id_lang);
        if(empty($defaultAddress->id_country) || empty($defaultAddress->id_state)){ //if first address is empty fill it
            $addressInfo['address_id'] = $defaultAddressId;
            return $this->updateAddress($addressInfo);
        }
        
        $address = new Address();
        $address->firstname = $addressInfo['firstname'];
        $address->lastname = $addressInfo['lastname'];
        $address->id_country = $addressInfo['country_id'];
        $address->alias = 'from mobile';
        $address->city = $addressInfo['city'];
        $address->address1 = $addressInfo['address_1'];
        $address->address2 = $addressInfo['address_2'];
        $address->postcode = $addressInfo['postcode'];
        $address->phone = $addressInfo['telephone'];
        $address->id_state = isset($addressInfo['state']) && empty($addressInfo['state']) ? 0 : State::getIdByName($addressInfo['state']);
        $address->id_customer = $this->cookie->id_customer;

        $errorMessages = $this->prepareSqlDataArrayForAddress($address);
        if (empty($errorMessages)) {
            if (!$country = new Country((int) $address->id_country) OR !Validate::isLoadedObject($country))
                $errorMessages[] = Tools::displayError();

            /* US customer: normalize the address */
            if ($address->id_country == Country::getByIso('US')) {
                include_once(_PS_TAASC_PATH_ . 'AddressStandardizationSolution.php');
                $normalize = new AddressStandardizationSolution;
                $address->address1 = $normalize->AddressLineStandardization($address->address1);
                $address->address2 = $normalize->AddressLineStandardization($address->address2);
            }

            $zip_code_format = $country->zip_code_format;
            if ($country->need_zip_code) {
                if (($address->postcode) AND $zip_code_format) {
                    $zip_regexp0 = '/^' . $zip_code_format . '$/ui';
                    $zip_regexp1 = str_replace(' ', '( |)', $zip_regexp0);
                    $zip_regexp2 = str_replace('-', '(-|)', $zip_regexp1);
                    $zip_regexp3 = str_replace('N', '[0-9]', $zip_regexp2);
                    $zip_regexp4 = str_replace('L', '[a-zA-Z]', $zip_regexp3);
                    $zip_regexp = str_replace('C', $country->iso_code, $zip_regexp4);
                    if (!preg_match($zip_regexp, $address->postcode))
                        $errorMessages[] = '<strong>' . Tools::displayError('Zip/ Postal code') . '</strong> ' . Tools::displayError('is invalid.') . '<br />' . Tools::displayError('Must be typed as follows:') . ' ' . str_replace('C', $country->iso_code, str_replace('N', '0', str_replace('L', 'A', $zip_code_format)));
                }
                elseif ($zip_code_format)
                    $errorMessages[] = '<strong>' . Tools::displayError('Zip/ Postal code') . '</strong> ' . Tools::displayError('is required.');
                elseif ($address->postcode AND !preg_match('/^[0-9a-zA-Z -]{4,9}$/ui', $address->postcode))
                    $errorMessages[] = '<strong>' . Tools::displayError('Zip/ Postal code') . '</strong> ' . Tools::displayError('is invalid.') . '<br />' . Tools::displayError('Must be typed as follows:') . ' ' . str_replace('C', $country->iso_code, str_replace('N', '0', str_replace('L', 'A', $zip_code_format)));
            }

            if ((int) ($country->contains_states) AND !(int) ($address->id_state))
                $errorMessages[] = Tools::displayError('This country requires a state selection.');

            if (!sizeof($this->errors)) {
                if ($address->save()) {
                    $this->cart->id_address_invoice = (int) ($address->id);
                    $this->cart->update();
                }
            }
        }

        return sizeof($errorMessages) ? $errorMessages : $address->id;
    }

    /**
     * delete  an entry from address book
     * @global type $registry
     * @param type $addressId
     * @return type
     * @author hujs
     */
    public function deleteAddress($addressId) {
        $address = new Address((int) $addressId);
        $errorMessages = array();

        if (Validate::isLoadedObject($address) && Customer::customerHasAddress($this->cookie->id_customer, $addressId)) {
            if ($address->delete()) {
                if ($this->cart->id_address_invoice == $address->id)
                    unset($this->cart->id_address_invoice);
                if ($this->cart->id_address_delivery == $address->id)
                    unset($this->cart->id_address_delivery);
            }else {
                $errorMessages[] = Tools::displayError('This address cannot be deleted.');
            }
        } else {
            $errorMessages[] = Tools::displayError('This address is not exists.');
        }

        return sizeof($errorMessages) ? $errorMessages : true;
    }

    /**
     * update user's address
     * @global type $customer_id
     * @param type $addressInfo
     * @return boolean|string
     * @author hujs
     */
    public function updateAddress($addressInfo) {
        $address = new Address();
        $address->firstname = empty($addressInfo['firstname']) ? $this->cookie->customer_firstname : $addressInfo['firstname'];
        $address->lastname = empty($addressInfo['lastname']) ? $this->cookie->customer_lastname : $addressInfo['lastname'];
        $address->id_country = $addressInfo['country_id'];
        $address->alias = 'from mobile';
        $address->city = $addressInfo['city'];
        $address->address1 = $addressInfo['address_1'];
        $address->address2 = $addressInfo['address_2'];
        $address->postcode = $addressInfo['postcode'];
        $address->phone = $addressInfo['telephone'];
        $address->id_state = isset($addressInfo['state']) && empty($addressInfo['state']) ? 0 : State::getIdByName($addressInfo['state']);
        $address->id_customer = $this->cookie->id_customer;

        $errorMessages = $this->prepareSqlDataArrayForAddress($address);
        if (empty($errorMessages)) {
            if (!$country = new Country((int) $address->id_country) OR !Validate::isLoadedObject($country))
                $errorMessages[] = Tools::displayError();

            /* US customer: normalize the address */
            if ($address->id_country == Country::getByIso('US')) {
                include_once(_PS_TAASC_PATH_ . 'AddressStandardizationSolution.php');
                $normalize = new AddressStandardizationSolution;
                $address->address1 = $normalize->AddressLineStandardization($address->address1);
                $address->address2 = $normalize->AddressLineStandardization($address->address2);
            }

            $zip_code_format = $country->zip_code_format;
            if ($country->need_zip_code) {
                if (($address->postcode) AND $zip_code_format) {
                    $zip_regexp0 = '/^' . $zip_code_format . '$/ui';
                    $zip_regexp1 = str_replace(' ', '( |)', $zip_regexp0);
                    $zip_regexp2 = str_replace('-', '(-|)', $zip_regexp1);
                    $zip_regexp3 = str_replace('N', '[0-9]', $zip_regexp2);
                    $zip_regexp4 = str_replace('L', '[a-zA-Z]', $zip_regexp3);
                    $zip_regexp = str_replace('C', $country->iso_code, $zip_regexp4);
                    if (!preg_match($zip_regexp, $address->postcode))
                        $errorMessages[] = '<strong>' . Tools::displayError('Zip/ Postal code') . '</strong> ' . Tools::displayError('is invalid.') . '<br />' . Tools::displayError('Must be typed as follows:') . ' ' . str_replace('C', $country->iso_code, str_replace('N', '0', str_replace('L', 'A', $zip_code_format)));
                }
                elseif ($zip_code_format)
                    $errorMessages[] = '<strong>' . Tools::displayError('Zip/ Postal code') . '</strong> ' . Tools::displayError('is required.');
                elseif ($address->postcode AND !preg_match('/^[0-9a-zA-Z -]{4,9}$/ui', $address->postcode))
                    $errorMessages[] = '<strong>' . Tools::displayError('Zip/ Postal code') . '</strong> ' . Tools::displayError('is invalid.') . '<br />' . Tools::displayError('Must be typed as follows:') . ' ' . str_replace('C', $country->iso_code, str_replace('N', '0', str_replace('L', 'A', $zip_code_format)));
            }

            if ((int) ($country->contains_states) AND !(int) ($address->id_state))
                $errorMessages[] = Tools::displayError('This country requires a state selection.');

            if (!sizeof($this->errors)) {
                $country = new Country((int) ($address->id_country));
                if (Validate::isLoadedObject($country) AND !$country->contains_states)
                    $address->id_state = 0;
                $address_old = new Address($addressInfo['address_id']);
                if (Validate::isLoadedObject($address_old) AND Customer::customerHasAddress($this->cookie->id_customer, (int) $address_old->id)) {
                    if ($address_old->isUsed()) {
                        $address_old->delete();
                        $to_update = false;
                        if ($this->cart->id_address_invoice == $address_old->id) {
                            $to_update = true;
                            $this->cart->id_address_invoice = 0;
                        }
                        if ($this->cart->id_address_delivery == $address_old->id) {
                            $to_update = true;
                            $this->cart->id_address_delivery = 0;
                        }
                        if ($to_update)
                            $this->cart->update();
                    } else {
                        $address->id = (int) $address_old->id;
                        $address->date_add = $address_old->date_add;
                    }
                }

                if ($address->save()) {
                    $this->cart->id_address_invoice = (int) ($address->id);
                    $this->cart->update();
                }
            }
        }

        return sizeof($errorMessages) ? $errorMessages : $address->id;
    }

    /**
     * User logout
     */
    public function logout() {
        $this->cookie->mylogout();
    }

}

?>
