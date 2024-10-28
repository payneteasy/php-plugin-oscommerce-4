<?php
/**
 * namespace
 */
namespace common\modules\orderPayment;

/**
 * used classes
 */
use common\classes\modules\ModulePayment;
use common\classes\modules\ModuleStatus;
use common\classes\modules\ModuleSortOrder;
use common\modules\orderPayment\payneteasy\api\PaynetApi;

/**
 * class declaration
 */
class payneteasy extends ModulePayment {

    /**
     * variables
     */
    var $code, $title, $description, $enabled, $order_id;

    /**
     * default values for translation
     */
    protected $defaultTranslationArray = [
        'MODULE_PAYMENT_PAYNETEASY_TEXT_TITLE' => 'PaynetEasy',
        'MODULE_PAYMENT_PAYNETEASY_TEXT_DESCRIPTION' => 'The PaynetEasy Payment Gateway enables merchants to accept credit card online during checkout.',
        'MODULE_PAYMENT_PAYNETEASY_ERROR' => 'There has been an error processing your credit card',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_JANUARY' => 'January',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_FEBRUARY' => 'February',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_MARCH' => 'March',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_APRIL' => 'April',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_MAY' => 'May',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_JUNE' => 'June',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_JULY' => 'July',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_AUGUST' => 'August',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_SEPTEMBER' => 'September',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_OCTOBER' => 'October',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_NOVEMBER' => 'November',
        'MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_DECEMBER' => 'December',
        'MODULE_PAYMENT_PAYNETEASY_RESPONSE_TEXT' => 'PaynetEasy payment completed for %1$s.%2$s <strong>Transaction ID:</strong>  %3$s.%4$s <strong>Approval Code:</strong> %5$s.%6$s <strong>RRN:</strong> %7$s',
    ];

