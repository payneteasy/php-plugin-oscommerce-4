<?php

/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 * 
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 * 
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
 
namespace common\modules\orderPayment\payneteasy\backend\controllers;

use common\helpers\Translation;
use common\classes\modules\ModulePayment;
use Yii;
use common\modules\orderPayment\payneteasy\api\PaynetApi;

/**
 * default controller to handle user requests.
 */
class PayneteasyController extends \backend\controllers\Sceleton {
    
    function post_transaction($requestData, $_paynet_api_url, $sandbox)
    {

//        $json = json_encode($requestData);
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $_paynet_api_url);
//        if( $sandbox == "Yes" ) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//            'Content-Type: application/json',
//            'Content-Length: ' . strlen($json)
//        ));
//        $response = curl_exec($ch);
//        curl_close($ch);
//
//	    $response = json_decode($response);
//
//	    return $response;

    }

    function showError($error_message) {
//        $result = array(
//            'message' => '<span class="warn warning"></span> <span class="text-error">'.$error_message.'</span>',
//            'error' => true
//        );
//        echo json_encode($result);
//        exit();
    }

    public function actionRefundPayment()
    {

    }
    
    function formatCurrencyRaw($total, $currency_code = null, $currency_value = null)
    {

        if (!isset($currency_code)) {
            $currency_code = DEFAULT_CURRENCY;
        }

        if (!isset($currency_value) || !is_numeric($currency_value)) {
            $currencies = \Yii::$container->get('currencies');
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(self::round($total * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }

    public function actionBalancePayment()
    {
        if( \Yii::$app->request->isPost === FALSE ) return;
        $post = \Yii::$app->request->post();
        $opyID = $post['opyID'];
        $paymentRecord = \common\helpers\OrderPayment::getRecord($opyID);
        if ($paymentRecord instanceof \common\models\OrdersPayment) {
            $orderPaymentAmountAvailable = \common\helpers\OrderPayment::getAmountAvailable($paymentRecord);
            $result = array(
                'amount' => sprintf("%1.2f",$orderPaymentAmountAvailable),
                'message' => 'Amount already refunded!',
                'error' => false
            );
            echo json_encode($result);
        } else {
            $result = array(
                'error' => true
            );
            echo json_encode($result);
        }
    }

    public function actionReturnPayment()
    {
                if( \Yii::$app->request->isPost === FALSE ) return;

        $post = \Yii::$app->request->post();

        if( !$post['platform_id'] ) $this->showError('The Platform ID is missing.');
        elseif( !$post['opyID'] ) $this->showError('The Payment ID is missing.');
        elseif( !$post['uuid'] ) $this->showError('The UUID is missing.');
        elseif( !$post['amount'] ) $this->showError('The amount is missing.');
        elseif( !is_numeric($post['amount']) ) $this->showError('The amount is invalid.');

//        $query = tep_db_query(
//            "select configuration_key, configuration_value from " . TABLE_PLATFORMS_CONFIGURATION . " ".
//            "where platform_id = '".intval($post['platform_id'])."' AND configuration_key in (
//                'MODULE_PAYMENT_PAYNETEASY_FRONT_TITLE',
//                'MODULE_PAYMENT_PAYNETEASY_ENDPOINT_ID',
//                'MODULE_PAYMENT_PAYNETEASY_CONTROL_KEY',
//                'MODULE_PAYMENT_PAYNETEASY_LOGIN',
//                'MODULE_PAYMENT_PAYNETEASY_SANDBOX',
//            )"
//        );
//
//        while( $row = tep_db_fetch_array($query) ) {
//            define($row["configuration_key"], $row["configuration_value"]);
//        }

        $transactionId = '';

        $order_id      = 0;
        $order_currency = '';
        $partialRefund = false;
        $opyID = $post['opyID'];
        $paymentRecord = \common\helpers\OrderPayment::getRecord($opyID);
        if ($paymentRecord instanceof \common\models\OrdersPayment) {
            $order_id       = $paymentRecord["orders_payment_order_id"];
            $order_currency = $paymentRecord["orders_payment_currency"];
            $currency_value = $paymentRecord["orders_payment_currency_rate"];
            $payment        = json_decode($paymentRecord["orders_payment_transaction_full"], true);
            $transactionId  = $payment["Transaction ID"];

            $orderPaymentAmountAvailable = \common\helpers\OrderPayment::getAmountAvailable($paymentRecord);
            if ($post['amount'] > $orderPaymentAmountAvailable) {
                $post['amount'] = $orderPaymentAmountAvailable;
            }
            if ($post['amount'] <= 0) {
                $this->showError('Amount already refunded!');
            }
            if ($post['amount'] < $orderPaymentAmountAvailable) {
                $partialRefund = true;
            }
        } else {
            $this->showError('Parent payment record not found!');
        }

        if( !$order_id ) $this->showError('The order id is missing.');

        $cartInstance = new \common\classes\shopping_cart((int)$order_id);
        if (is_object($cartInstance)) {
            $managerInstance = \common\services\OrderManager::loadManager($cartInstance);
            if (is_object($managerInstance)) {
                $orderInstance = $managerInstance->getOrderInstanceWithId('\common\classes\Order', (int)$order_id);
                if (is_object($orderInstance)) {
                    Yii::$app->get('platform')->config((int)$post['platform_id'])->constant_up();
                    $managerInstance->set('platform_id', (int)$post['platform_id']);
                }
            }
        }

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

        $paynet_order_id = tep_db_fetch_array(tep_db_query("SELECT paynet_order_id FROM payneteasy_payments WHERE merchant_order_id = '" . $order_id . "'"));

        $data = [
            'login' => MODULE_PAYMENT_PAYNETEASY_LOGIN,
            'client_orderid' => $order_id,
            'orderid' => $paynet_order_id['paynet_order_id'],
            'comment' => 'Order cancel'
        ];

        $data['control'] = $this->signPaymentRequest($data, $payment_payneteasy_endpoint_id, MODULE_PAYMENT_PAYNETEASY_CONTROL_KEY);

        $response = $paynetApi->return($data, $payment_payneteasy_payment_method, $sandbox, $action_url, $payment_payneteasy_endpoint_id);

        if( trim($response['type']) == "validation-error" ) {
            $error_message = $response['error-message'];
//			if( isset($response->desc) )
//	    		$error_message .= "<br />".$response->desc;
            $result = array(
                'message' => '<span class="warn warning"></span> <span class="text-error">'.$error_message.'</span>',
                'error' => true
            );
            echo json_encode($result);
        } else {
            $currencies = \Yii::$container->get('currencies');
//            $orders_payment_transaction_id = $response->txnid;
//            $orders_payment_token = $response->token;
//            $orders_payment_rrn = $response->rrn;
//            $orders_payment_approval_code = $response->approval_code;
//
//            $result = array (
//                "Transaction ID" => $orders_payment_transaction_id,
//                "Token" => $orders_payment_token,
//                "RRN" => $orders_payment_rrn,
//                "Approval Code" => $orders_payment_approval_code
//            );
//
//            $response_string = sprintf(
//                  'PaynetPos payment %1$s for %2$s.%3$s <strong>Transaction ID:</strong>  %4$s.%5$s <strong>Approval Code:</strong> %6$s.%7$s <strong>RRN:</strong> %8$s',
//                "completed",
//                $currencies->format($post['amount'], true, $order_currency, $currency_value),
//                "<br />",
//                $orders_payment_transaction_id,
//                "<br />",
//                $orders_payment_approval_code,
//                "<br />",
//                $orders_payment_rrn
//            );
//
            $orderPayment = new \common\models\OrdersPayment();
            $orderPayment->orders_payment_module = 'payneteasy';
            $orderPayment->orders_payment_module_name = MODULE_PAYMENT_PAYNETEASY_FRONT_TITLE;
            $orderPayment->orders_payment_transaction_id = $paynet_order_id['paynet_order_id'];
            $orderPayment->orders_payment_id_parent = $opyID;
            $orderPayment->orders_payment_order_id = $order_id;
            $orderPayment->orders_payment_is_credit = 0;
            $orderPayment->deferred = 0;
            $orderPayment->orders_payment_status = \common\helpers\OrderPayment::OPYS_REFUNDED;
            $orderPayment->orders_payment_amount = $post['amount'];
            $orderPayment->orders_payment_currency = trim($order_currency);
            $orderPayment->orders_payment_currency_rate = (float) $currency_value;
            $orderPayment->orders_payment_snapshot = json_encode(\common\helpers\OrderPayment::getOrderPaymentSnapshot($orderInstance));
            $orderPayment->orders_payment_transaction_status = 'OK';
            $orderPayment->orders_payment_transaction_commentary = '';
            $orderPayment->orders_payment_date_create = date('Y-m-d H:i:s');
            $orderPayment->orders_payment_transaction_full = json_encode($response);
            global $login_id;
            $orderPayment->orders_payment_admin_create = (int)$login_id;

            if( $orderPayment->save() ) {

                $languages_id = \common\classes\language::get_id(DEFAULT_LANGUAGE);

                $updated = $orderInstance->updatePaidTotals();

                if( !$partialRefund ) {

                    //check refunded order status if exist get id or insert then get id
                    $order_status_query = tep_db_query("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_name = 'Refunded' AND language_id = '" . $languages_id . "'");
                    if ( tep_db_num_rows($order_status_query) > 0 ) {
                        $order_status = tep_db_fetch_array($order_status_query);
                        \common\helpers\Order::setStatus($order_id, (int)$order_status['orders_status_id'], [
                            'customer_notifsssied' => 1,
                        ]);
                    }

                }

                if( $partialRefund ) {

                    //check partial refunded order status if exist get id or insert then get id
                    $order_status_query = tep_db_query("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_name = 'Partially refunded' AND language_id = '" . $languages_id . "'");
                    if ( tep_db_num_rows($order_status_query) > 0 ) {
                        $order_status = tep_db_fetch_array($order_status_query);
                        \common\helpers\Order::setStatus($order_id, (int)$order_status['orders_status_id'], [
                            'customer_notifsssied' => 1,
                        ]);
                    }

                }

//                $response_string = sprintf(
//                    '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg> <h2>Success!</h2><p>The Payment %2$s has been %1$s successfully.%3$s<strong>Transaction ID:</strong>  %4$s.%5$s <strong>Approval Code:</strong> %6$s.%7$s <strong>RRN:</strong> %8$s</p><div class="buttonbox"><button type="button" id="donebutton" class="btn btn-primary">Ok</button></div>',
//                "refunded",
//                $currencies->format($post['amount'], true, $order_currency, $currency_value),
//                "<br /><br />",
//                $orders_payment_transaction_id,
//                "<br />",
//                $orders_payment_approval_code,
//                "<br />",
//                $orders_payment_rrn
//                );
//
//                $result = array(
//                    'message' => $response_string,
//                    'error' => false
//                );
//                echo json_encode($result);
//
            } else {

                $result = array(
                    'message' => '<span class="warn warning"></span> <span class="text-error">Error while updating Order totals!</span>',
                    'error' => true
                );
                echo json_encode($result);

            }

        }
    }

    private function signPaymentRequest($requestFields, $endpointId, $merchantControl)
    {
        $base = '';
        $base .= $endpointId;
        $base .= $requestFields['client_orderid'];
        $base .= $requestFields['amount'] * 100;
        $base .= $requestFields['email'];

        return $this->signString($base, $merchantControl);
    }

    private function signString($s, $merchantControl)
    {
        return sha1($s . $merchantControl);
    }

}