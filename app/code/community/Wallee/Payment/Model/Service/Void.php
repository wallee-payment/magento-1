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
 * This service provides functions to deal with wallee transaction voids.
 */
class Wallee_Payment_Model_Service_Void extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * The transaction void API service.
     *
     * @var \Wallee\Sdk\Service\TransactionVoidService
     */
    protected $_transactionVoidService;

    /**
     * Void the transaction of the given payment.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \Wallee\Sdk\Model\TransactionVoid
     */
    public function void(Mage_Sales_Model_Order_Payment $payment)
    {
        return $this->getTransactionVoidService()->voidOnline(
            $payment->getOrder()
                ->getWalleeSpaceId(), $payment->getOrder()
                ->getWalleeTransactionId());
    }

    /**
     * Returns the transaction void API service.
     *
     * @return \Wallee\Sdk\Service\TransactionVoidService
     */
    protected function getTransactionVoidService()
    {
        if ($this->_transactionVoidService == null) {
            $this->_transactionVoidService = new \Wallee\Sdk\Service\TransactionVoidService(
                Mage::helper('wallee_payment')->getApiClient());
        }

        return $this->_transactionVoidService;
    }
}