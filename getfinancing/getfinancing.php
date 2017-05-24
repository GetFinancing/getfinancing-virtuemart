<?php

/**
 * @author     Albert Fatsini - getfinancing.com
 * @date       : 20.07.2016
 *
 * @copyright  Copyright (C) 2015 - 2015 getfinancing.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined ('_JEXEC') or die('Restricted access');

/**
 * @version: Getfinancing 1.1.3
 */
if (!class_exists ('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DIRECTORY_SEPARATOR . 'vmpsplugin.php');
}

class plgVmPaymentGetfinancing extends vmPSPlugin {

    //Only USD is allowed
    const GETFINANCING_CURRENCY = "USD";


    function __construct (& $subject, $config) {

        parent::__construct ($subject, $config);

        $this->_loggable = TRUE;
        $this->tableFields = array_keys ($this->getTableSQLFields ());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush ();
        $this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

    }

    /**
     * Create the table for this plugin if it does not yet exist.
     */
    public function getVmPluginCreateTableSQL () {

        return $this->createTableSQL ('Payment GetFinancing Table');
    }

    /**
     * Payment table fields
     *
     * @return string SQL Fileds
     */
    function getTableSQLFields () {

        $SQLfields = array(
            'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'         => 'int(1) UNSIGNED',
            'order_number'                => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name'                => 'varchar(5000)',
            'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'            => 'char(3)',
            'email_currency'              => 'char(3)',
            'cost_per_transaction'        => 'decimal(10,2)',
            'cost_percent_total'          => 'decimal(10,2)',
            'tax_id'                      => 'smallint(1)'
        );

        return $SQLfields;
    }

    /**
     * Form Creation
     */
    function plgVmConfirmedOrder ($cart, $order) {
        if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL;
        }
        if (!$this->selectedThisElement ($method->payment_element)) {
            return FALSE;
        }

        $lang = JFactory::getLanguage ();
        $filename = 'com_virtuemart';
        $lang->load ($filename, JPATH_ADMINISTRATOR);

        if (!class_exists ('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');
        }
        if (!class_exists ('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'currency.php');
        }

        $jinput = JFactory::getApplication()->input;

        //Account Settings
        $environment = $method->getfinancing_env;
        $merchant_id = $method->getfinancing_merchant_id;
        $username = $method->getfinancing_username;
        $password = $method->getfinancing_password;

        //Callback urls
        $url_ok = JURI::root () . 'index.php?option=com_virtuemart'.
        '&view=pluginresponse'.
        '&task=pluginresponsereceived'.
        '&vmethod=getfinancing'.
        '&status=ok'.
        '&on=' .$order['details']['BT']->order_number .
        '&pm=' .$order['details']['BT']->virtuemart_paymentmethod_id .
        '&Itemid=' . $jinput->get('Itemid','',INT) .
        '&lang='. $jinput->get('lang','',CMD);

        $url_ko = JURI::root () . 'index.php?option=com_virtuemart'.
        '&view=pluginresponse'.
        '&task=pluginresponsereceived'.
        '&vmethod=getfinancing'.
        '&status=ko'.
        '&on=' .$order['details']['BT']->order_number .
            '&pm=' .$order['details']['BT']->virtuemart_paymentmethod_id .
            '&Itemid=' .  $jinput->get('Itemid','',INT) .
            '&lang='. $jinput->get('lang','',CMD);

        $callback_url=JURI::root () . 'index.php?option=com_virtuemart'.
        '&view=pluginresponse'.
        '&task=pluginresponsereceived'.
        '&vmethod=getfinancing'.
        '&pm=' .$order['details']['BT']->virtuemart_paymentmethod_id ;

        //Order ID
        $order_id = strval($order['details']['BT']->order_number);

        //Order email & Full name
        $customer_email = $order['details']['BT']->email;
        $customer_name = $order['details']['BT']->first_name.' '.$order['details']['BT']->last_name;
        //Order Amount
        //Precio del pedido
        $order_amount = number_format((float)($order['details']['BT']->order_total)/100, 2, '.', '' );
        $order_amount = str_replace('.','',$order_amount);
        $order_amount = floatval($order_amount);

        //Currency
        $currency = self::GETFINANCING_CURRENCY;

        //address
        $address = $order['details']['BT']->address_1. " ". $order['details']['BT']->address_2;
        $city = $order['details']['BT']->city;
        $country = shopfunctions::getCountryByID($order['details']['BT']->virtuemart_country_id,'country_name');
        $state = shopfunctions::getStateByID($order['details']['BT']->virtuemart_state_id,'state_name');
        $zip = $order['details']['BT']->zip;
        //shipping
        $saddress = !isset($order['details']['ST']->address_1)?$order['details']['BT']->address_1. " ". $order['details']['BT']->address_2 :$order['details']['ST']->address_1. " ". $order['details']['ST']->address_2;
        $scity = !isset($order['details']['ST']->city)? $order['details']['BT']->city : $order['details']['ST']->city;
        $scountry = !isset($order['details']['ST']->virtuemart_country_id)? shopfunctions::getCountryByID($order['details']['BT']->virtuemart_country_id,'country_name') : shopfunctions::getCountryByID($order['details']['ST']->virtuemart_country_id,'country_name');
        $sstate = !isset($order['details']['ST']->virtuemart_state_id)? shopfunctions::getStateByID($order['details']['BT']->virtuemart_state_id,'state_name') :  shopfunctions::getStateByID($order['details']['ST']->virtuemart_state_id,'state_name');
        $szip = !isset($order['details']['ST']->zip) ? $order['details']['BT']->zip : $order['details']['ST']->zip;

        //phone
        $phone = !empty($order['details']['BT']->phone_1) ? $order['details']['BT']->phone_1 : $order['details']['BT']->phone_2;
        if ($phone == null){
          $phone ='';
        }
        $mobile_phone =$order['details']['BT']->phone_2;

        $this->log("Creating Form");
        $this->log("OrderID:".$order_id);

        //Order description
        $description = 'OrderID: '.$order_id;


        //Products description
        $product_info='';
        $cart_items = array();
        foreach ($cart->products as $product) {
              $product_info .= $product->product_name.' ('.$product->quantity.') ';
              $cart_items[]=array('sku' => $product->product_sku,
                                  'display_name' => $product->product_name,
                                  'quantity' => $product->quantity,
                                  'unit_price' => $product->prices['basePrice'],
                                  'unit_tax' => $product->prices['taxAmount']

            );
        }

        $merchant_loan_id = md5(time() .$merchant_id . $order['details']['BT']->first_name . $order_amount);

        $sql = "INSERT INTO `#__getfinancing` ( order_id, merchant_loan_id ) values ('".$order_id."', '".$merchant_loan_id."')";
        $db = JFactory::getDBO();
        $db->setQuery($sql);
        $db->query();




        $gf_data = array(
            'amount'           => $order_amount,
            //'product_info'     => $product_info,
            'cart_items'       => $cart_items,
            'first_name'       => $order['details']['BT']->first_name,
            'last_name'        => $order['details']['BT']->last_name,
            'shipping_address' => array(
                'street1'  => $saddress,
                'city'    => $scity,
                'state'   => $sstate,
                'zipcode' => $szip
            ),
            'billing_address' => array(
                'street1'  => $address,
                'city'    => $city,
                'state'   => $state,
                'zipcode' => $zip
            ),
            'version'          => '1.9',
            'email'            => $customer_email,
            'merchant_loan_id' => $merchant_loan_id,
            'success_url' => $url_ok,
            'postback_url' => $callback_url,
            'failure_url' => $url_ko,
            'phone' => $phone,
            'software_name' => 'vituemart',
            'software_version' => 'joomla 3 - virtuemart 3'
        );
        $this->log($gf_data. ": ". var_export($gf_data,1));

        $body_json_data = json_encode($gf_data);
        $header_auth = base64_encode($username . ":" . $password);

        $post_args = array(
            'body' => $body_json_data,
            'timeout' => 60,     // 60 seconds
            'blocking' => true,  // Forces PHP wait until get a response
            'headers' => array(
              'Content-Type' => 'application/json',
              'Authorization' => 'Basic ' . $header_auth,
              'Accept' => 'application/json'
             )
        );


        if ($environment == "test") {
            $gf_url = 'https://api-test.getfinancing.com/merchant/' . $merchant_id . '/requests';
        } else {
            $gf_url = 'https://api.getfinancing.com/merchant/' . $merchant_id . '/requests';
        }
        $gf_response = $this->_remote_post( $gf_url, $post_args );

        $this->log($gf_response. ": ". var_export($gf_response,1));

        if ($gf_response === false) {
            header("Location: ".$url_ko);
        }

        $gf_response = json_decode($gf_response);

        if ((isset($gf_response->href) == FALSE) || (empty($gf_response->href)==TRUE)) {
          header("Location: ".$url_ko);
        }

        //HTML necesary to send GetFinancing Request
        $form = '<html><head><title>Redirección GetFinancing</title></head><body><div style="margin: auto; text-align: center;">
        <script src="https://cdn.getfinancing.com/libs/1.0/getfinancing.js"></script>
        <script type="text/javascript">
              var onComplete = function() {
                  window.location.href="' . $url_ok . '";
              };

              var onAbort = function() {
                  window.location.href="' . $url_ko . '";
              };
              //$("#btn_submit").hide();
              new GetFinancing("' . $gf_response->href . '", onComplete, onAbort);
          </script>';
        $form .= 'Redirecting to GetFinancing...';
        $form .= '</div>';
        $form .= '</body></html>';

        //Se crea el pedido
        $modelOrder = VmModel::getModel ('orders');
        //Status del pedido -> "Pending"
        $order['order_status'] = $this->getNewStatus ($method);
        $order['customer_notified'] = 1;
        $order['comments'] = '';
        $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

        $jinput->set('html', $form);
        return TRUE;

    }

    /*
         *
         * Se genera el status inicial del pedido -> "Pending"
         */
    function getNewStatus ($method) {
        vmInfo (JText::_ ('GetFinancing: Pedido en estado "Pending"'));
        if (isset($method->status_pending) and $method->status_pending!="") {
            return $method->status_pending;
        } else {
            // $StatutWhiteList = array('P','C','X','R','S','N');
            return 'P';  //PENDING
            //return 'X';  //CANCELLED
            //return 'R';  //REFUNDED
            //return 'C';  //CONFIRMED
        }
    }

    /**
     * @param $html
     * @return bool|null|string
     */
    function plgVmOnPaymentResponseReceived (&$html) {
      $jinput = JFactory::getApplication()->input;

        if(empty($jinput->get('vmethod')) || !$jinput->get('vmethod') == "getfinancing"){
            return NULL;
        }

        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');
        }

        // Recuperamos Identificador de pedido
        $virtuemart_paymentmethod_id = $jinput->get('pm', 0);
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL;
        }
        $json = file_get_contents('php://input');
        $data = json_decode($json);


        $request_token = (int)$data->request_token;
        $version = $data->version;
        $updates = $data->updates;
        $merchant_transaction_id = $data->merchant_transaction_id;
        if ( $updates->status == "approved" ) {//CallBack URL

            $this->log("Starting callback script");


            $sql = "SELECT * from `#__getfinancing` where merchant_loan_id ='".$merchant_transaction_id."'";
            $db = JFactory::getDBO();
            $db->setQuery($sql);
            $results = $db->loadObjectList();
            $order_id = $results[0]->order_id;

            if (   $order_id  == null ){
              die("Hack detected. Order not approved");
            }


              $orderModel = VmModel::getModel('orders');
              $order_number = $orderModel->getOrderIdByOrderNumber($order_id);
              $order = $orderModel->getOrder($order_number);
              $order['order_status'] =  "C";
              $order['customer_notified'] = 1;
              $updated = $orderModel->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

              $msg = $updated? "Updating order ".$order['details']['BT']->virtuemart_order_id." to status C":"Not updaing order ".$order['details']['BT']->virtuemart_order_id." to status C";
              $this->log($msg);

              //Se eliminan productos del carrito
              $cart = VirtueMartCart::getCart();
              $cart->emptyCart();

          } else if ($updates->status == "rejected") {

              $virtuemart_order_id = $merchant_transaction_id;
              $orderModel = VmModel::getModel('orders');
              //Don't lose cart
              $order_number = $orderModel->getOrderIdByOrderNumber($virtuemart_order_id);
              $order = $orderModel->getOrder($order_number);
              $order['order_status'] =  "X";
              $order['customer_notified'] = 1;
              $cart = VirtueMartCart::getCart();
              $cart->emptyCart();
              $orderModel->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);
            }

        else {//URL OK Y KO
            $status = $jinput->get("status");
            $order_number = $jinput->get("on");

            if (!$this->selectedThisElement($method->payment_element)) {
            return NULL;
            }

            if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
                return NULL;
            }

            if ($status == "ok") {
                $this->log("GetFinancing Order Number: ".$order_number.", Order Id: ".$virtuemart_order_id.' Correctly procesed. Showing OK page');
                $html = '<img src="'.JURI::root () .'plugins/vmpayment/getfinancing/getfinancing/assets/images/getfinancing.png" width="225"><br><br><br>';
                $html .= '<h3>Order '.$order_number.' successfuly Processed. Thank you for using GetFinancing.</h3>';
                //Flush cart
                $cart = VirtueMartCart::getCart();
                $cart->emptyCart();
            }
            else {
                $this->log("GetFinancing Order Number: ".$order_number.", Order Id: ".$virtuemart_order_id.' processed with error. Shwing KO page');
                $html = '<img src="'.JURI::root () .'plugins/vmpayment/getfinancing/getfinancing/assets/images/getfinancing.png" width="225"><br><br><br>';
                $html .='<h3>Order '.$order_number.' not processed.</h3>';
                $html .= '<h3>Your cart was not cleaned, please retry the purchase once again.</h3>';
            }
        }
        return TRUE;
    }
    //*****************************************************************************************


    /**
     * Display stored payment data for an order
     *
     */
    function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

        if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
            return NULL; // Another method was selected, do nothing
        }

        if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
            return NULL;
        }
        VmConfig::loadJLang('com_virtuemart');

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE ();
        $html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE ('GETFINANCING_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        if ($paymentTable->email_currency) {
            $html .= $this->getHtmlRowBE ('GETFINANCING_EMAIL_CURRENCY', $paymentTable->email_currency );
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     *
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions ($cart, $method, $cart_prices) {

        $this->convert_condition_amount($method);
        $amount = $this->getCartAmount($cart_prices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
            OR
            ($method->min_amount <= $amount AND ($method->max_amount == 0)));
        if (!$amount_cond) {
            return FALSE;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array ($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array ($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count ($countries) == 0 || in_array ($address['virtuemart_country_id'], $countries) ) {
            return TRUE;
        }

        return FALSE;
    }


    /*
    * We must reimplement this triggers for joomla 1.7
    */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the getfinancing method to create the tables
     */
    function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

        return $this->onStoreInstallPluginTable ($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
     */
    public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

        return $this->OnSelectCheck ($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object  $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, application->enqueueMessages() must be used to set a message.
     */
    public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

        return $this->displayListFE ($cart, $selected, $htmlIn);
    }

    /*
    * plgVmonSelectedCalculatePricePayment
    * Calculate the price (value, tax_id) of the selected method
    * It is called by the calculator
    * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
    *
    * @cart: VirtueMartCart the current cart
    * @cart_prices: array the new cart prices
    * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
    */

    public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

        return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

        if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement ($method->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency ($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     */
    function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

        return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     */
    public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

        $this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /* TODO ELIMINAR */
    function log($text) {
            // Log
        $logfilename = 'logs/getfinancing-log.log';
        $fp = @fopen($logfilename, 'a');
        if ($fp) {
            fwrite($fp, date('M d Y G:i:s') . ' -- ' . $text . "\r\n");
            fclose($fp);
        }
    }

    /**
     * @param $orderDetails
     * @param $data
     * @return null
     */
    function plgVmOnUserInvoice ($orderDetails, &$data) {

        if (!($method = $this->getVmPluginMethod ($orderDetails['virtuemart_paymentmethod_id']))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement ($method->payment_element)) {
            return NULL;
        }
        //vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

        if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00){
            return NULL;
        }

        if ($orderDetails['order_salesPrice']==0.00) {
            $data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Nerver send the invoice via email
        }

    }
    /**
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     * @return bool|null
     */
    function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {

            return '';
        }
        if (empty($payments[0]->email_currency)) {
            $vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
            $db = JFactory::getDBO();
            $q = 'SELECT vendor_currency FROM #__virtuemart_vendors WHERE virtuemart_vendor_id=' . $vendorId;
            $db->setQuery($q);
            $emailCurrencyId = $db->loadResult();
        } else {
            $emailCurrencyId = $payments[0]->email_currency;
        }

    }
    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     *

    public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
    return null;
    }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     *
     */
    function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

        return $this->onShowOrderPrint ($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3( &$data) {
        return $this->declarePluginParams('payment', $data);
    }
    function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

        return $this->setOnTablePluginParams ($name, $id, $table);
    }

    /**
     * Set up RemotePost / Curl.
     */
    function _remote_post($url,$args=array()) {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $args['body']);
        curl_setopt($curl, CURLOPT_USERAGENT, 'VirtueMart - GetFinancing Payment Module ' . $this->module_version);
        if (defined('CURLOPT_POSTFIELDSIZE')) {
            curl_setopt($curl, CURLOPT_POSTFIELDSIZE, 0);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, $args['timeout']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        $array_headers = array();
        foreach ($args['headers'] as $k => $v) {
            $array_headers[] = $k . ": " . $v;
        }
        if (sizeof($array_headers)>0) {
          curl_setopt($curl, CURLOPT_HTTPHEADER, $array_headers);
        }

        if (strtoupper(substr(@php_uname('s'), 0, 3)) === 'WIN') {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        $resp = curl_exec($curl);
        curl_close($curl);

        if (!$resp) {
          return false;
        } else {
          return $resp;
        }
    }
}
