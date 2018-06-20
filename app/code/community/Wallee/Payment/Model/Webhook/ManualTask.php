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

/**
 * Webhook processor to handle manual task state transitions.
 */
class Wallee_Payment_Model_Webhook_ManualTask extends Wallee_Payment_Model_Webhook_Abstract
{

    /**
     * Updates the number of open manual tasks.
     *
     * @param Wallee_Payment_Model_Webhook_Request $request
     */
    protected function process(Wallee_Payment_Model_Webhook_Request $request)
    {
        /* @var Wallee_Payment_Model_Service_ManualTask $manualTaskService */
        $manualTaskService = Mage::getSingleton('wallee_payment/service_manualTask');
        $manualTaskService->update();
    }
}