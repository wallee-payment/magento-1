<?php
use Wallee\Sdk\Model\TransactionState;

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
 * Abstract model for wallee payment methods.
 */
class Wallee_Payment_Model_Payment_Method_Abstract extends Mage_Payment_Model_Method_Abstract
{

    protected $_paymentMethodConfigurationId;

    /**
     * The payment method configuration.
     *
     * @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration
     */
    protected $_paymentMethodConfiguration;

    protected $_formBlockType = 'wallee_payment/payment_form';

    protected $_infoBlockType = 'wallee_payment/payment_info';

    protected $_isGateway = true;

    protected $_canAuthorize = false;

    protected $_canCapture = true;

    protected $_canCapturePartial = true;

    protected $_canRefund = true;

    protected $_canRefundInvoicePartial = true;

    protected $_canVoid = true;

    protected $_canUseInternal = true;

    protected $_canUseCheckout = true;

    protected $_canUseForMultishipping = false;

    protected $_isInitializeNeeded = true;

    protected $_canFetchTransactionInfo = false;

    protected $_canReviewPayment = true;

    protected $_canManageRecurringProfiles = false;

    /**
     * Returns the id of the payment method configuration.
     *
     * @return int
     */
    public function getPaymentMethodConfigurationId()
    {
        return $this->_paymentMethodConfigurationId;
    }

    /**
     * Returns the payment method configuration.
     *
     * @return Wallee_Payment_Model_Entity_PaymentMethodConfiguration
     */
    public function getPaymentMethodConfiguration()
    {
        if ($this->_paymentMethodConfiguration == null) {
            $this->_paymentMethodConfiguration = Mage::getModel(
                'wallee_payment/entity_paymentMethodConfiguration')->load(
                $this->_paymentMethodConfigurationId);
        }

        return $this->_paymentMethodConfiguration;
    }

