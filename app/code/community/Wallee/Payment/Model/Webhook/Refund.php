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

/**
 * Webhook processor to handle refund state transitions.
 */
class Wallee_Payment_Model_Webhook_Refund extends Wallee_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see Wallee_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \Wallee\Sdk\Model\Refund
     */
    protected function loadEntity(Wallee_Payment_Model_Webhook_Request $request)
    {
        $refundService = new \Wallee\Sdk\Service\RefundService(Mage::helper('wallee_payment')->getApiClient());
        return $refundService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderIncrementId($refund)
    {
        /* @var \Wallee\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($refund)
    {
        /* @var \Wallee\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getId();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $refund)
    {
        /* @var \Wallee\Sdk\Model\Refund $refund */
        switch ($refund->getState()) {
            case \Wallee\Sdk\Model\Refund::STATE_FAILED:
                $this->failed($refund, $order);
                $this->deleteRefundJob($refund);
                break;
            case \Wallee\Sdk\Model\Refund::STATE_SUCCESSFUL:
                $this->refunded($refund, $order);
                $this->deleteRefundJob($refund);
            default:
                // Nothing to do.
                break;
        }
    }

    protected function failed(\Wallee\Sdk\Model\Refund $refund, Mage_Sales_Model_Order $order)
    {
    }

    protected function refunded(\Wallee\Sdk\Model\Refund $refund, Mage_Sales_Model_Order $order)
    {

        /* @var Mage_Sales_Model_Order_Creditmemo $existingCreditmemo */
        $existingCreditmemo = Mage::getModel('sales/order_creditmemo')->load($refund->getExternalId(), 'wallee_external_id');
        if ($existingCreditmemo->getId() > 0) {
            return;
        }

        /* @var Wallee_Payment_Model_Service_Refund $refundService */
        $refundService = Mage::getSingleton('wallee_payment/service_refund');
        $refundService->registerRefundNotification($refund, $order);
    }

    protected function deleteRefundJob(\Wallee\Sdk\Model\Refund $refund)
    {
        /* @var Wallee_Payment_Model_Entity_RefundJob $refundJob */
        $refundJob = Mage::getModel('wallee_payment/entity_refundJob');
        $refundJob->loadByExternalId($refund->getExternalId());
        if ($refundJob->getId() > 0) {
            $refundJob->delete();
        }
    }
}