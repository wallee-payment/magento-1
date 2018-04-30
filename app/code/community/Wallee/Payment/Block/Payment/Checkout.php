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
 * This block extends the checkout to be able to process wallee payments.
 */
class Wallee_Payment_Block_Payment_Checkout extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('wallee/payment/checkout.phtml');
    }

    /**
     * Returns the URL to wallee's JavaScript library that is necessary to display the payment form.
     *
     * @return string
     */
    public function getJavaScriptUrl()
    {
        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        /* @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        try {
            return $transactionService->getJavaScriptUrl($checkoutSession->getQuote());
        } catch (Exception $e) {
            return false;
        }
    }
}