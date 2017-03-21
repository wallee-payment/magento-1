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
 * Webhook processor to handle delivery indication state transitions.
 */
class Wallee_Payment_Model_Webhook_DeliveryIndication extends Wallee_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see Wallee_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    protected function loadEntity(Wallee_Payment_Model_Webhook_Request $request)
    {
        $deliveryIndicationService = new \Wallee\Sdk\Service\DeliveryIndicationService(Mage::helper('wallee_payment')->getApiClient());
        return $deliveryIndicationService->deliveryIndicationReadGet($request->getSpaceId(), $request->getEntityId());
    }

    protected function getLockType()
    {
        return Wallee_Payment_Model_Service_Lock::TYPE_DELIVERY_INDICATION;
    }

    protected function getOrderIncrementId($deliveryIndication)
    {
        /* @var \Wallee\Sdk\Model\DeliveryIndication $deliveryIndication */
        return $deliveryIndication->getTransaction()
                ->getMerchantReference();
    }

    protected function getTransactionId($deliveryIndication)
    {
        /* @var \Wallee\Sdk\Model\DeliveryIndication $deliveryIndication */
        return $deliveryIndication->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $deliveryIndication)
    {
        /* @var \Wallee\Sdk\Model\DeliveryIndication $deliveryIndication */
        switch ($deliveryIndication->getState()) {
            case \Wallee\Sdk\Model\DeliveryIndication::STATE_MANUAL_CHECK_REQUIRED:
                $this->review($order);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    private function review(Mage_Sales_Model_Order $order)
    {
        if ($order->getState() != Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, Mage::helper('wallee_payment')->__('A manual decision about whether to accept the payment is required.'));
            $order->save();
        }
    }
}