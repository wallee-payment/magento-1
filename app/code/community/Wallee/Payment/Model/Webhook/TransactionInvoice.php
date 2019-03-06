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
 * Webhook processor to handle transaction inovice transitions.
 */
class Wallee_Payment_Model_Webhook_TransactionInvoice extends Wallee_Payment_Model_Webhook_Transaction
{

    /**
     *
     * @see Wallee_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \Wallee\Sdk\Model\TransactionInvoice
     */
    protected function loadEntity(Wallee_Payment_Model_Webhook_Request $request)
    {
        $transactionInvoiceService = new \Wallee\Sdk\Service\TransactionInvoiceService(
            Mage::helper('wallee_payment')->getApiClient());
        return $transactionInvoiceService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getTransactionId($transactionInvoice)
    {
        /* @var \Wallee\Sdk\Model\TransactionInvoice $transactionInvoice */
        return $transactionInvoice->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $transactionInvoice)
    {
        parent::processOrderRelatedInner($order,
            $transactionInvoice->getCompletion()
                ->getLineItemVersion()
                ->getTransaction());

        /* @var \Wallee\Sdk\Model\TransactionInvoice $transactionInvoice */
        $invoice = $this->getInvoiceForTransaction($transactionInvoice->getLinkedSpaceId(),
            $transactionInvoice->getCompletion()
                ->getLineItemVersion()
                ->getTransaction()
                ->getId(), $order);
        if ($invoice == null || $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
            switch ($transactionInvoice->getState()) {
                case \Wallee\Sdk\Model\TransactionInvoiceState::NOT_APPLICABLE:
                case \Wallee\Sdk\Model\TransactionInvoiceState::PAID:
                    $this->capture($transactionInvoice->getCompletion()
                        ->getLineItemVersion()
                        ->getTransaction(), $order, $transactionInvoice->getAmount(), $invoice);
                    break;
                case \Wallee\Sdk\Model\TransactionInvoiceState::DERECOGNIZED:
                    $this->derecognize($transactionInvoice->getCompletion()
                        ->getLineItemVersion()
                        ->getTransaction(), $order, $invoice);
                default:
                    // Nothing to do.
                    break;
            }
        }
    }

    protected function capture(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order,
        $amount, Mage_Sales_Model_Order_Invoice $invoice = null)
    {
        if ($order->getWalleeCanceled()) {
            return;
        }

        $isOrderInReview = ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);

        if (! $invoice) {
            $order->setWalleePaymentInvoiceAllowManipulation(true);
            $invoice = $this->createInvoice($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        }

        if (Mage_Sales_Model_Order_Invoice::STATE_OPEN == $invoice->getState()) {
            $order->getPayment()->registerCaptureNotification($amount);
            $invoice->setWalleeCapturePending(false)->save();
        }

        if ($transaction->getState() == \Wallee\Sdk\Model\TransactionState::COMPLETED) {
            $order->setStatus('processing_wallee');
        }

        if ($isOrderInReview) {
            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
        }

        $order->save();
    }

    protected function derecognize(\Wallee\Sdk\Model\Transaction $transaction,
        Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Invoice $invoice = null)
    {
        $isOrderInReview = ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);

        $order->getPayment()->registerVoidNotification();

        if ($invoice && Mage_Sales_Model_Order_Invoice::STATE_OPEN == $invoice->getState()) {
            $invoice->setWalleeCapturePending(false);
            $order->setWalleePaymentInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
        }
        if ($isOrderInReview) {
            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
        }

        $order->save();
    }

    /**
     * Creates an invoice for the order.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function createInvoice($spaceId, $transactionId, Mage_Sales_Model_Order $order)
    {
        $invoice = $order->prepareInvoice();
        $invoice->setWalleeAllowCreation(true);
        $invoice->register();
        $invoice->setTransactionId($spaceId . '_' . $transactionId);
        $invoice->save();
        return $invoice;
    }
}