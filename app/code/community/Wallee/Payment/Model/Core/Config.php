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
 * Handles the dynamic payment method configs.
 */
class Wallee_Payment_Model_Core_Config extends Mage_Core_Model_Config
{
    
    protected $_cacheSections = array(
        'admin'     => 0,
        'adminhtml' => 0,
        'crontab'   => 0,
        'install'   => 0,
        'stores'    => 1,
        'websites'  => 0,
        'wallee'    => 0
    );
    
    public function loadDb()
    {
        parent::loadDb();
        
        Mage::getModel('wallee_payment/system_config')->initConfigValues();
    }
    
}