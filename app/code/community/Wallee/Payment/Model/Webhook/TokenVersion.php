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
 * Webhook processor to handle token version state transitions.
 */
class Wallee_Payment_Model_Webhook_TokenVersion extends Wallee_Payment_Model_Webhook_Abstract
{

    protected function process(Wallee_Payment_Model_Webhook_Request $request)
    {
        /* @var Wallee_Payment_Model_Service_Token $tokenService */
        $tokenService = Mage::getSingleton('wallee_payment/service_token');
        $tokenService->updateTokenVersion($request->getSpaceId(), $request->getEntityId());
    }
}