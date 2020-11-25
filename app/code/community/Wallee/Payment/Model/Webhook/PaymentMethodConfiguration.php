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
 * Webhook processor to handle payment method configuration state transitions.
 */
class Wallee_Payment_Model_Webhook_PaymentMethodConfiguration extends Wallee_Payment_Model_Webhook_Abstract
{

    /**
     * Synchronizes the payment method configurations on state transition.
     *
     * @param Wallee_Payment_Model_Webhook_Request $request
     */
    protected function process(Wallee_Payment_Model_Webhook_Request $request)
    {
        /* @var Wallee_Payment_Model_Service_PaymentMethodConfiguration $paymentMethodConfigurationService */
        $paymentMethodConfigurationService = Mage::getSingleton(
            'wallee_payment/service_paymentMethodConfiguration');
        $paymentMethodConfigurationService->synchronize();
    }
}