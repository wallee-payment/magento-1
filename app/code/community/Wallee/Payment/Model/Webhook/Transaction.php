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
 * Webhook processor to handle transaction state transitions.
 */
class Wallee_Payment_Model_Webhook_Transaction extends Wallee_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see Wallee_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return \Wallee\Sdk\Model\Transaction
     */
    protected function loadEntity(Wallee_Payment_Model_Webhook_Request $request)
    {
        $transactionService = new \Wallee\Sdk\Service\TransactionService(Mage::helper('wallee_payment')->getApiClient());
        return $transactionService->transactionReadGet($request->getSpaceId(), $request->getEntityId());
    }

    protected function getLockType()
    {
        return Wallee_Payment_Model_Service_Lock::TYPE_TRANSACTION;
    }

    protected function getOrderIncrementId($transaction)
    {
        /* @var \Wallee\Sdk\Model\Transaction $transaction */
        return $transaction->getMerchantReference();
    }

    protected function getTransactionId($transaction)
    {
        /* @var \Wallee\Sdk\Model\Transaction $transaction */
        return $transaction->getId();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $transaction)
    {
        /* @var \Wallee\Sdk\Model\Transaction $transaction */
        /* @var Wallee_Payment_Model_Entity_TransactionInfo $transactionInfo */
        $transactionInfo = Mage::getModel('wallee_payment/entity_transactionInfo')->loadByOrder($order);
        if ($transaction->getState() != $transactionInfo->getState()) {
            switch ($transaction->getState()) {
                case \Wallee\Sdk\Model\Transaction::STATE_AUTHORIZED:
                    $this->authorize($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\Transaction::STATE_DECLINE:
                    $this->decline($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\Transaction::STATE_FAILED:
                    $this->failed($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\Transaction::STATE_FULFILL:
                    if (! $order->getWalleeAuthorized()) {
                        $this->authorize($transaction, $order);
                    }

                    $this->fulfill($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\Transaction::STATE_VOIDED:
                    $this->voided($transaction, $order);
                    break;
                case \Wallee\Sdk\Model\Transaction::STATE_COMPLETED:
                default:
                    // Nothing to do.
                    break;
            }
        }

        /* @var Wallee_Payment_Model_Service_Transaction $transactionStoreService */
        $transactionStoreService = Mage::getSingleton('wallee_payment/service_transaction');
        $transactionStoreService->updateTransactionInfo($transaction, $order);
    }

    private function authorize(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        $order->getPayment()
            ->setTransactionId($transaction->getLinkedSpaceId() . '_' . $transaction->getId())
            ->setIsTransactionClosed(false);
        $order->getPayment()->registerAuthorizationNotification($transaction->getAuthorizationAmount());
        $this->sendOrderEmail($order);
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing_wallee', Mage::helper('wallee_payment')->__('The order should not be fulfilled yet, as the payment is not guaranteed.'));
        $order->setWalleeAuthorized(true);
        $order->save();
        $this->updateShopCustomer($transaction, $order);
    }

    private function decline(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        if ($order->getState() != Mage_Sales_Model_Order::STATE_CANCELED) {
            $order->setWalleePaymentInvoiceAllowManipulation(true);
            $order->getPayment()->setNotificationResult(true);
            $order->getPayment()->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_DENY, false);
        }

        $order->save();
    }

    private function failed(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        $invoice = $this->getInvoiceForTransaction($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        if ($invoice) {
            $order->setWalleePaymentInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
        }

        $order->registerCancellation(null, false)->save();
    }

    private function fulfill(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
            $order->getPayment()->setNotificationResult(true);
            $order->getPayment()->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_ACCEPT, false);
        } elseif ($order->getStatus() == 'processing_wallee') {
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, Mage::helper('wallee_payment')->__('The order can be fulfilled now.'));
        }

        $order->save();
    }

    private function voided(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        $order->getPayment()->registerVoidNotification();
        $invoice = $this->getInvoiceForTransaction($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        if ($invoice) {
            $order->setWalleePaymentInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
        }

        $order->save();
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

    private function updateShopCustomer(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        if ($order->getCustomerIsGuest()) {
            return;
        }

        /* @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());

        $billingAddress = $customer->getAddressById(
            $order->getBillingAddress()
            ->getCustomerAddressId()
        );

        if ($customer->getDob() == null && $transaction->getBillingAddress()->getDateOfBirth() != null) {
            $customer->setDob(
                $transaction->getBillingAddress()
                ->getDateOfBirth()
            );
        }

        if ($transaction->getBillingAddress()->getSalutation() != null) {
            if ($customer->getPrefix() == null) {
                $customer->setPrefix(
                    $transaction->getBillingAddress()
                    ->getSalutation()
                );
            }

            if ($billingAddress->getPrefix() == null) {
                $billingAddress->setPrefix(
                    $transaction->getBillingAddress()
                    ->getSalutation()
                );
            }
        }

        if ($customer->getGender() == null && $transaction->getBillingAddress()->getGender() != null) {
            if ($transaction->getBillingAddress()->getGender() == \Wallee\Sdk\Model\Address::GENDER_MALE) {
                $customer->setGender(1);
            } elseif ($transaction->getBillingAddress()->getGender() == \Wallee\Sdk\Model\Address::GENDER_FEMALE) {
                $customer->setGender(2);
            }
        }

        if ($transaction->getBillingAddress()->getSalesTaxNumber() != null) {
            if ($customer->getTaxvat() == null) {
                $customer->setTaxvat(
                    $transaction->getBillingAddress()
                    ->getSalesTaxNumber()
                );
            }

            if ($billingAddress->getVatId() == null) {
                $billingAddress->setVatId(
                    $transaction->getBillingAddress()
                    ->getSalesTaxNumber()
                );
            }
        }

        if ($billingAddress->getCompany() == null && $transaction->getBillingAddress()->getOrganizationName() != null) {
            $billingAddress->setCompany(
                $transaction->getBillingAddress()
                ->getOrganizationName()
            );
        }

        $billingAddress->save();
        $customer->save();
    }
}