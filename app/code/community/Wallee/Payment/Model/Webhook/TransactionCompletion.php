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
 * Webhook processor to handle transaction completion state transitions.
 */
class Wallee_Payment_Model_Webhook_TransactionCompletion extends Wallee_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see Wallee_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \Wallee\Sdk\Model\TransactionCompletion
     */
    protected function loadEntity(Wallee_Payment_Model_Webhook_Request $request)
    {
        $completionService = new \Wallee\Sdk\Service\TransactionCompletionService(Mage::helper('wallee_payment')->getApiClient());
        return $completionService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderIncrementId($completion)
    {
        /* @var \Wallee\Sdk\Model\TransactionCompletion $completion */
        return $completion->getLineItemVersion()
            ->getTransaction()
            ->getMerchantReference();
    }

    protected function getTransactionId($completion)
    {
        /* @var \Wallee\Sdk\Model\TransactionCompletion $completion */
        return $completion->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $completion)
    {
        /* @var \Wallee\Sdk\Model\TransactionCompletion $completion */
        switch ($completion->getState()) {
            case \Wallee\Sdk\Model\TransactionCompletionState::FAILED:
                $this->failed(
                    $completion->getLineItemVersion()
                    ->getTransaction(), $order
                );
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function failed(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        $invoice = $this->getInvoiceForTransaction($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        if ($invoice && $invoice->getWalleeCapturePending() && $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
            $invoice->setWalleeCapturePending(false);

            $authTransaction = $order->getPayment()->getAuthorizationTransaction();
            $authTransaction->setIsClosed(0);

            Mage::getModel('core/resource_transaction')->addObject($invoice)
                ->addObject($authTransaction)
                ->save();
        }
    }

    /**
     * Returns the invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function getInvoiceForTransaction($spaceId, $transactionId, Mage_Sales_Model_Order $order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if (strpos($invoice->getTransactionId(), $spaceId . '_' . $transactionId) === 0 && $invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_CANCELED) {
                $invoice->load($invoice->getId());
                return $invoice;
            }
        }

        return false;
    }
}