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

$installer->endSetup();