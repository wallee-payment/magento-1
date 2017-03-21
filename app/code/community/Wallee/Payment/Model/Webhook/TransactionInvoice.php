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
 * Webhook processor to handle transaction inovice transitions.
 */
class Wallee_Payment_Model_Webhook_TransactionInvoice extends Wallee_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see Wallee_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \Wallee\Sdk\Model\TransactionInvoice
     */
    protected function loadEntity(Wallee_Payment_Model_Webhook_Request $request)
    {
        $transactionInvoiceService = new \Wallee\Sdk\Service\TransactionInvoiceService(Mage::helper('wallee_payment')->getApiClient());
        return $transactionInvoiceService->transactionInvoiceReadGet($request->getSpaceId(), $request->getEntityId());
    }

    protected function getLockType()
    {
        return Wallee_Payment_Model_Service_Lock::TYPE_TRANSACTION_INVOICE;
    }

    protected function getOrderIncrementId($transactionInvoice)
    {
        /* @var \Wallee\Sdk\Model\TransactionInvoice $transactionInvoice */
        return $transactionInvoice->getCompletion()
            ->getLineItemVersion()
            ->getTransaction()
            ->getMerchantReference();
    }

    protected function getTransactionId($transactionInvoice)
    {
        /* @var \Wallee\Sdk\Model\TransactionInvoice $transactionInvoice */
        return $transactionInvoice->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $transactionInvoice)
    {
        /* @var \Wallee\Sdk\Model\TransactionInvoice $transactionInvoice */
        $invoice = $this->getInvoiceForTransaction(
            $transactionInvoice->getLinkedSpaceId(), $transactionInvoice->getCompletion()
            ->getLineItemVersion()
            ->getTransaction()
            ->getId(), $order
        );
        if (! $invoice || $invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_OPEN) {
            switch ($transactionInvoice->getState()) {
                case \Wallee\Sdk\Model\TransactionInvoice::STATE_NOT_APPLICABLE:
                case \Wallee\Sdk\Model\TransactionInvoice::STATE_PAID:
                    $this->capture(
                        $transactionInvoice->getCompletion()
                        ->getLineItemVersion()
                        ->getTransaction(), $order, $invoice, $transactionInvoice->getAmount()
                    );
                    break;
                case \Wallee\Sdk\Model\TransactionInvoice::STATE_DERECOGNIZED:
                    $this->derecognize(
                        $transactionInvoice->getCompletion()
                        ->getLineItemVersion()
                        ->getTransaction(), $order, $invoice
                    );
                default:
                    // Nothing to do.
                    break;
            }
        }
    }

    private function capture(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Invoice $invoice, $amount)
    {
        $isOrderInReview = ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);

        if (! $invoice) {
            $order->setWalleePaymentInvoiceAllowManipulation(true);
            $invoice = $this->createInvoice($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        }

        if (Mage_Sales_Model_Order_Invoice::STATE_OPEN == $invoice->getState()) {
            $order->getPayment()->registerCaptureNotification($amount);
            $invoice->setWalleeCapturePending(false)->save();
        }

        $this->sendOrderEmail($order);
        if ($transaction->getState() == \Wallee\Sdk\Model\Transaction::STATE_COMPLETED) {
            $order->setStatus('processing_wallee');
        }

        if ($isOrderInReview) {
            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
        }

        $order->save();
    }

    private function derecognize(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Invoice $invoice)
    {
        if ($invoice && Mage_Sales_Model_Order_Invoice::STATE_OPEN == $invoice->getState()) {
            $isOrderInReview = ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);

            $order->getPayment()->registerVoidNotification();

            $invoice->setWalleeCapturePending(false);
            $order->setWalleePaymentInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);

            if ($isOrderInReview) {
                $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
            }

            $order->save();
        }
    }

    /**
     * Sends the order email if not already sent.
     *
     * @param Mage_Sales_Model_Order $order
     */
    private function sendOrderEmail(Mage_Sales_Model_Order $order)
    {
        if ($order->getStore()->getConfig('wallee_payment/email/order') && ! $order->getEmailSent()) {
            $order->sendNewOrderEmail();
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
    private function getInvoiceForTransaction($spaceId, $transactionId, Mage_Sales_Model_Order $order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if (strpos($invoice->getTransactionId(), $spaceId . '_' . $transactionId) === 0 && $invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_CANCELED) {
                $invoice->load($invoice->getId());
                return $invoice;
            }
        }

        return false;
    }

    /**
     * Creates an invoice for the order.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Order_Invoice
     */
    private function createInvoice($spaceId, $transactionId, Mage_Sales_Model_Order $order)
    {
        $invoice = $order->prepareInvoice();
        $invoice->register();
        $invoice->setTransactionId($spaceId . '_' . $transactionId);
        $invoice->save();
        return $invoice;
    }
}