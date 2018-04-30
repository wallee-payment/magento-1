<?php

/**
 * Wallee Magento
 *
 * This Magento extension enables to process payments with Wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * This service provides functions to deal with wallee transaction completions.
 */
class Wallee_Payment_Model_Service_TransactionCompletion extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * The transaction completion API service.
     *
     * @var \Wallee\Sdk\Service\TransactionCompletionService
     */
    private $transactionCompletionService;

    /**
     * Completes a transaction completion.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return \Wallee\Sdk\Model\TransactionCompletion
     */
    public function complete(Mage_Sales_Model_Order_Payment $payment)
    {
        return $this->getTransactionCompletionService()->completeOnline(
            $payment->getOrder()
            ->getWalleeSpaceId(), $payment->getOrder()
            ->getWalleeTransactionId()
        );
    }

    /**
     * Returns the transaction completion API service.
     *
     * @return \Wallee\Sdk\Service\TransactionCompletionService
     */
    protected function getTransactionCompletionService()
    {
        if ($this->transactionCompletionService == null) {
            $this->transactionCompletionService = new \Wallee\Sdk\Service\TransactionCompletionService($this->getHelper()->getApiClient());
        }

        return $this->transactionCompletionService;
    }
}