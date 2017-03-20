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
 * This block displays a note that the invoice is in a pending capture state.
 */
class Wallee_Payment_Block_Adminhtml_Sales_Order_Invoice_View extends Mage_Adminhtml_Block_Abstract
{

    /**
     * Returns whether the invoice is in a pending capture state.
     *
     * @return boolean
     */
    public function isInvoicePending()
    {
        $invoice = Mage::registry('current_invoice');
        return $invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_PAID && $invoice->getWalleeCapturePending();
    }
}