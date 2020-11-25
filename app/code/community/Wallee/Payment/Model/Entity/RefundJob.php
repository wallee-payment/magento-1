<?php

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
 * This entity holds data about a pending refund to achieve reliability.
 *
 * @method int getOrderId()
 * @method Wallee_Payment_Model_Entity_RefundJob setOrderId(int orderId)
 * @method int getSpaceId()
 * @method Wallee_Payment_Model_Entity_RefundJob setSpaceId(int spaceId)
 * @method int getExternalId()
 * @method Wallee_Payment_Model_Entity_RefundJob setExternalId(int externalId)
 * @method string getCreatedAt()
 * @method \Wallee\Sdk\Model\Refund getRefund()
 * @method Wallee_Payment_Model_Entity_RefundJob setRefund(string refund)
 * @method bool getLock()
 * @method Wallee_Payment_Model_Entity_RefundJob setLock(bool lock)
 */
class Wallee_Payment_Model_Entity_RefundJob extends Mage_Core_Model_Abstract
{

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_refund_job';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    protected $_eventObject = 'refundJob';

    /**
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('wallee_payment/refundJob');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        if ($this->isObjectNew()) {
            $this->setCreatedAt(Mage::getSingleton('core/date')->date());
        }
    }

    /**
     * Loading refund job by order.
     *
     * @param int|Mage_Sales_Model_Order $order
     * @return Wallee_Payment_Model_Entity_RefundJob
     */
    public function loadByOrder($order)
    {
        if ($order instanceof Mage_Sales_Model_Order) {
            $orderId = $order->getId();
        } else {
            $orderId = (int) $order;
        }

        return $this->load($orderId, 'order_id');
    }

    /**
     * Loading refund job by external id.
     *
     * @param int $externalId
     * @return Wallee_Payment_Model_Entity_RefundJob
     */
    public function loadByExternalId($externalId)
    {
        return $this->load($externalId, 'external_id');
    }

    /**
     * Returns the order the refund was requested for.
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (! $this->_order instanceof Mage_Sales_Model_Order) {
            $this->_order = Mage::getModel('sales/order')->load($this->getOrderId());
        }

        return $this->_order;
    }
}