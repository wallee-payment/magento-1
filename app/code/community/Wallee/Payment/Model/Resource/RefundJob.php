<?php

/**
 * wallee Magento 1
 *
 * This Magento extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * Resource model of refund job.
 */
class Wallee_Payment_Model_Resource_RefundJob extends Mage_Core_Model_Resource_Db_Abstract
{

    protected $_serializableFields = array(
        'refund' => array(
            null,
            array()
        )
    );

    protected function _construct()
    {
        $this->_init('wallee_payment/refund_job', 'entity_id');
    }
}