<?php

/**
 * wallee Magento 1
 *
 * This Magento extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

/**
 * Add column to transaction info for resource domain.
 */
$installer->getConnection()->addColumn(
    $installer->getTable('wallee_payment/transaction_info'), 'resource_domain', array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length' => '512',
        'nullable' => true,
        'comment' => 'Resource Domain'
    )
);

/**
 * Add column to payment method configuration for resource domain.
 */
$installer->getConnection()->addColumn(
    $installer->getTable('wallee_payment/payment_method_configuration'), 'resource_domain', array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length' => '512',
        'nullable' => true,
        'comment' => 'Resource Domain'
    )
);

$installer->endSetup();