    /**
     * class constructor
     */
    function __construct($order_id = -1) {
        parent::__construct();
        
        $this->code = 'payneteasy';
        $this->title = MODULE_PAYMENT_PAYNETEASY_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_PAYNETEASY_TEXT_DESCRIPTION;
        if (!defined('MODULE_PAYMENT_PAYNETEASY_STATUS')) {
            $this->enabled = false;
            return false;
        }

        $this->sort_order = MODULE_PAYMENT_PAYNETEASY_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_PAYNETEASY_STATUS == 'True') ? true : false);
        $this->order_id = $order_id;
        $this->order_status = MODULE_PAYMENT_PAYNETEASY_ORDER_STATUS_ID;
        $this->paid_status = 0;
        
        //remove keys if validate key false in last submit
        if( defined('MODULE_PAYMENT_PAYNETEASY_VALIDATEKEY') && MODULE_PAYMENT_PAYNETEASY_VALIDATEKEY == "No" ) {
            tep_db_query("UPDATE ".TABLE_PLATFORMS_CONFIGURATION." SET configuration_value='' WHERE configuration_key='MODULE_PAYMENT_PAYNETEASY_ENDPOINT_ID' AND platform_id='".(int)$platform_id."'");
            tep_db_query("UPDATE ".TABLE_PLATFORMS_CONFIGURATION." SET configuration_value='' WHERE configuration_key='MODULE_PAYMENT_PAYNETEASY_CONTROL_KEY' AND platform_id='".(int)$platform_id."'");
            tep_db_query("UPDATE ".TABLE_PLATFORMS_CONFIGURATION." SET configuration_value='' WHERE configuration_key='MODULE_PAYMENT_PAYNETEASY_LOGIN' AND platform_id='".(int)$platform_id."'");
            tep_db_query("UPDATE ".TABLE_PLATFORMS_CONFIGURATION." SET configuration_value='' WHERE configuration_key='MODULE_PAYMENT_PAYNETEASY_AUTHTOKEN' AND platform_id='".(int)$platform_id."'");
        }
        
        if( $this->manager ) {
            $platform_id  = $this->manager->getPlatformId();
            $return_url = \Yii::$app->urlManager->createUrl('payneteasy/return-payment');
            $refund_url   = \Yii::$app->urlManager->createUrl('payneteasy/refund-payment');
            $balancepayment_url = \Yii::$app->urlManager->createUrl('payneteasy/balance-payment');
            $opyID        = \Yii::$app->request->get('opyID');
            $script = 'var payneteasy = {
                    platform_id : '.$platform_id.',
                    return_url  : "'.$return_url.'",
                    refund_url  : "'.$refund_url.'",
                    balancepayment_url  : "'.$balancepayment_url.'",
                    opyID       : "'.$opyID.'"
                };';
            \Yii::$app->getView()->registerJs($script);
        }
        
        if ($this->checkView() == "admin") {
            $adminrefund = tep_catalog_href_link('lib/common/modules/orderPayment/payneteasy/js/adminrefund.js');
            $adminrefundcss = tep_catalog_href_link('lib/common/modules/orderPayment/payneteasy/css/adminrefund.css');
            \Yii::$app->getView()->registerJsFile($adminrefund);
            \Yii::$app->getView()->registerCssFile($adminrefundcss);
        } else {
            $branddetection = tep_href_link('lib/common/modules/orderPayment/payneteasy/js/BrandDetection.js');
            $creditcard = tep_href_link('lib/common/modules/orderPayment/payneteasy/js/creditcard.js');
            $cc = tep_href_link('lib/common/modules/orderPayment/payneteasy/js/cc.js');
            $payneteasycss = tep_href_link('lib/common/modules/orderPayment/payneteasy/css/payneteasy.css');
            \Yii::$app->getView()->registerJsFile($branddetection);
            \Yii::$app->getView()->registerJsFile($creditcard);
            \Yii::$app->getView()->registerJsFile($cc);
            \Yii::$app->getView()->registerCssFile($payneteasycss);
        }
        $this->update_status();
    }

    function getScriptName() {
        global $PHP_SELF;
        if (class_exists('\Yii') && is_object(\Yii::$app)) {
            return \Yii::$app->controller->id;
        } else {
            return basename($PHP_SELF);
        }
    }
    
    function checkView() {
        $view = "admin";
        if (tep_session_name() != 'tlAdminID') {
            if ($this->getScriptName() == 'checkout' /* FILENAME_CHECKOUT_PAYMENT */) {
                $view = "checkout";
            } else {
                $view = "frontend";
            }
        }
        return $view;
    }

    function update_status() {
        if (($this->enabled == true) && ((int) MODULE_PAYMENT_PAYNETEASY_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYNETEASY_ZONE . "' and zone_country_id = '" . $this->billing['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $this->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function selection() {

        //if store currency not euro then ignore payneteasy payment gateway
        $transaction_currency = \Yii::$app->settings->get('currency');
        if (strtolower($transaction_currency) != "eur") {
            $this->enabled = false;
            return false;
        }
        
        $months_array     = array();
        $months_array[0]  = array('', 'Month');
        $months_array[1]  = array('01', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_JANUARY);
        $months_array[2]  = array('02', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_FEBRUARY);
        $months_array[3]  = array('03', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_MARCH);
        $months_array[4]  = array('04', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_APRIL);
        $months_array[5]  = array('05', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_MAY);
        $months_array[6]  = array('06', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_JUNE);
        $months_array[7]  = array('07', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_JULY);
        $months_array[8]  = array('08', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_AUGUST);
        $months_array[9]  = array('09', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_SEPTEMBER);
        $months_array[10] = array('10', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_OCTOBER);
        $months_array[11] = array('11', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_NOVEMBER);
        $months_array[12] = array('12', MODULE_PAYMENT_PAYNETEASY_CC_TEXT_MONTH_DECEMBER);
        
        $today         = getdate();
        $years_array   = array();
        $years_array[] = array('','Year');

        for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
            $years_array[$i] = array(strftime('%Y', mktime(0, 0, 0, 1, 1, $i)),
                                     strftime('%Y', mktime(0, 0, 0, 1, 1, $i)));
        }

        if( strstr(MODULE_PAYMENT_PAYNETEASY_ACCEPTED_CARDS,'American Express') ) {
            $amex = tep_href_link('lib/common/modules/orderPayment/payneteasy/images/amex.png');
            $logos .= '<img src="'.$amex.'" class="payneteasy-cc-logo" id="payneteasy-cc-amex" alt="American Express"/>';
        }
        if( strstr(MODULE_PAYMENT_PAYNETEASY_ACCEPTED_CARDS,'Visa') ) {
            $visa = tep_href_link('lib/common/modules/orderPayment/payneteasy/images/visa.png');
            $logos .= '<img src="'.$visa.'" class="payneteasy-cc-logo" id="payneteasy-cc-visa" alt="VISA"/>';
        }
        if( strstr(MODULE_PAYMENT_PAYNETEASY_ACCEPTED_CARDS,'MasterCard') ) {
            $mastercard = tep_href_link('lib/common/modules/orderPayment/payneteasy/images/mastercard.png');
            $logos .= '<img src="'.$mastercard.'" class="payneteasy-cc-logo" id="payneteasy-cc-mastercard" alt="Master Card"/>';
        }
        if( strstr(MODULE_PAYMENT_PAYNETEASY_ACCEPTED_CARDS,'Discover') ) {
            $discover = tep_href_link('lib/common/modules/orderPayment/payneteasy/images/discover.png');
            $logos .= '<img src="'.$discover.'" class="payneteasy-cc-logo" id="payneteasy-cc-discover" alt="Discover"/>';
        }
        if( strstr(MODULE_PAYMENT_PAYNETEASY_ACCEPTED_CARDS,'JCB') ) {
            $jcb = tep_href_link('lib/common/modules/orderPayment/payneteasy/images/jcb.png');
            $logos .= '<img src="'.$jcb.'" class="payneteasy-cc-logo" id="payneteasy-cc-jcb" alt="JCB"/>';
        }
        if( strstr(MODULE_PAYMENT_PAYNETEASY_ACCEPTED_CARDS,'Diners') ) {
            $diners = tep_href_link('lib/common/modules/orderPayment/payneteasy/images/diners.png');
            $logos .= '<img src="'.$diners.'" class="payneteasy-cc-logo" id="payneteasy-cc-diners-club" alt="Diners"/>';
        }

        if ($this->checkView() == "admin") {
            $paynetlogo = tep_catalog_href_link('lib/common/modules/orderPayment/payneteasy/logo/PaynetLogo.svg');
        } else {
            $paynetlogo = tep_href_link('lib/common/modules/orderPayment/payneteasy/logo/PaynetLogo.svg');
        }
        
        $billing_address = $this->manager->getBillingAddress();
            
        $customer_id = \Yii::$app->user->getId();

        $script = '<script type="text/javascript">'
                  . 'var payneteasy_cc_months = ' . json_encode($months_array) . ';'
                  . 'var payneteasy_cc_years = ' . json_encode($years_array) . ';'
                  . 'var payneteasy_logos = \'' . $logos . '\';'
                  . 'var payneteasy_street_address = \'' . $billing_address["street_address"] . '\';'
                  . 'var payneteasy_postcode = \'' . $billing_address["postcode"] . '\';'
                  . 'var payneteasy_customer_id = \'' . $customer_id . '\';'
                  . 'var payneteasy_payment_method = \'' . MODULE_PAYMENT_PAYNETEASY_PAYMENT_METHOD . '\';'
                  . 'var payneteasy_brand_logo_path = \'' . tep_href_link('lib/common/modules/orderPayment/payneteasy/images') . '\';'
                . '</script>';
                
        return array('id' => $this->code,
            'module' => (MODULE_PAYMENT_PAYNETEASY_SHOWLOGO=='Yes'?'<img src="'.$paynetlogo.'" width="150px">':MODULE_PAYMENT_PAYNETEASY_FRONT_TITLE).$script
        );
    
    }

    public function install($platform_id) {
        $languages_id = \common\classes\language::get_id(DEFAULT_LANGUAGE);

        $get_current_status_id = tep_db_fetch_array(tep_db_query(
            "SELECT MAX(orders_status_id) AS current_max_id FROM ".TABLE_ORDERS_STATUS." "
        ));
        $new_status_id = intval($get_current_status_id['current_max_id'])+1;

        //check refunded order status if exist get id or insert then get id
        $order_status_query = tep_db_query("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_name = 'Refunded' AND language_id = '" . $languages_id . "'");
        if ( tep_db_num_rows($order_status_query) <= 0 ) {
            tep_db_query(
                "INSERT INTO ".TABLE_ORDERS_STATUS." (orders_status_id, orders_status_groups_id, language_id, orders_status_name, 
                orders_status_template, automated, orders_status_template_confirm, orders_status_template_sms, 
                order_evaluation_state_id, order_evaluation_state_default, orders_status_allocate_allow, 
                orders_status_release_deferred, orders_status_send_ga, comment_template_id, hidden) 
                VALUES (".$new_status_id.", 5, ".$languages_id.", 'Refunded', 'Order Status Update', 0, 'Order Status Update', '', 60, 0, 1, 0, -1, 0, 0)"
            );
        }

        //check partial refunded order status if exist get id or insert then get id
        $order_status_query = tep_db_query("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_name = 'Partially refunded' AND language_id = '" . $languages_id . "'");
        if ( tep_db_num_rows($order_status_query) <= 0 ) {
            tep_db_query(
                "INSERT INTO ".TABLE_ORDERS_STATUS." (orders_status_id, orders_status_groups_id, language_id, orders_status_name, 
                orders_status_template, automated, orders_status_template_confirm, orders_status_template_sms, 
                order_evaluation_state_id, order_evaluation_state_default, orders_status_allocate_allow, 
                orders_status_release_deferred, orders_status_send_ga, comment_template_id, hidden) 
                VALUES (".$new_status_id.", 2, ".$languages_id.", 'Partially refunded', 'Order Status Update', 0, 'Order Status Update', '', 60, 0, 1, 0, -1, 0, 0)"
            );
        }

        tep_db_query("CREATE TABLE `payneteasy_payments` (`paynet_order_id` int(11) NOT NULL, `merchant_order_id` int(11) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");

        //copy ot_payneteasy.php file to orderTotal module
        if( file_exists(__DIR__."/ot_payneteasy.php") && copy(__DIR__."/ot_payneteasy.php",__DIR__."/../orderTotal/ot_payneteasy.php") ) {
            unlink(__DIR__."/ot_payneteasy.php");
        }

        //check if table already exist or create new
        $install_query = tep_db_query("Show tables like 'payneteasy_vault'");
        if ( tep_db_num_rows($install_query) <= 0 ) {

            tep_db_query("CREATE TABLE `payneteasy_vault` (`customer_id` int(11) NOT NULL, `vault_customer_id` int(11) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");

            tep_db_query("ALTER TABLE `payneteasy_vault` ADD PRIMARY KEY (`customer_id`,`vault_customer_id`);");

        }

        parent::install($platform_id);

    }

    function process_button() {
        return false;
    }

    function _post_transaction()
    {

    }

    function _start_transaction($order)
    {
        $billing  = $order->billing;
        $shipping = $order->delivery;
        $customer = $order->customer;

        $payneteasy_card_number       = $this->manager->get('payneteasy-card-number');
        $payneteasy_card_expiry_month = $this->manager->get('payneteasy-card-expiry-month');
        $payneteasy_card_expiry_year  = $this->manager->get('payneteasy-card-expiry-year');
        $payneteasy_card_name         = $this->manager->get('payneteasy-card-name');
        $payneteasy_card_cvv          = $this->manager->get('payneteasy-card-cvv');
        $payneteasy_card_address      = $this->manager->get('payneteasy-card-address');
        $payneteasy_card_zip          = $this->manager->get('payneteasy-card-zip');
        $payneteasy_remote_address    = $this->manager->get('payneteasy-remote-address');

        $card_data = [
            'credit_card_number' => $payneteasy_card_number?:'',
            'card_printed_name' => $payneteasy_card_name?:'',
            'expire_month' => $payneteasy_card_expiry_month?:'',
            'expire_year' => $payneteasy_card_expiry_year?:'',
            'cvv2' => $payneteasy_card_cvv?:'',
        ];

        $data = [
            'client_orderid' => (string)$order->order_id,
            'order_desc' => 'Order # ' . $order->order_id,
            'amount' => $this->formatCurrencyRaw($order->info['total_inc_tax'], $order->info['currency']),
            'currency' => trim($order->info['currency'])?:'',
            'address1' => $payneteasy_card_address?:$billing["street_address"],
            'city' => $billing["city"]?:$shipping["city"],
            'zip_code' => $payneteasy_card_zip?:$billing["postcode"],
            'country' => $billing["country"]["iso_code_2"]?:$shipping["country"]["iso_code_2"],
            'phone'      => $billing["telephone"]?:$shipping["telephone"],
            'email'      => $customer["email_address"],
            'ipaddress' => $_SERVER['REMOTE_ADDR'],
            'cvv2' => $card_data['cvv2'],
            'credit_card_number' => $card_data['credit_card_number'],
            'card_printed_name' => $card_data['card_printed_name'],
            'expire_month' => $card_data['expire_month'],
            'expire_year' => $card_data['expire_year'],
            'first_name' => $customer['first_name'],
            'last_name'  => $customer['last_name'],
//            'redirect_success_url'      => HTTP_SERVER.'/checkout/success?order_id='.$order->order_id,
            'redirect_success_url' => tep_href_link('callback/webhooks.payment.' . $this->code, 'action=success&orders_id=' . $order->order_id, 'SSL'),
//            'redirect_fail_url'      => HTTP_SERVER.'/checkout?payment_error=PaynetEasy&order_id='.$order->order_id,
            'redirect_fail_url' => tep_href_link('callback/webhooks.payment.' . $this->code, 'action=error&orders_id=' . $order->order_id, 'SSL'),
//            'redirect_url' => HTTP_SERVER.'/checkout/success?order_id='.$order->order_id,
            'redirect_url' => tep_href_link('callback/webhooks.payment.' . $this->code, 'action=success&orders_id=' . $order->order_id, 'SSL'),
            'server_callback_url' => tep_href_link('callback/webhooks.payment.' . $this->code, 'action=success&orders_id=' . $order->order_id, 'SSL'),
        ];

        $payment_payneteasy_endpoint_id = MODULE_PAYMENT_PAYNETEASY_ENDPOINT_ID;

        $payment_payneteasy_control_key = MODULE_PAYMENT_PAYNETEASY_CONTROL_KEY;

        $data['control'] = $this->signPaymentRequest($data, $payment_payneteasy_endpoint_id, $payment_payneteasy_control_key);

        $sandbox = MODULE_PAYMENT_PAYNETEASY_SANDBOX;
        $action_url = MODULE_PAYMENT_PAYNETEASY_LIVE_URL;

        if ($sandbox == 'Yes')
            $sandbox = true;
        else
            $sandbox = false;

        if ($sandbox)
            $action_url = MODULE_PAYMENT_PAYNETEASY_SANDBOX_URL;

        $payment_payneteasy_payment_method = MODULE_PAYMENT_PAYNETEASY_PAYMENT_METHOD;
        $payment_payneteasy_endpoint_id = MODULE_PAYMENT_PAYNETEASY_ENDPOINT_ID;

        $paynetApi = new PaynetApi(
            MODULE_PAYMENT_PAYNETEASY_LOGIN,
            MODULE_PAYMENT_PAYNETEASY_CONTROL_KEY,
            $payment_payneteasy_endpoint_id,
            $payment_payneteasy_payment_method,
            $sandbox
        );

        if ($payment_payneteasy_payment_method == 'Form') {
            $response = $paynetApi->saleForm(
                $data,
                $payment_payneteasy_payment_method,
                $sandbox,
                $action_url,
                $payment_payneteasy_endpoint_id
            );
        } elseif ($payment_payneteasy_payment_method == 'Direct') {
            $response = $paynetApi->saleDirect(
                $data,
                $payment_payneteasy_payment_method,
                $sandbox,
                $action_url,
                $payment_payneteasy_endpoint_id
            );
        }

        tep_db_query(
            "INSERT INTO payneteasy_payments (paynet_order_id, merchant_order_id) 
                VALUES (".$response['paynet-order-id'].", ".$response['merchant-order-id'].")"
        );
        return $response;
    }

    function _save_order() {
        global $languages_id;

        if (!empty($this->order_id) && $this->order_id > 0) {
            return;
        }

        $order = $this->manager->getOrderInstance();

        $order->save_order();

        $order->save_details();

        $order->save_products(false);

        $stock_updated = false;

        $this->order_id = $order->order_id;
    }

    function before_process()
    {
        return false;
    }

    function after_process()
    {
        $order = $this->manager->getOrderInstance();
        $response = $this->_start_transaction($order);
        $status = $this->getPaymentStatus($order);
        $reset_cart = false;
        if (trim($status['status']) == 'processing') {
            if (MODULE_PAYMENT_PAYNETEASY_PAYMENT_METHOD == 'Form') {
                tep_redirect($response['redirect-url']);
            } elseif (MODULE_PAYMENT_PAYNETEASY_PAYMENT_METHOD == 'Direct') {
                if (MODULE_PAYMENT_PAYNETEASY_THREE_D_SECURE == 'Yes') {
                    print $status['html'];
                }
            }
        } elseif (trim($status['status']) == 'approved' || $status['status'] == 'approved') {
            $reset_cart = true;
            $orderPayment = new \common\models\OrdersPayment();
            $orderPayment->orders_payment_module = $this->code;
            $orderPayment->orders_payment_module_name = $this->title;
            $orderPayment->orders_payment_transaction_id = $status['paynet-order-id'];
            $orderPayment->orders_payment_id_parent = 0;
            $orderPayment->orders_payment_order_id = $order->order_id;
            $orderPayment->orders_payment_is_credit = 0;
            $orderPayment->orders_payment_status = \common\helpers\OrderPayment::OPYS_SUCCESSFUL;
            $orderPayment->orders_payment_amount = $this->formatCurrencyRaw($order->info['total_inc_tax'], $order->info['currency']);
            $orderPayment->orders_payment_currency = trim($order->info['currency']);
            $orderPayment->orders_payment_currency_rate = (float) $order->info['currency_value'];
            $orderPayment->orders_payment_snapshot = json_encode(\common\helpers\OrderPayment::getOrderPaymentSnapshot($order));
            $orderPayment->orders_payment_transaction_status = 'OK';
            $orderPayment->orders_payment_transaction_commentary = '';
            $orderPayment->orders_payment_date_create = date('Y-m-d H:i:s');
            $orderPayment->orders_payment_transaction_full = json_encode($response);
            $orderPayment->save();
            \common\helpers\Order::setStatus($order->order_id, 100006, [
                'comments' => '',
                'customer_notified' => 0,
            ]);
        } elseif (trim($status['status']) == 'error' || $status['status'] == 'error') {
            $reset_cart = true;
            $orderPayment = new \common\models\OrdersPayment();
            $orderPayment->orders_payment_module = $this->code;
            $orderPayment->orders_payment_module_name = $this->title;
            $orderPayment->orders_payment_transaction_id = $status['paynet-order-id'];
            $orderPayment->orders_payment_id_parent = 0;
            $orderPayment->orders_payment_order_id = $order->order_id;
            $orderPayment->orders_payment_is_credit = 0;
            $orderPayment->orders_payment_status = \common\helpers\OrderPayment::OPYS_CANCELLED;
            $orderPayment->orders_payment_amount = $this->formatCurrencyRaw($order->info['total_inc_tax'], $order->info['currency']);
            $orderPayment->orders_payment_currency = trim($order->info['currency']);
            $orderPayment->orders_payment_currency_rate = (float) $order->info['currency_value'];
            $orderPayment->orders_payment_snapshot = json_encode(\common\helpers\OrderPayment::getOrderPaymentSnapshot($order));
            $orderPayment->orders_payment_transaction_status = '';
            $orderPayment->orders_payment_transaction_commentary = '';
            $orderPayment->orders_payment_date_create = date('Y-m-d H:i:s');
            $orderPayment->orders_payment_transaction_full = json_encode($status);
            $orderPayment->save();
            \common\helpers\Order::setStatus($order->order_id, 5, [
                'comments' => '',
                'customer_notified' => 0,
            ]);
            $error_url = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . (tep_not_null($status['error-message']) ? '&error=' . $status['error-message'] : '') . '&' . tep_session_name() . '=' . tep_session_id(), 'SSL', false);
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode($result['data']['message']), 'SSL'));
            tep_redirect($error_url);
        } elseif (trim($status['status']) == 'declined') {
            $reset_cart = true;
            $orderPayment = new \common\models\OrdersPayment();
            $orderPayment->orders_payment_module = $this->code;
            $orderPayment->orders_payment_module_name = $this->title;
            $orderPayment->orders_payment_transaction_id = $status['paynet-order-id'];
            $orderPayment->orders_payment_id_parent = 0;
            $orderPayment->orders_payment_order_id = $order->order_id;
            $orderPayment->orders_payment_is_credit = 0;
            $orderPayment->orders_payment_status = \common\helpers\OrderPayment::OPYS_CANCELLED;
            $orderPayment->orders_payment_amount = $this->formatCurrencyRaw($order->info['total_inc_tax'], $order->info['currency']);
            $orderPayment->orders_payment_currency = trim($order->info['currency']);
            $orderPayment->orders_payment_currency_rate = (float) $order->info['currency_value'];
            $orderPayment->orders_payment_snapshot = json_encode(\common\helpers\OrderPayment::getOrderPaymentSnapshot($order));
            $orderPayment->orders_payment_transaction_status = '';
            $orderPayment->orders_payment_transaction_commentary = '';
            $orderPayment->orders_payment_date_create = date('Y-m-d H:i:s');
            $orderPayment->orders_payment_transaction_full = json_encode($status);
            $orderPayment->save();
            \common\helpers\Order::setStatus($order->order_id, 5, [
                'comments' => '',
                'customer_notified' => 0,
            ]);
            $error_url = tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . (tep_not_null($status['error-message']) ? '&error=' . $status['error-message'] : '') . '&' . tep_session_name() . '=' . tep_session_id(), 'SSL', false);
            tep_redirect($error_url);
        }
        if ($reset_cart) {
            tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE customers_id = '" . (int) $order->customer['id'] . "'");
            tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE customers_id = '" . (int) $order->customer['id'] . "'");
        }
    }

    public function call_webhooks() {
        $order = $this->manager->getOrderInstanceWithId('\common\classes\Order', (int)$_POST['merchant_order']);
        $status = $this->getPaymentStatus($order);
        if (trim($status['status']) == 'approved' || $status['status'] == 'approved') {
            $orderPayment = new \common\models\OrdersPayment();
            $orderPayment->orders_payment_module = $this->code;
            $orderPayment->orders_payment_module_name = $this->title;
            $orderPayment->orders_payment_transaction_id = $status['paynet-order-id'];
            $orderPayment->orders_payment_id_parent = 0;
            $orderPayment->orders_payment_order_id = $order->order_id;
            $orderPayment->orders_payment_is_credit = 0;
            $orderPayment->orders_payment_status = \common\helpers\OrderPayment::OPYS_SUCCESSFUL;
            $orderPayment->orders_payment_amount = $this->formatCurrencyRaw($order->info['total_inc_tax'], $order->info['currency']);
            $orderPayment->orders_payment_currency = trim($order->info['currency']);
            $orderPayment->orders_payment_currency_rate = (float) $order->info['currency_value'];
            $orderPayment->orders_payment_snapshot = json_encode(\common\helpers\OrderPayment::getOrderPaymentSnapshot($order));
            $orderPayment->orders_payment_transaction_status = 'OK';
            $orderPayment->orders_payment_transaction_commentary = '';
            $orderPayment->orders_payment_date_create = date('Y-m-d H:i:s');
            $orderPayment->orders_payment_transaction_full = json_encode($status);
            $orderPayment->save();
            \common\helpers\Order::setStatus($order->order_id, 100006, [
                'comments' => '',
                'customer_notified' => 0,
            ]);
            tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE customers_id = '" . (int) $order->customer['id'] . "'");
            tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE customers_id = '" . (int) $order->customer['id'] . "'");

            tep_redirect(HTTP_SERVER.'/checkout/success?order_id='.$order->order_id);
        } elseif (trim($status['status']) == 'error' || $status['status'] == 'error') {
            $orderPayment = new \common\models\OrdersPayment();
            $orderPayment->orders_payment_module = $this->code;
            $orderPayment->orders_payment_module_name = $this->title;
            $orderPayment->orders_payment_transaction_id = $status['paynet-order-id'];
            $orderPayment->orders_payment_id_parent = 0;
            $orderPayment->orders_payment_order_id = $order->order_id;
            $orderPayment->orders_payment_is_credit = 0;
            $orderPayment->orders_payment_status = \common\helpers\OrderPayment::OPYS_CANCELLED;
            $orderPayment->orders_payment_amount = $this->formatCurrencyRaw($order->info['total_inc_tax'], $order->info['currency']);
            $orderPayment->orders_payment_currency = trim($order->info['currency']);
            $orderPayment->orders_payment_currency_rate = (float) $order->info['currency_value'];
            $orderPayment->orders_payment_snapshot = json_encode(\common\helpers\OrderPayment::getOrderPaymentSnapshot($order));
            $orderPayment->orders_payment_transaction_status = '';
            $orderPayment->orders_payment_transaction_commentary = '';
            $orderPayment->orders_payment_date_create = date('Y-m-d H:i:s');
            $orderPayment->orders_payment_transaction_full = json_encode($status);
            $orderPayment->save();
            \common\helpers\Order::setStatus($order->order_id, 5, [
                'comments' => '',
                'customer_notified' => 0,
            ]);
            echo '<div class="error" style="text-align: center;width: 100%;">';
            echo '<p>Error:</p>';
            echo $status['error-message'];
            echo '<p><a class="btn-1 btn-buy" href="'.HTTP_SERVER.'">Return on homepage</a></p>';
            echo '</div>';
            tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE customers_id = '" . (int) $order->customer['id'] . "'");
            tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE customers_id = '" . (int) $order->customer['id'] . "'");
        } elseif (trim($status['status']) == 'declined') {
            $orderPayment = new \common\models\OrdersPayment();
            $orderPayment->orders_payment_module = $this->code;
            $orderPayment->orders_payment_module_name = $this->title;
            $orderPayment->orders_payment_transaction_id = $status['paynet-order-id'];
            $orderPayment->orders_payment_id_parent = 0;
            $orderPayment->orders_payment_order_id = $order->order_id;
            $orderPayment->orders_payment_is_credit = 0;
            $orderPayment->orders_payment_status = \common\helpers\OrderPayment::OPYS_CANCELLED;
            $orderPayment->orders_payment_amount = $this->formatCurrencyRaw($order->info['total_inc_tax'], $order->info['currency']);
            $orderPayment->orders_payment_currency = trim($order->info['currency']);
            $orderPayment->orders_payment_currency_rate = (float) $order->info['currency_value'];
            $orderPayment->orders_payment_snapshot = json_encode(\common\helpers\OrderPayment::getOrderPaymentSnapshot($order));
            $orderPayment->orders_payment_transaction_status = '';
            $orderPayment->orders_payment_transaction_commentary = '';
            $orderPayment->orders_payment_date_create = date('Y-m-d H:i:s');
            $orderPayment->orders_payment_transaction_full = json_encode($status);
            $orderPayment->save();
            \common\helpers\Order::setStatus($order->order_id, 5, [
                'comments' => '',
                'customer_notified' => 0,
            ]);
            echo '<div class="declined" style="text-align: center;width: 100%;">';
            echo '<p>Your payment was declined</p>';
            echo $status['error-message'];
            echo '<p><a class="btn-1 btn-buy" href="'.HTTP_SERVER.'">Return on homepage</a></p>';
            echo '</div>';
            tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE customers_id = '" . (int) $order->customer['id'] . "'");
            tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE customers_id = '" . (int) $order->customer['id'] . "'");
        }
    }

    function get_error()
    {
        $error = array('title' => 'Payment error',
            'error' => stripslashes(urldecode($_GET['error'])));
        return $error;
    }

    function pre_confirmation_check()
    {
        $payneteasy_card_number = $_POST['payneteasy-card-number'];
        $payneteasy_card_number = str_replace(' ','',$payneteasy_card_number);
        $payneteasy_card_expiry_month = $_POST['payneteasy-card-expiry-month'];
        $payneteasy_card_expiry_year = $_POST['payneteasy-card-expiry-year'];
        $payneteasy_card_name = $_POST['payneteasy-card-name'];
        $payneteasy_card_cvv = $_POST['payneteasy-card-cvv'];
        $payneteasy_card_address = $_POST['payneteasy-card-address'];
        $payneteasy_card_zip = $_POST['payneteasy-card-zip'];

        $this->manager->set('payneteasy-card-number', $payneteasy_card_number);
        $this->manager->set('payneteasy-card-expiry-month', $payneteasy_card_expiry_month);
        $this->manager->set('payneteasy-card-expiry-year', $payneteasy_card_expiry_year);
        $this->manager->set('payneteasy-card-name', $payneteasy_card_name);
        $this->manager->set('payneteasy-card-cvv', $payneteasy_card_cvv);
        $this->manager->set('payneteasy-card-address', $payneteasy_card_address);
        $this->manager->set('payneteasy-card-zip', $payneteasy_card_zip);
        $this->manager->set('payneteasy-remote-address', $_SERVER['REMOTE_ADDR']);        
    }
    
    function formatCurrencyRaw($total, $currency_code = null, $currency_value = null) {

        if (!isset($currency_code)) {
            $currency_code = DEFAULT_CURRENCY;
        }

        if (!isset($currency_value) || !is_numeric($currency_value)) {
            $currencies = \Yii::$container->get('currencies');
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(self::round($total * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }
	

    function isOnline() {
        return true;
    }

    public function configure_keys() {
        return array(
            'MODULE_PAYMENT_PAYNETEASY_STATUS' => array(
                'title' => 'Enable PaynetPos',
                'value' => 'True',
                'description' => 'Do you want to accept PaynetEasy payments?',
                'sort_order' => '1',
                'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), ',
            ),
            'MODULE_PAYMENT_PAYNETEASY_FRONT_TITLE' => array(
                'title' => 'Title',
                'value' => '',
                'description' => 'PaynetEasy APP Checkout Title',
                'sort_order' => '2',
            ),
            'MODULE_PAYMENT_PAYNETEASY_SANDBOX_URL' => array(
                'title' => 'Gateway url (SANDBOX)',
                'value' => '',
                'description' => 'https://sandbox.payneteasy.com/ etc.',
                'sort_order' => '3',
            ),
            'MODULE_PAYMENT_PAYNETEASY_LIVE_URL' => array(
                'title' => 'Gateway url (LIVE)',
                'value' => '',
                'description' => 'https://gate.payneteasy.com/ etc.',
                'sort_order' => '4',
            ),
            'MODULE_PAYMENT_PAYNETEASY_THREE_D_SECURE' => array(
                'title' => '3D Secure',
                'value' => 'No',
                'description' => '3D Secure or Non 3D Secure (WORK ONLY WITH DIRECT INTEGRATION METHOD).',
                'sort_order' => '5',
                'set_function' => 'multiOption(\'dropdown\', array(\'Yes\', \'No\'), ',
            ),
            'MODULE_PAYMENT_PAYNETEASY_ENDPOINT_ID' => array(
                'title' => 'Endpoint ID',
                'value' => '',
                'description' => 'Merchant ENDPOINT ID is required to call the API',
                'sort_order' => '6',
            ),
            'MODULE_PAYMENT_PAYNETEASY_CONTROL_KEY' => array(
                'title' => 'Control Key',
                'value' => '',
                'description' => 'Merchant Control Key is required to call the API',
                'sort_order' => '7',
            ),
            'MODULE_PAYMENT_PAYNETEASY_LOGIN' => array(
                'title' => 'Login',
                'value' => '',
                'description' => 'Request header used by the merchant resource for additional authentication when accessing the payment gateway.',
                'sort_order' => '8',
            ),
            'MODULE_PAYMENT_PAYNETEASY_SANDBOX' => array(
                'title' => 'Use Sandbox',
                'value' => 'Yes',
                'description' => 'Set No if Production Keys are set OR Set Yes if Sandbox Keys are set then Live payments will not be taken.',
                'sort_order' => '9',
                'set_function' => 'multiOption(\'dropdown\', array(\'Yes\', \'No\'), ',
            ),
            'MODULE_PAYMENT_PAYNETEASY_PAYMENT_METHOD' => array(
                'title' => 'Integration method',
                'value' => 'Direct',
                'description' => 'Select integration method (Direct or Form).',
                'sort_order' => '10',
                'set_function' => 'multiOption(\'dropdown\', array(\'Direct\', \'Form\'), ',
            ),
            'MODULE_PAYMENT_PAYNETEASY_SHOWLOGO' => array(
                'title' => 'Show Logo',
                'value' => 'Yes',
                'description' => 'Set Yes to show logo at checkout page OR Set No to show only title while selecting payment method.',
                'sort_order' => '11',
                'set_function' => 'multiOption(\'dropdown\', array(\'Yes\', \'No\'), ',
            ),
            'MODULE_PAYMENT_PAYNETEASY_ZONE' => array(
                'title' => 'Payment Zone',
                'value' => '0',
                'description' => 'If a zone is selected, only enable this payment method for that zone.',
                'sort_order' => '12',
                'use_function' => '\\common\\helpers\\Zones::get_zone_class_title',
                'set_function' => 'tep_cfg_pull_down_zone_classes(',
            ),
            'MODULE_PAYMENT_PAYNETEASY_ORDER_STATUS_ID' => array(
                'title' => 'Set Order Status',
                'value' => '0',
                'description' => 'Set the status of orders made with this payment module to this value.',
                'sort_order' => '13',
                'set_function' => 'tep_cfg_pull_down_order_statuses(',
                'use_function' => '\\common\\helpers\\Order::get_order_status_name',
            ),
            'MODULE_PAYMENT_PAYNETEASY_SORT_ORDER' => array(
                'title' => 'Sort order of display',
                'value' => '0',
                'description' => 'Sort order of PaynetEasy display. Lowest is displayed first.',
                'sort_order' => '14',
            ),
            'MODULE_PAYMENT_PAYNETEASY_ACCEPTED_CARDS' => array(
                'title' => 'Accepted Cards',
                'value' => '',
                'description' => 'Allow Credit or Debit cards while purchasing at checkout page',
                'sort_order' => '15',
                'set_function' => "tep_cfg_select_multioption(array('American Express', 'Visa', 'MasterCard', 'Discover', 'JCB', 'Diners'),",
            ),
        );
    }



    public function describe_status_key()
    {
        return new ModuleStatus('MODULE_PAYMENT_PAYNETEASY_STATUS', 'True', 'False');
    }

    public function describe_sort_key()
    {
        return new ModuleSortOrder('MODULE_PAYMENT_PAYNETEASY_SORT_ORDER');
    }

    public function getPaymentStatus($order)
    {
        $paynet_order_id = tep_db_fetch_array(tep_db_query("SELECT paynet_order_id FROM payneteasy_payments WHERE merchant_order_id = '" . $order->order_id . "'"));

        $data = [
            'login' => MODULE_PAYMENT_PAYNETEASY_LOGIN,
            'client_orderid' => (string)$order->order_id,
            'orderid' => $paynet_order_id['paynet_order_id'],
        ];
        $data['control'] = $this->signStatusRequest($data, MODULE_PAYMENT_PAYNETEASY_LOGIN, MODULE_PAYMENT_PAYNETEASY_CONTROL_KEY);

        $sandbox = MODULE_PAYMENT_PAYNETEASY_SANDBOX;
        $action_url = MODULE_PAYMENT_PAYNETEASY_LIVE_URL;

        if ($sandbox == 'Yes')
            $sandbox = true;
        else
            $sandbox = false;

        if ($sandbox)
            $action_url = MODULE_PAYMENT_PAYNETEASY_SANDBOX_URL;

        $paynetApi = new PaynetApi(
            MODULE_PAYMENT_PAYNETEASY_LOGIN,
            MODULE_PAYMENT_PAYNETEASY_CONTROL_KEY,
            MODULE_PAYMENT_PAYNETEASY_ENDPOINT_ID,
            MODULE_PAYMENT_PAYNETEASY_PAYMENT_METHOD,
            $sandbox
        );

        $response = $paynetApi->status($data, MODULE_PAYMENT_PAYNETEASY_PAYMENT_METHOD, $sandbox, $action_url, MODULE_PAYMENT_PAYNETEASY_ENDPOINT_ID);

        if (
            !isset($response['status'])
        ) {
//            throw new Exception('No information about payment status.');
        }

        return $response;
    }

    private function signStatusRequest($requestFields, $login, $merchantControl)
    {
        $base = '';
        $base .= $login;
        $base .= $requestFields['client_orderid'];
        $base .= $requestFields['orderid'];

        return $this->signString($base, $merchantControl);
    }

    private function signPaymentRequest($data, $endpointId, $merchantControl)
    {
        $base = '';
        $base .= $endpointId;
        $base .= $data['client_orderid'];
        $base .= $data['amount'] * 100;
        $base .= $data['email'];

        return $this->signString($base, $merchantControl);
    }

    private function signString($s, $merchantControl)
    {
        return sha1($s . $merchantControl);
    }

}