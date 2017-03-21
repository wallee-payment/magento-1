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
 * This controller redirects the customer to the relevant page in the shop after the payment process and allows customers to download transaction documents.
 */
class Wallee_Payment_TransactionController extends Mage_Core_Controller_Front_Action
{

    /**
     * Redirects the customer to the order confirmation page after the payment process was successful.
     *
     * The quote is inactivated.
     */
    public function successAction()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($orderId);

        /* @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession->getQuote()
            ->setIsActive(false)
            ->save();
        $checkoutSession->setLastSuccessQuoteId($order->getQuoteId());

        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        $transactionService->waitForTransactionState(
            $order, array(
            \Wallee\Sdk\Model\Transaction::STATE_CONFIRMED,
            \Wallee\Sdk\Model\Transaction::STATE_PENDING,
            \Wallee\Sdk\Model\Transaction::STATE_PROCESSING
            ), 3
        );

        $this->_redirectUrl($this->getSuccessUrl($order));
    }

    private function getSuccessUrl(Mage_Sales_Model_Order $order)
    {
        $result = new StdClass;
        $result->url = Mage::getUrl('checkout/onepage/success', array('_secure' => true));
        Mage::dispatchEvent('wallee_payment_success_url', array(
            'result' => $result,
            'order' => $order
        ));
        return $result->url;
    }

    /**
     * Redirects the customer to the cart after payment process was cancelled.
     */
    public function failureAction()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($orderId);
        /* @var Wallee_Payment_Model_Payment_Method_Abstract $methodInstance */
        $methodInstance = $order->getPayment()->getMethodInstance();
        $methodInstance->fail($order);

        $this->_redirectUrl($this->getFailureUrl($order));
    }

    private function getFailureUrl(Mage_Sales_Model_Order $order)
    {
        $result = new StdClass;
        $result->url = Mage::getUrl('checkout/cart');
        Mage::dispatchEvent('wallee_payment_failure_url', array(
            'result' => $result,
            'order' => $order
        ));
        return $result->url;
    }

    /**
     * Downloads the transaction's invoice PDF document.
     */
    public function downloadInvoiceAction()
    {
        $transactionInfo = $this->loadTransactionInfo();

        if (! Mage::getStoreConfigFlag('wallee_payment/document/customer_download_invoice', $transactionInfo->getOrder()->getStore())) {
            $this->_redirect('sales/order/view/order_id/' . $transactionInfo->getOrderId());
            return false;
        }

        $service = new \Wallee\Sdk\Service\TransactionService(Mage::helper('wallee_payment')->getApiClient());
        $document = $service->transactionGetInvoiceDocumentGet($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
        $this->download($document);
    }

    /**
     * Downloads the transaction's packing slip PDF document.
     */
    public function downloadPackingSlipAction()
    {
        $transactionInfo = $this->loadTransactionInfo();

        if (! Mage::getStoreConfigFlag('wallee_payment/document/customer_download_packing_slip', $transactionInfo->getOrder()->getStore())) {
            $this->_redirect('sales/order/view/order_id/' . $transactionInfo->getOrderId());
            return false;
        }

        $service = new \Wallee\Sdk\Service\TransactionService(Mage::helper('wallee_payment')->getApiClient());
        $document = $service->transactionGetPackingSlipGet($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
        $this->download($document);
    }

    /**
     * Sends the data received by calling the given path to the browser.
     *
     * @param string $path
     */
    private function download(\Wallee\Sdk\Model\RenderedDocument $document)
    {
        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', 'application/pdf', true)
            ->setHeader('Content-Disposition', 'attachment; filename=' . $document->getTitle() . '.pdf')
            ->setHeader('Content-Description', $document->getTitle());
        $this->getResponse()->setBody(base64_decode($document->getData()));

        $this->getResponse()->sendHeaders();
        session_write_close();
        $this->getResponse()->outputBody();
    }

    /**
     * Load the transaction info.
     *
     * @return Wallee_Payment_Model_Entity_TransactionInfo
     */
    private function loadTransactionInfo()
    {
        $order = $this->loadOrder();
        if (! $order) {
            return false;
        }

        /* @var Wallee_Payment_Model_Entity_TransactionInfo $transactionInfo */
        $transactionInfo = Mage::getModel('wallee_payment/entity_transactionInfo')->loadByOrder($order);
        if ($transactionInfo->getId()) {
            return $transactionInfo;
        } else {
            $this->_forward('noRoute');
            return false;
        }
    }

    /**
     * Try to load valid order by order_id and register it
     *
     * @param int $orderId
     * @return Mage_Sales_Model_Order
     */
    private function loadOrder()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        if (! $orderId) {
            $this->_forward('noRoute');
            return false;
        }

        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($orderId);
        if ($this->canViewOrder($order)) {
            return $order;
        } else {
            $this->_redirect('sales/order/history');
            return false;
        }
    }

    /**
     * Check order view availability
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    private function canViewOrder(Mage_Sales_Model_Order $order)
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        $availableStates = Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates();
        if ($order->getId() && $order->getCustomerId() && ($order->getCustomerId() == $customerId) && in_array($order->getState(), $availableStates, $strict = true)) {
            return true;
        }

        return false;
    }
}