    public function canVoid(Varien_Object $payment)
    {
        if (! $payment->getOrder()) {
            return parent::canVoid($payment);
        }

        if (! parent::canVoid($payment)) {
            return false;
        }

        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        try {
            $transaction = $transactionService->getTransaction(
                $payment->getOrder()
                    ->getWalleeSpaceId(),
                $payment->getOrder()
                    ->getWalleeTransactionId());
            if ($transaction instanceof \Wallee\Sdk\Model\Transaction) {
                return $transaction->getState() == TransactionState::AUTHORIZED;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function canRefund()
    {
        if (! parent::canRefund()) {
            return false;
        }

        return ! $this->hasExistingRefundJob($this->getInfoInstance()
            ->getOrder());
    }

    public function canRefundPartialPerInvoice()
    {
        if (! parent::canRefundPartialPerInvoice()) {
            return false;
        }

        return ! $this->hasExistingRefundJob($this->getInfoInstance()
            ->getOrder());
    }

    /**
     * Returns whether there is an existing refund job for the given order.
     *
     * @param int|Mage_Sales_Model_Order $order
     * @return boolean
     */
    protected function hasExistingRefundJob($order)
    {
        /* @var Wallee_Payment_Model_Entity_RefundJob $existingRefundJob */
        $existingRefundJob = Mage::getModel('wallee_payment/entity_refundJob');
        $existingRefundJob->loadByOrder($order);
        return $existingRefundJob->getId() > 0;
    }

    /**
     * Returns whether this payment method can be used with the given quote.
     *
     * It will perform an online check on wallee.
     *
     * @see Mage_Payment_Model_Method_Abstract::isAvailable()
     */
    public function isAvailable($quote = null)
    {
        $isAvailable = parent::isAvailable($quote);
        if (! $isAvailable) {
            return false;
        }

        if ($quote != null && $quote->getGrandTotal() < 0.0001) {
            return false;
        }

        $client = $this->getHelper()->getApiClient(true);
        if ($quote != null && $client) {
            $spaceId = $quote->getStore()->getConfig('wallee_payment/general/space_id');
            if ($spaceId) {
                /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
                $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
                try {
                    $possiblePaymentMethods = $transactionService->getPossiblePaymentMethods($quote);
                    $paymentMethodPossible = false;
                    foreach ($possiblePaymentMethods as $possiblePaymentMethod) {
                        if ($possiblePaymentMethod->getId() ==
                            $this->getPaymentMethodConfiguration()->getConfigurationId()) {
                            $paymentMethodPossible = true;
                            break;
                        }
                    }

                    if (! $paymentMethodPossible) {
                        return false;
                    }
                } catch (Exception $e) {
                    Mage::log(
                        (!empty($quote->getWalleeSpaceId()) ? '[Space ' . $quote->getWalleeSpaceId() . '] ' : '' ) .
                        (!empty($quote->getWalleeTransactionId()) ? '[Transaction ' . $quote->getWalleeTransactionId() . '] ' : '' ) .
                        'The payment method ' . $this->getTitle() . ' is not available because of an exception: ' .
                        $e->getMessage(), null, 'wallee.log');
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Initializes the payment.
     *
     * An invoice is created and the transaction updated to match the order.
     *
     * The order state will be set to {@link Mage_Sales_Model_Order::STATE_PENDING_PAYMENT}.
     *
     * @see Mage_Payment_Model_Method_Abstract::initialize()
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();

        /* @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $this->setCheckoutOrder($order);

        $order->setCanSendNewEmailFlag(false);
        $payment->setAmountAuthorized($order->getTotalDue());
        $payment->setBaseAmountAuthorized($order->getBaseTotalDue());

        /* @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->setStore($order->getStore())
            ->load($order->getQuoteId());
        $this->setCheckoutQuote($quote);

        $invoice = $this->createInvoice($quote->getWalleeSpaceId(),
            $quote->getWalleeTransactionId(), $order);
        $this->setCheckoutInvoice($invoice);

        $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        $order->getResource()->addCommitCallback(array(
            $this,
            'onOrderCreation'
        ));
    }

    public function onOrderCreation()
    {
        $order = Mage::getModel('sales/order')->load($this->getCheckoutOrder()
            ->getId());
        $quote = $this->getCheckoutQuote();

        $token = null;
        if (Mage::app()->getStore()->isAdmin()) {
            $tokenInfoId = $quote->getPayment()->getData('wallee_token');
            if ($tokenInfoId) {
                /* @var Wallee_Payment_Model_Entity_TokenInfo $tokenInfo */
                $tokenInfo = Mage::getModel('wallee_payment/entity_tokenInfo')->load($tokenInfoId);
                $token = new \Wallee\Sdk\Model\Token();
                $token->setId($tokenInfo->getTokenId());
            }
        }

        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');

        if ($order->getWalleeSpaceId() != null ||
            $order->getWalleeTransactionId() != null) {
            Mage::throwException(
                $this->getHelper()->__('The wallee payment transaction has already been set on the order.'));
        }

        $this->getCheckoutOrder()->setWalleeSpaceId($quote->getWalleeSpaceId());
        $this->getCheckoutOrder()->setWalleeTransactionId(
            $quote->getWalleeTransactionId());
        $order->setWalleeSpaceId($quote->getWalleeSpaceId());
        $order->setWalleeTransactionId($quote->getWalleeTransactionId());
        $order->save();

        if (! $this->checkTransactionInfo($order)) {
            return;
        }

        $transaction = $transactionService->getTransaction($quote->getWalleeSpaceId(),
            $quote->getWalleeTransactionId());
        $transactionService->updateTransactionInfo($transaction, $order);

        $transaction = $transactionService->confirmTransaction($transaction, $order, $this->getCheckoutInvoice(),
            Mage::app()->getStore()
                ->isAdmin(), $token);
        $transactionService->updateTransactionInfo($transaction, $order);

        if (Mage::app()->getStore()->isAdmin()) {
            // Set the selected token on the order and tell it to apply the charge flow after it is saved.
            $this->getCheckoutOrder()->setWalleeChargeFlow(true);
            $this->getCheckoutOrder()->setWalleeToken($token);
        }
    }

    /**
     * Checks whether the transaction info for the transaction linked to the order is already linked to another order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return boolean
     */
    protected function checkTransactionInfo(Mage_Sales_Model_Order $order)
    {
        /* @var Wallee_Payment_Model_Entity_TransactionInfo $info */
        $info = Mage::getModel('wallee_payment/entity_transactionInfo')->loadByTransaction(
            $order->getWalleeSpaceId(), $order->getWalleeTransactionId());
        if ($info->getId() && $info->getOrderId() != $order->getId()) {
            return false;
        } else {
            return true;
        }
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
        $invoice->register();
        $invoice->setTransactionId($spaceId . '_' . $transactionId);

        /* @var Mage_Core_Model_Resource_Transaction $transactionSave */
        $transactionSave = Mage::getModel('core/resource_transaction');
        $transactionSave->addObject($invoice)->addObject($invoice->getOrder());
        $transactionSave->save();
        return $invoice;
    }

    /**
     * Accepts the payment by marking the delivery indication as suitable.
     *
     * @see Mage_Payment_Model_Method_Abstract::acceptPayment()
     */
    public function acceptPayment(Mage_Payment_Model_Info $payment)
    {
        parent::acceptPayment($payment);
        Mage::getSingleton('wallee_payment/service_deliveryIndication')->markAsSuitable($payment);
        return true;
    }

    /**
     * Denies the payment by marking the delivery indication as not suitable.
     *
     * @see Mage_Payment_Model_Method_Abstract::denyPayment()
     */
    public function denyPayment(Mage_Payment_Model_Info $payment)
    {
        parent::denyPayment($payment);
        Mage::getSingleton('wallee_payment/service_deliveryIndication')->markAsNotSuitable($payment);
        $payment->getOrder()->setWalleePaymentInvoiceAllowManipulation(true);
        return true;
    }

    /**
     * Voids the payment.
     *
     * @see Mage_Payment_Model_Method_Abstract::void()
     */
    public function void(Varien_Object $payment)
    {
        parent::void($payment);
        $this->voidOnline($payment);
        return $this;
    }

    /**
     * Cancels the payment.
     *
     * @see Mage_Payment_Model_Method_Abstract::cancel()
     */
    public function cancel(Varien_Object $payment)
    {
        parent::cancel($payment);

        $order = $payment->getOrder();
        if ($order->getWalleeDerecognized()) {
            return $this;
        }

        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        try {
            $transaction = $transactionService->getTransaction($order->getWalleeSpaceId(),
                $order->getWalleeTransactionId());
            if (! ($transaction instanceof \Wallee\Sdk\Model\Transaction)) {
                Mage::throwException($this->getHelper()->__('The transaction linked to the order could not be loaded.'));
            }
        } catch (Exception $e) {
            Mage::throwException($this->getHelper()->__('The transaction linked to the order could not be loaded.'));
        }

        if ($transaction->getState() == TransactionState::AUTHORIZED) {
            $this->voidOnline($payment);
        } else {
            $this->refundCancelledOrder($payment);
        }
        return $this;
    }

    /**
     * Voids the transaction online.
     *
     * @param Varien_Object $payment
     * @throws Mage_Core_Exception
     */
    protected function voidOnline(Varien_Object $payment)
    {
        /* @var \Wallee\Sdk\Model\TransactionVoid $void */
        $void = Mage::getSingleton('wallee_payment/service_void')->void($payment);
        if ($void->getState() == \Wallee\Sdk\Model\TransactionVoidState::FAILED) {
            Mage::throwException($this->getHelper()->__('The void of the payment failed on the gateway.'));
        }
    }

    /**
     * Refunds the transaction online in case the order has been cancelled.
     *
     * @param Varien_Object $payment
     * @throws Mage_Core_Exception
     */
    protected function refundCancelledOrder(Varien_Object $payment)
    {
        $order = $payment->getOrder();

        $this->checkExistingRefundJob($order);

        /* @var Wallee_Payment_Model_Service_Refund $refundService */
        $refundService = Mage::getSingleton('wallee_payment/service_refund');
        $refund = $refundService->createForPayment($payment);

        $refundJob = $this->createRefundJob($order, $refund);

        try {
            $refund = $refundService->refund($refundJob->getSpaceId(), $refund);
        } catch (\Wallee\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \Wallee\Sdk\Model\ClientError) {
                $refundJob->delete();
                Mage::throwException($e->getResponseObject()->getMessage());
            } else {
                Mage::throwException(
                    $this->getHelper()->__('There has been an error while sending the refund to the gateway.'));
            }
        } catch (Exception $e) {
            Mage::throwException(
                $this->getHelper()->__('There has been an error while sending the refund to the gateway.'));
        }

        if ($refund->getState() == \Wallee\Sdk\Model\RefundState::FAILED) {
            $refundJob->delete();
            Mage::throwException($this->getHelper()->translate($refund->getFailureReason()
                ->getDescription()));
        } elseif ($refund->getState() == \Wallee\Sdk\Model\RefundState::PENDING) {
            Mage::throwException(
                $this->getHelper()->__('The refund was requested successfully, but is still pending on the gateway.'));
        }

        $refundJob->delete();
    }

    /**
     * Captures the payment with the given amount.
     *
     * @see Mage_Payment_Model_Method_Abstract::capture()
     */
    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);

        /* @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = Mage::registry('wallee_payment_capture_invoice');

        if ($invoice->getWalleeCapturePending()) {
            Mage::throwException(
                $this->getHelper()->__(
                    'The capture has already been requested but could not be completed yet. The invoice will be updated, as soon as the capture is done.'));
        }

        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        try {
            $transaction = $transactionService->getTransaction(
                $payment->getOrder()
                    ->getWalleeSpaceId(),
                $payment->getOrder()
                    ->getWalleeTransactionId());
            if (! ($transaction instanceof \Wallee\Sdk\Model\Transaction)) {
                Mage::throwException($this->getHelper()->__('The transaction linked to the order could not be loaded.'));
            }
        } catch (Exception $e) {
            Mage::throwException($this->getHelper()->__('The transaction linked to the order could not be loaded.'));
        }

        if ($transaction->getState() == TransactionState::AUTHORIZED) {
            if ($invoice->getId()) {
                $this->complete($payment, $invoice, $amount);
            } else {
                $invoice->setWalleePaymentNeedsCapture(true);
            }
        } else {
            Mage::throwException($this->getHelper()->__('The invoice cannot be captured manually.'));
        }

        return $this;
    }

    /**
     * Complete the transaction.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param float $amount
     */
    public function complete(Mage_Sales_Model_Order_Payment $payment, Mage_Sales_Model_Order_Invoice $invoice, $amount)
    {
        $this->updateLineItems($payment, $invoice, $amount);

        /* @var \Wallee\Sdk\Model\TransactionCompletion $completion */
        $completion = Mage::getSingleton('wallee_payment/service_transactionCompletion')->complete(
            $payment);
        if ($completion->getState() == \Wallee\Sdk\Model\TransactionCompletionState::FAILED) {
            Mage::throwException($this->getHelper()->__('The capture of the invoice failed on the gateway.'));
        }

        /* @var \Wallee\Sdk\Model\TransactionInvoice $transactionInvoice */
        $transactionInvoice = Mage::getSingleton('wallee_payment/service_transactionInvoice')->getTransactionInvoiceByCompletion(
            $completion->getLinkedSpaceId(), $completion->getId());
        if ($transactionInvoice->getState() != \Wallee\Sdk\Model\TransactionInvoiceState::PAID &&
            $transactionInvoice->getState() != \Wallee\Sdk\Model\TransactionInvoiceState::NOT_APPLICABLE) {
            $invoice->setWalleeCapturePending(true);
        }

        $authTransaction = $payment->getAuthorizationTransaction();
        $authTransaction->close(false);
        $invoice->getOrder()->addRelatedObject($authTransaction);
    }

    /**
     * Updates the transaction's line items.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param number $amount
     */
    protected function updateLineItems(Mage_Sales_Model_Order_Payment $payment, Mage_Sales_Model_Order_Invoice $invoice,
        $amount)
    {
        /* @var Wallee_Payment_Model_Entity_TransactionInfo $transactionInfo */
        $transactionInfo = Mage::getModel('wallee_payment/entity_transactionInfo')->loadByOrder(
            $payment->getOrder());
        if ($transactionInfo->getState() == \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
            /* @var Wallee_Payment_Model_Service_LineItem $lineItemCollection */
            $lineItemCollection = Mage::getSingleton('wallee_payment/service_lineItem');
            $lineItems = $lineItemCollection->collectInvoiceLineItems($invoice, $amount);

            /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
            $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
            $transactionService->updateLineItems($payment->getOrder()
                ->getWalleeSpaceId(), $payment->getOrder()
                ->getWalleeTransactionId(), $lineItems);
        }
    }

    /**
     * Refunds the payment with the given amount.
     *
     * @see Mage_Payment_Model_Method_Abstract::refund()
     */
    public function refund(Varien_Object $payment, $amount)
    {
        parent::refund($payment, $amount);

        /* @var Mage_Sales_Model_Order_Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();

        $this->checkExistingRefundJob($creditmemo->getOrder());

        /* @var Wallee_Payment_Model_Service_Refund $refundService */
        $refundService = Mage::getSingleton('wallee_payment/service_refund');
        $refund = $refundService->create($payment, $creditmemo);

        $refundJob = $this->createRefundJob($creditmemo->getOrder(), $refund);

        try {
            $refund = $refundService->refund($refundJob->getSpaceId(), $refund);
        } catch (\Wallee\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \Wallee\Sdk\Model\ClientError) {
                $refundJob->delete();
                Mage::throwException($e->getResponseObject()->getMessage());
            } else {
                Mage::throwException(
                    $this->getHelper()->__('There has been an error while sending the refund to the gateway.'));
            }
        } catch (Exception $e) {
            Mage::throwException(
                $this->getHelper()->__('There has been an error while sending the refund to the gateway.'));
        }

        if ($refund->getState() == \Wallee\Sdk\Model\RefundState::FAILED) {
            $refundJob->delete();
            Mage::throwException($this->getHelper()->translate($refund->getFailureReason()
                ->getDescription()));
        } elseif ($refund->getState() == \Wallee\Sdk\Model\RefundState::PENDING) {
            Mage::throwException(
                $this->getHelper()->__('The refund was requested successfully, but is still pending on the gateway.'));
        }

        $creditmemo->setWalleeExternalId($refund->getExternalId());
        $refundJob->delete();

        return $this;
    }

    /**
     * Creates a new refund job for the given order and refund.
     *
     * @param Mage_Sales_Model_Order $order
     * @param \Wallee\Sdk\Model\RefundCreate $refund
     * @return Wallee_Payment_Model_Entity_RefundJob
     */
    protected function createRefundJob(Mage_Sales_Model_Order $order,
        \Wallee\Sdk\Model\RefundCreate $refund)
    {
        /* @var Wallee_Payment_Model_Entity_RefundJob $refundJob */
        $refundJob = Mage::getModel('wallee_payment/entity_refundJob');
        $refundJob->setOrderId($order->getId());
        $refundJob->setSpaceId($order->getWalleeSpaceId());
        $refundJob->setExternalId($refund->getExternalId());
        $refundJob->setRefund($refund);
        $refundJob->save();
        return $refundJob;
    }

    /**
     * Checks if there is an existing refund job for the given order.
     *
     * If yes, the refund is sent to the gateway and an exception is thrown.
     *
     * @param Mage_Sales_Model_Order $order
     * @throws Mage_Core_Exception
     */
    protected function checkExistingRefundJob(Mage_Sales_Model_Order $order)
    {
        /* @var Wallee_Payment_Model_Entity_RefundJob $existingRefundJob */
        $existingRefundJob = Mage::getModel('wallee_payment/entity_refundJob');
        $existingRefundJob->loadByOrder($order);
        if ($existingRefundJob->getId() > 0) {
            try {
                /* @var Wallee_Payment_Model_Service_Refund $refundService */
                $refundService = Mage::getSingleton('wallee_payment/service_refund');
                $refundService->refund($existingRefundJob->getSpaceId(), $existingRefundJob->getRefund());
            } catch (Exception $e) {
                Mage::log('Failed to refund because of an exception: ' . $e->getMessage(), null,
                    'wallee.log');
            }

            Mage::throwException(
                $this->getHelper()->__(
                    'As long as there is an open creditmemo for the order, no new creditmemo can be created.'));
        }
    }

    /**
     * Processes the invoice.
     *
     * Ensures that the invoice is not set as paid if the capture is pending.
     *
     * @see Mage_Payment_Model_Method_Abstract::processInvoice()
     */
    public function processInvoice($invoice, $payment)
    {
        parent::processInvoice($invoice, $payment);

        if ($invoice->getWalleeCapturePending()) {
            $invoice->setIsPaid(false);
        }

        return $this;
    }

    /**
     * Restores the quote after the payment has been cancelled.
     *
     * @param Mage_Sales_Model_Order $order
     */
    public function fail(Mage_Sales_Model_Order $order)
    {
        /* @var Mage_Checkout_Model_Session $session */
        $session = Mage::getSingleton('checkout/session');
        /* @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        if ($quote->getId()) {
            $quote->setWalleeTransactionId(null);
            $quote->setIsActive(1)
                ->setReservedOrderId(NULL)
                ->save();
            $session->replaceQuote($quote);
        }

        $session->unsLastRealOrderId();
        $session->replaceQuote($quote);

        /* @var Mage_Core_Model_Session $coreSession */
        $coreSession = Mage::getSingleton('core/session');
        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        try {
            $transaction = $transactionService->getTransaction($order->getWalleeSpaceId(),
                $order->getWalleeTransactionId());
            if ($transaction instanceof \Wallee\Sdk\Model\Transaction &&
                $transaction->getUserFailureMessage() != null) {
                $coreSession->addError($transaction->getUserFailureMessage());
                return;
            }
        } catch (Exception $e) {
            Mage::log(
                'Failed to get the failure message from the transaction because of an exception: ' . $e->getMessage(),
                null, 'wallee.log');
        }

        $coreSession->addError(
            $this->getHelper()
                ->__('The payment process could not have been finished successfully.'));
    }

    /**
     * Redirects the customer to the redirection controller action.
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('wallee/transaction/redirect', array(
            '_secure' => true
        ));
    }

    /**
     * Returns the data helper.
     *
     * @return Wallee_Payment_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('wallee_payment');
    }
}