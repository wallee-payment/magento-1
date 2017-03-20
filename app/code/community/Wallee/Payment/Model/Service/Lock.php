<?php

/**
 * Wallee Magento
 *
 * This Magento extension enables to process payments with Wallee (https://wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 * @link https://github.com/wallee-payment/magento
 */

/**
 * This service provides functions to lock database entities.
 */
class Wallee_Payment_Model_Service_Lock
{

    const TYPE_DELIVERY_INDICATION = 1;

    const TYPE_REFUND = 2;

    const TYPE_TRANSACTION = 3;

    const TYPE_TRANSACTION_COMPLETION = 4;

    const TYPE_TRANSACTION_INVOICE = 5;

    /**
     * Create a lock to prevent concurrency.
     *
     * @param int $lockType
     */
    public function lock($lockType)
    {
        /* @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('wallee_payment/lock'), array(
            'locked_at' => date("Y-m-d H:i:s")
            ), array(
            'lock_type = ?' => $lockType
            )
        );
    }
}