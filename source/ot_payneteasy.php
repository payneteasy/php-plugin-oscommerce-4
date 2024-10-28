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
namespace common\modules\orderTotal;

use common\classes\modules\ModuleTotal;
use common\classes\modules\ModuleStatus;
use common\classes\modules\ModuleSortOrder;

class ot_payneteasy extends ModuleTotal {

    var $title, $output;

    protected $visibility = [
        'admin',
        'shop_order',
    ];
    
    function __construct() {
        parent::__construct();

        $this->code = 'ot_payneteasy';
        $this->title = '';
        if (!defined('MODULE_ORDER_TOTAL_PAYNETEASY_STATUS')) {
            $this->enabled = false;
            return false;
        }
        $this->enabled = ((MODULE_ORDER_TOTAL_PAYNETEASY_STATUS == 'true') ? true : false);
        $this->sort_order = MODULE_ORDER_TOTAL_PAYNETEASY_SORT_ORDER;

        $this->output = array();
    }

    function process($replacing_value = -1) {
        $module = $this->manager->getPayment();
	
	$this->output = [];

	if( MODULE_PAYMENT_PAYNETEASY_STATUS == 'True' && $module == "payneteasy" ) {
         
            $order = $this->manager->getOrderInstance();
            \common\helpers\Php8::nullArrProps($order->info, ['total_paid_exc_tax', 'total_paid_inc_tax', 'currency', 'currency_value']);
            $currencies = \Yii::$container->get('currencies');
            $this->output = [];
	}
    }

    public function describe_status_key() {
        return new ModuleStatus('MODULE_ORDER_TOTAL_PAYNETEASY_STATUS', 'true', 'false');
    }

    public function describe_sort_key() {
        return new ModuleSortOrder('MODULE_ORDER_TOTAL_PAYNETEASY_SORT_ORDER');
    }

    public function configure_keys() {
        return array(
            'MODULE_ORDER_TOTAL_PAYNETEASY_STATUS' =>
            array(
                'value' => 'true',
                'sort_order' => '1',
                'set_function' => 'tep_cfg_select_option(array(\'true\', \'false\'), ',
            ),
            'MODULE_ORDER_TOTAL_PAYNETEASY_SORT_ORDER' =>
            array(
                'title' => 'Sort Order',
                'value' => '90',
                'description' => 'Sort order of display.',
                'sort_order' => '2',
            ),
        );
    }

}
