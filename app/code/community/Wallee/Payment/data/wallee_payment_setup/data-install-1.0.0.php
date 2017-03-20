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

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$connection = $installer->getConnection();

$installer->startSetup();

/**
 * Insert order status 'Hold Delivery'.
 */
$data = array(
    array(
        'processing_wallee',
        'Hold Delivery'
    )
);
$installer->getConnection()->insertArray(
    $installer->getTable('sales/order_status'), array(
    'status',
    'label'
    ), $data
);

/**
 * Assign order status 'Hold Delivery' to state 'processing'.
 */
$data = array(
    array(
        'processing_wallee',
        'processing',
        0
    )
);
$installer->getConnection()->insertArray(
    $installer->getTable('sales/order_status_state'), array(
    'status',
    'state',
    'is_default'
    ), $data
);

/**
 * Insert lock types.
 */
$data = array(
    array(
        Wallee_Payment_Model_Service_Lock::TYPE_DELIVERY_INDICATION
    ),
    array(
        Wallee_Payment_Model_Service_Lock::TYPE_REFUND
    ),
    array(
        Wallee_Payment_Model_Service_Lock::TYPE_TRANSACTION
    ),
    array(
        Wallee_Payment_Model_Service_Lock::TYPE_TRANSACTION_COMPLETION
    ),
    array(
        Wallee_Payment_Model_Service_Lock::TYPE_TRANSACTION_INVOICE
    )
);
$installer->getConnection()->insertArray(
    $installer->getTable('wallee_payment/lock'), array(
    'lock_type'
    ), $data
);

$installer->endSetup();