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

$installer->startSetup();

/**
 * Add a new column to the sales/order table that stores whether the invoice has been derecognized.
 */
$installer->getConnection()->addColumn(
    $installer->getTable('sales/order'), 'wallee_derecognized', array(
    'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
    'default' => '0',
    'comment' => 'wallee Payment Derecognized'
    )
);

$installer->endSetup();