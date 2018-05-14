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
 * This block displays a note that there is a pending refund for the order.
 */
class Wallee_Payment_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Abstract
{

    /**
     * Returns whether there is a pending refund for the order.
     *
     * @return boolean
     */
    public function hasPendingRefund()
    {
        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::registry('sales_order');

        /* @var Wallee_Payment_Model_Entity_RefundJob $existingRefundJob */
        $existingRefundJob = Mage::getModel('wallee_payment/entity_refundJob');
        $existingRefundJob->loadByOrder($order);
        return $existingRefundJob->getId() > 0;
    }

    /**
     * Returns the URL to send the refund request to the gateway.
     *
     * @return string
     */
    public function getRefundUrl()
    {
        /* @var Mage_Sales_Model_Order $order */
        $order = Mage::registry('sales_order');
        return $this->getUrl(
            'adminhtml/wallee_transaction/refund', array(
            'order_id' => $order->getId()
            )
        );
    }
}