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

class Wallee_Payment_Model_PaymentMethod{id} extends Wallee_Payment_Model_Payment_Method_Abstract
{
    protected $_code = 'wallee_payment_{id}';
    
    protected $_paymentMethodConfigurationId = {id};
}