<?php

/**
 * wallee Magento
 *
 * This Magento extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * The observer handles payment related events.
 */
class Wallee_Payment_Model_Observer_Payment
{

    /**
     * Stores the invoice during a capture request.
     *
     * This is necessary to be able to collect the line items for partial captures.
     *
     * @param Varien_Event_Observer $observer
     */
    public function capturePayment(Varien_Event_Observer $observer)
    {
        Mage::unregister('wallee_payment_capture_invoice');
        Mage::register('wallee_payment_capture_invoice', $observer->getInvoice());
    }

    /**
     * Ensures that an invoice with pending capture cannot be cancelled and that the order state is set correctly after cancelling an invoice.
     *
     * @param Varien_Event_Observer $observer
     * @throws Mage_Core_Exception
     */
    public function cancelInvoice(Varien_Event_Observer $observer)
    {
        /* @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $observer->getInvoice();

        /* @var Mage_Sales_Model_Order $order */
        $order = $invoice->getOrder();

        // Skip the following checks if the order's payment method is not by wallee.
        if (! ($order->getPayment()->getMethodInstance() instanceof Wallee_Payment_Model_Payment_Method_Abstract)) {
            return;
        }

        // If there is a pending capture, the invoice cannot be cancelled.
        if ($invoice->getWalleeCapturePending()) {
            Mage::throwException('The invoice cannot be cancelled as it\'s capture has already been requested.');
        }

        // This allows to skip the following checks in certain situations.
        if ($order->getWalleePaymentInvoiceAllowManipulation()) {
            return;
        }

        // The invoice can only be cancelled by the merchant if the transaction is in state 'AUTHORIZED'.
        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        $transaction = $transactionService->getTransaction($order->getWalleeSpaceId(), $order->getWalleeTransactionId());
        if ($transaction->getState() != \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
            Mage::throwException(Mage::helper('wallee_payment')->__('The invoice cannot be cancelled.'));
        }

        // Make sure the order is in the correct state after the invoice has been cancelled.
        $methodInstance = $order->getPayment()->getMethodInstance();
        if ($methodInstance instanceof Wallee_Payment_Model_Payment_Method_Abstract) {
            /* @var Wallee_Payment_Model_Entity_TransactionInfo $transactionInfo */
            $transactionInfo = Mage::getModel('wallee_payment/entity_transactionInfo')->loadByOrder($order);
            if ($transactionInfo->getState() != \Wallee\Sdk\Model\TransactionInvoiceState::DERECOGNIZED) {
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing_wallee');
            }
        }
    }

    /**
     * Ensures that an invoice can only be created if possible.
     *
     * - Only one uncancelled invoice can exist per order.
     * - The transaction has to be in state authorized.
     *
     * @param Varien_Event_Observer $observer
     * @throws Mage_Core_Exception
     */
    public function registerInvoice(Varien_Event_Observer $observer)
    {
        /* @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $observer->getInvoice();

        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();

        // Skip the following checks if the order's payment method is not by wallee.
        if (! ($order->getPayment()->getMethodInstance() instanceof Wallee_Payment_Model_Payment_Method_Abstract)) {
            return;
        }

        // Allow creating the invoice if there is no existing one for the order.
        if ($order->getInvoiceCollection()->count() == 1) {
            return;
        }

        // Only allow to create a new invoice if all previous invoices of the order have been cancelled.
        if (! $this->canCreateInvoice($order)) {
            Mage::throwException(Mage::helper('wallee_payment')->__('Only one invoice is allowed. To change the invoice, cancel the existing one first.'));
        }

        if ($invoice->getWalleeCapturePending()) {
            return;
        }

        $invoice->setTransactionId($order->getWalleeSpaceId() . '_' . $order->getWalleeTransactionId());

        // This allows to skip the following checks in certain situations.
        if ($order->getWalleePaymentInvoiceAllowManipulation()) {
            return;
        }

        // The invoice can only be created by the merchant if the transaction is in state 'AUTHORIZED'.
        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        $transaction = $transactionService->getTransaction($order->getWalleeSpaceId(), $order->getWalleeTransactionId());
        if ($transaction->getState() != \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
            Mage::throwException(Mage::helper('wallee_payment')->__('The invoice cannot be created.'));
        }

        // Completes the transaction on the gateway if necessary, otherwise just update the line items.
        if ($invoice->getWalleePaymentNeedsCapture()) {
            $order->getPayment()
                ->getMethodInstance()
                ->complete($order->getPayment(), $invoice, $invoice->getGrandTotal());
        } else {
            /* @var Wallee_Payment_Model_Service_LineItem $lineItemCollection */
            $lineItemCollection = Mage::getSingleton('wallee_payment/service_lineItem');
            $lineItems = $lineItemCollection->collectInvoiceLineItems($invoice, $invoice->getGrandTotal());
            $transactionService->updateLineItems($order->getWalleeSpaceId(), $order->getWalleeTransactionId(), $lineItems);
        }
    }

    /**
     * Activates the quote after creating the order to handle the user going back in the browser history correctly.
     *
     * Applies the charge flow to the order after it is placed.
     *
     * @param Varien_Event_Observer $observer
     */
    public function quoteSubmitSuccess(Varien_Event_Observer $observer)
    {
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();

        if ($order->getPayment()->getMethodInstance() instanceof Wallee_Payment_Model_Payment_Method_Abstract) {
            /* @var Mage_Sales_Model_Quote $quote */
            $quote = $observer->getQuote();
            $quote->setWalleeTransactionId(null);
            $quote->setIsActive(true)->setReservedOrderId(null);
        }

        // Apply a charge flow to the transaction after the order was created from the backend.
        if ($order->getWalleeChargeFlow() && Mage::app()->getStore()->isAdmin()) {
            /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
            $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
            $transaction = $transactionService->getTransaction($order->getWalleeSpaceId(), $order->getWalleeTransactionId());

            /* @var Wallee_Payment_Model_Service_ChargeFlow $chargeFlowService */
            $chargeFlowService = Mage::getSingleton('wallee_payment/service_chargeFlow');
            $chargeFlowService->applyFlow($transaction);

            if ($order->getWalleeToken()) {
                /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
                $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
                $transactionService->waitForTransactionState(
                    $order, array(
                    \Wallee\Sdk\Model\TransactionState::CONFIRMED,
                    \Wallee\Sdk\Model\TransactionState::PENDING,
                    \Wallee\Sdk\Model\TransactionState::PROCESSING
                    )
                );
            }
        }
    }

    /**
     * Reset the payment information in the quote.
     *
     * @param Varien_Event_Observer $observer
     */
    public function convertOrderToQuote(Varien_Event_Observer $observer)
    {
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();

        /* @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getQuote();

        if ($order->getPayment()->getMethodInstance() instanceof Wallee_Payment_Model_Payment_Method_Abstract) {
            $quote->setWalleeTransactionId(null);
        }
    }

    /**
     * Returns whether an invoice can be created for the given order, i.e.
     * there is no existing uncancelled invoice.
     *
     * @param Mage_Sales_Model_Order $order
     * @return boolean
     */
    private function canCreateInvoice(Mage_Sales_Model_Order $order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getId() && $invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_CANCELED) {
                return false;
            }
        }

        return true;
    }
}