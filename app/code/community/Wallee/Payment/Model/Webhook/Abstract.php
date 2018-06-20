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
 * Abstract webhook processor.
 */
abstract class Wallee_Payment_Model_Webhook_Abstract
{

    /**
     * Listens for an event call.
     *
     * @param Varien_Event_Observer $observer
     */
    public function listen(Varien_Event_Observer $observer)
    {
        $this->process($observer->getRequest());
    }

    /**
     * Processes the received webhook request.
     *
     * @param Wallee_Payment_Model_Webhook_Request $request
     */
    abstract protected function process(Wallee_Payment_Model_Webhook_Request $request);

}