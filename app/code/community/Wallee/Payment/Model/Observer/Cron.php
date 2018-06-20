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
 * The observer handles cron jobs.
 */
class Wallee_Payment_Model_Observer_Cron
{

    /**
     * Tries to send all pending refunds to the gateway.
     */
    public function processRefundJobs()
    {
        /* @var Wallee_Payment_Model_Service_Refund $refundService */
        $refundService = Mage::getSingleton('wallee_payment/service_refund');

        /* @var Wallee_Payment_Model_Resource_RefundJob_Collection $refundJobCollection */
        $refundJobCollection = Mage::getModel('wallee_payment/entity_refundJob')->getCollection();
        $refundJobCollection->setPageSize(100);
        foreach ($refundJobCollection->getItems() as $refundJob) {
            /* @var Wallee_Payment_Model_Entity_RefundJob $refundJob */
            try {
                $refundService->refund($refundJob->getSpaceId(), $refundJob->getRefund());
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }
}