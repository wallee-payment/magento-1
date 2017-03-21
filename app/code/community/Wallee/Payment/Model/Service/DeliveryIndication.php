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
 * This service provides functions to deal with Wallee delivery indications.
 */
class Wallee_Payment_Model_Service_DeliveryIndication extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * The delivery indication API service.
     *
     * @var \Wallee\Sdk\Service\DeliveryIndicationService
     */
    private $deliveryIndicationService;

    /**
     * Marks the delivery indication belonging to the given payment as suitable.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    public function markAsSuitable(Mage_Sales_Model_Order_Payment $payment)
    {
        $deliveryIndication = $this->getDeliveryIndicationForTransaction(
            $payment->getOrder()
            ->getWalleeSpaceId(), $payment->getOrder()
            ->getWalleeTransactionId()
        );
        return $this->getDeliveryIndicationService()->deliveryIndicationMarkAsSuitablePost($deliveryIndication->getLinkedSpaceId(), $deliveryIndication->getId());
    }

    /**
     * Marks the delivery indication belonging to the given payment as not suitable.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    public function markAsNotSuitable(Mage_Sales_Model_Order_Payment $payment)
    {
        $deliveryIndication = $this->getDeliveryIndicationForTransaction(
            $payment->getOrder()
            ->getWalleeSpaceId(), $payment->getOrder()
            ->getWalleeTransactionId()
        );
        return $this->getDeliveryIndicationService()->deliveryIndicationMarkAsNotSuitablePost($deliveryIndication->getLinkedSpaceId(), $deliveryIndication->getId());
    }

    /**
     * Returns the delivery indication API service..
     *
     * @return \Wallee\Sdk\Service\DeliveryIndicationService
     */
    private function getDeliveryIndicationService()
    {
        if ($this->deliveryIndicationService == null) {
            $this->deliveryIndicationService = new \Wallee\Sdk\Service\DeliveryIndicationService($this->getHelper()->getApiClient());
        }

        return $this->deliveryIndicationService;
    }

    /**
     * Returns the delivery indication for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \Wallee\Sdk\Model\DeliveryIndication
     */
    private function getDeliveryIndicationForTransaction($spaceId, $transactionId)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $query->setFilter($this->createEntityFilter('transaction.id', $transactionId));
        $query->setNumberOfEntities(1);
        return current($this->getDeliveryIndicationService()->deliveryIndicationSearchPost($spaceId, $query));
    }
}