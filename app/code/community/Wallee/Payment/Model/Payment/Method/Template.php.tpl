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

class Wallee_Payment_Model_PaymentMethod{id} extends Wallee_Payment_Model_Payment_Method_Abstract
{
    protected $_code = 'wallee_payment_{id}';
    
    protected $_paymentMethodConfigurationId = {id};
}