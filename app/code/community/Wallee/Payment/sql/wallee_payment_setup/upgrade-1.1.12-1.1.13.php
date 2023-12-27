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
 * Add a new column to the wallee_payment/refund_job table that stores adminhtml formdata from creditmemo/save
 */
$installer->getConnection()->addColumn(
    $installer->getTable('wallee_payment/refund_job'), 'adminhtml_formdata', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => '64k',
    'default' => NULL,
    'nullable' => true,
    'comment' => 'adminhtml post request creditmemo data'
    )
);

$installer->endSetup();