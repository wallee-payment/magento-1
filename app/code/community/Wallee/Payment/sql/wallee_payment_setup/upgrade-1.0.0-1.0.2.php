<?php

/**
 * wallee Magento
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
 * Add column to store failure reason on the transaction info.
 */
$installer->getConnection()->addColumn(
    $installer->getTable('wallee_payment/transaction_info'), 'failure_reason', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => '64k',
    'nullable' => true,
    'comment' => 'Failure Reason'
    )
);

$installer->endSetup();