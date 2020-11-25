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
 * Resource collection of refund job.
 */
class Wallee_Payment_Model_Resource_RefundJob_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{

    protected function _construct()
    {
        $this->_init('wallee_payment/entity_refundJob');
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();
        foreach ($this->_items as $item) {
            $item->getResource()->unserializeFields($item);
        }
    }
}