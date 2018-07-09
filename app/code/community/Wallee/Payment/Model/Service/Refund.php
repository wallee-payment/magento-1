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
 * This service provides functions to deal with wallee refunds.
 */
class Wallee_Payment_Model_Service_Refund extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * The refund API service.
     *
     * @var \Wallee\Sdk\Service\RefundService
     */
    private $refundService;

    /**
     * Returns the refund by the given external id.
     *
     * @param int $spaceId
     * @param string $externalId
     * @return \Wallee\Sdk\Model\Refund
     */
    public function getRefundByExternalId($spaceId, $externalId)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $query->setFilter($this->createEntityFilter('externalId', $externalId));
        $query->setNumberOfEntities(1);
        $result = $this->getRefundService()->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            Mage::throwException('The refund could not be found.');
        }
    }

    /**
     * Creates a refund request model for the given creditmemo.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return \Wallee\Sdk\Model\RefundCreate
     */
    public function create(Mage_Sales_Model_Order_Payment $payment, Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        /* @var Wallee_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('wallee_payment/service_transaction');
        $transaction = new \Wallee\Sdk\Model\Transaction();
        $transaction->setId(
            $payment->getOrder()
            ->getWalleeTransactionId()
        );

        $reductions = $this->getProductReductions($creditmemo);
        $shippingReduction = $this->getShippingReduction($creditmemo);
        if ($shippingReduction != null) {
            $reductions[] = $shippingReduction;
        }

        $reductions = $this->fixReductions($creditmemo, $transaction, $reductions);

        $refund = new \Wallee\Sdk\Model\RefundCreate();
        $refund->setExternalId(uniqid($creditmemo->getOrderId() . '-'));
        $refund->setReductions($reductions);
        $refund->setTransaction($transaction);
        $refund->setType(\Wallee\Sdk\Model\RefundType::MERCHANT_INITIATED_ONLINE);
        return $refund;
    }

    /**
     * Returns the fixed line item reductions for the creditmemo.
     *
     * If the amount of the given reductions does not match the creditmemo's grand total, the amount to refund is distributed equally to the line items.
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @param \Wallee\Sdk\Model\LineItemReductionCreate[] $reductions
     * @return \Wallee\Sdk\Model\LineItemReductionCreate[]
     */
    protected function fixReductions(Mage_Sales_Model_Order_Creditmemo $creditmemo, \Wallee\Sdk\Model\Transaction $transaction, array $reductions)
    {
        $baseLineItems = $this->getBaseLineItems($creditmemo->getOrder()->getWalleeSpaceId(), $transaction);

        /* @var Wallee_Payment_Helper_LineItem $lineItemHelper */
        $lineItemHelper = Mage::helper('wallee_payment/lineItem');
        $reductionAmount = $lineItemHelper->getReductionAmount($baseLineItems, $reductions, $creditmemo->getOrderCurrencyCode());

        if ($reductionAmount != $creditmemo->getGrandTotal()) {
            $fixedReductions = array();
            $baseAmount = $lineItemHelper->getTotalAmountIncludingTax($baseLineItems);
            $rate = $creditmemo->getGrandTotal() / $baseAmount;
            foreach ($baseLineItems as $lineItem) {
                if ($lineItem->getQuantity() > 0) {
                    /* @var Mage_Sales_Model_Order_Creditmemo_Item $item */
                    $reduction = new \Wallee\Sdk\Model\LineItemReductionCreate();
                    $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                    $reduction->setQuantityReduction(0);
                    $reduction->setUnitPriceReduction($this->roundAmount($lineItem->getAmountIncludingTax() * $rate / $lineItem->getQuantity(), $creditmemo->getOrderCurrencyCode()));
                    $fixedReductions[] = $reduction;
                }
            }

            return $fixedReductions;
        } else {
            return $reductions;
        }
    }

    /**
     * Returns the line item reductions for the creditmemo's items.
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return \Wallee\Sdk\Model\LineItemReductionCreate[]
     */
    protected function getProductReductions(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $reductions = array();
        foreach ($creditmemo->getAllItems() as $item) {
            /* @var Mage_Sales_Model_Order_Creditmemo_Item $item */
            $orderItem = $item->getOrderItem();
            if ($orderItem->getParentItemId() != null && $orderItem->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                continue;
            }

            if ($orderItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $orderItem->getParentItemId() == null) {
                continue;
            }

            $reduction = new \Wallee\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId(
                $orderItem->getQuoteItemId()
            );
            $reduction->setQuantityReduction($item->getQty());
            $reduction->setUnitPriceReduction(0);
            $reductions[] = $reduction;
            
            if ($orderItem->getDiscountAmount() != 0) {
                $reduction = new \Wallee\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId(
                    $orderItem->getQuoteItemId() . '-discount'
                );
                $reduction->setQuantityReduction($item->getQty());
                $reduction->setUnitPriceReduction(0);
                $reductions[] = $reduction;
            }
        }

        return $reductions;
    }

    /**
     * Returns the line item reduction for the shipping.
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return \Wallee\Sdk\Model\LineItemReductionCreate
     */
    protected function getShippingReduction(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        if ($creditmemo->getShippingAmount() > 0) {
            $reduction = new \Wallee\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId('shipping');
            if ($creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount() == $creditmemo->getOrder()->getShippingInclTax()) {
                $reduction->setQuantityReduction(1);
                $reduction->setUnitPriceReduction(0);
            } else {
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction($this->roundAmount($creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount(), $creditmemo->getOrderCurrencyCode()));
            }

            return $reduction;
        } else {
            return null;
        }
    }

    /**
     * Sends the refund to the gateway.
     *
     * @param int $spaceId
     * @param \Wallee\Sdk\Model\RefundCreate $refund
     * @return \Wallee\Sdk\Model\Refund
     */
    public function refund($spaceId, \Wallee\Sdk\Model\RefundCreate $refund)
    {
        return $this->getRefundService()->refund($spaceId, $refund);
    }

    /**
     * Registers a refund notification, creates a creditmemo.
     *
     * @param \Wallee\Sdk\Model\Refund $refund
     * @param Mage_Sales_Model_Order $order
     */
    public function registerRefundNotification(\Wallee\Sdk\Model\Refund $refund, Mage_Sales_Model_Order $order)
    {
        /* @var Mage_Sales_Model_Service_Order $serviceModel */
        $serviceModel = Mage::getModel('sales/service_order', $order);
        $creditmemo = $serviceModel->prepareCreditmemo($this->getCreditmemoData($refund, $order));

        $creditmemo->setPaymentRefundDisallowed(true);
        $creditmemo->setAutomaticallyCreated(true);
        $creditmemo->register();
        $creditmemo->addComment(Mage::helper('sales')->__('Credit memo has been created automatically'));
        $creditmemo->setWalleeExternalId($refund->getExternalId());
        $creditmemo->save();

        $this->updateTotals(
            $order->getPayment(), array(
            'amount_refunded' => $creditmemo->getGrandTotal(),
            'base_amount_refunded_online' => $refund->getAmount()
            )
        );

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, Mage::helper('sales')->__('Registered notification about refunded amount of %s.', $this->formatPrice($order, $refund->getAmount())));
        $order->save();
    }

    /**
     * Returns the data needed to create the creditmemo.
     *
     * @param \Wallee\Sdk\Model\Refund $refund
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    protected function getCreditmemoData(\Wallee\Sdk\Model\Refund $refund, Mage_Sales_Model_Order $order)
    {
        $orderItemMap = array();
        foreach ($order->getAllItems() as $orderItem) {
            /* @var Mage_Sales_Model_Order_Item $orderItem */
            $orderItemMap[$orderItem->getQuoteItemId()] = $orderItem;
        }

        $lineItems = array();
        foreach ($refund->getTransaction()->getLineItems() as $lineItem) {
            $lineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $baseLineItems = array();
        foreach ($this->getBaseLineItems($order->getWalleeSpaceId(), $refund->getTransaction(), $refund) as $lineItem) {
            $baseLineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $refundQuantities = array();
        foreach ($order->getAllItems() as $orderItem) {
            $refundQuantities[$orderItem->getQuoteItemId()] = 0;
        }

        $creditmemoAmount = 0;
        $shippingAmount = 0;
        foreach ($refund->getReductions() as $reduction) {
            $lineItem = $lineItems[$reduction->getLineItemUniqueId()];
            switch ($lineItem->getType()) {
                case \Wallee\Sdk\Model\LineItemType::PRODUCT:
                    if ($reduction->getQuantityReduction() > 0) {
                        $refundQuantities[$orderItemMap[$reduction->getLineItemUniqueId()]->getId()] = $reduction->getQuantityReduction();
                        $creditmemoAmount += $reduction->getQuantityReduction() * $orderItemMap[$reduction->getLineItemUniqueId()]->getPriceInclTax();
                    }
                    break;
                case \Wallee\Sdk\Model\LineItemType::FEE:
                case \Wallee\Sdk\Model\LineItemType::DISCOUNT:
                    break;
                case \Wallee\Sdk\Model\LineItemType::SHIPPING:
                    if ($reduction->getQuantityReduction() > 0) {
                        $shippingAmount = $baseLineItems[$reduction->getLineItemUniqueId()]->getAmountIncludingTax();
                    } elseif ($reduction->getUnitPriceReduction() > 0) {
                        $shippingAmount = $reduction->getUnitPriceReduction();
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($shippingAmount <= $order->getShippingInclTax() - $order->getShippingRefunded()) {
                        $creditmemoAmount += $shippingAmount;
                    } else {
                        $shippingAmount = 0;
                    }
                    break;
            }
        }

        $positiveAdjustment = 0;
        $negativeAdjustment = 0;
        if ($creditmemoAmount > $refund->getAmount()) {
            $negativeAdjustment = $creditmemoAmount - $refund->getAmount();
        } elseif ($creditmemoAmount < $refund->getAmount()) {
            $positiveAdjustment = $refund->getAmount() - $creditmemoAmount;
        }

        return array(
            'qtys' => $refundQuantities,
            'shipping_amount' => $shippingAmount,
            'adjustment_positive' => $positiveAdjustment,
            'adjustment_negative' => $negativeAdjustment
        );
    }

    /**
     * Returns the formatted price.
     *
     * @param Mage_Sales_Model_Order $order
     * @param number $amount
     * @return string
     */
    protected function formatPrice(Mage_Sales_Model_Order $order, $amount)
    {
        return $order->getBaseCurrency()->formatTxt($amount, array());
    }

    /**
     * Updates the total of the order payment.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param array $data
     */
    protected function updateTotals(Mage_Sales_Model_Order_Payment $payment, array $data)
    {
        foreach ($data as $key => $amount) {
            if (null !== $amount) {
                $was = $payment->getDataUsingMethod($key);
                $payment->setDataUsingMethod($key, $was + $amount);
            }
        }
    }

    /**
     * Returns the line items that are to be used to calculate the refund.
     *
     * This returns the line items of the latest refund if there is one or else of the transaction invoice.
     *
     * @param int $spaceId
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @param \Wallee\Sdk\Model\Refund $refund
     * @return \Wallee\Sdk\Model\LineItem[]
     */
    protected function getBaseLineItems($spaceId, \Wallee\Sdk\Model\Transaction $transaction, \Wallee\Sdk\Model\Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transaction, $refund);
        if ($lastSuccessfulRefund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        } else {
            return $this->getTransactionInvoice($spaceId, $transaction)->getLineItems();
        }
    }

    /**
     * Returns the transaction invoice for the given transaction.
     *
     * @param int $spaceId
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @throws Exception
     * @return \Wallee\Sdk\Model\TransactionInvoice
     */
    protected function getTransactionInvoice($spaceId, \Wallee\Sdk\Model\Transaction $transaction)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();

        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
            $this->createEntityFilter('state', \Wallee\Sdk\Model\TransactionInvoiceState::CANCELED, \Wallee\Sdk\Model\CriteriaOperator::NOT_EQUALS),
            $this->createEntityFilter('completion.lineItemVersion.transaction.id', $transaction->getId())
            )
        );
        $query->setFilter($filter);

        $query->setNumberOfEntities(1);

        $invoiceService = new \Wallee\Sdk\Service\TransactionInvoiceService($this->getHelper()->getApiClient());
        $result = $invoiceService->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            throw new Exception('The transaction invoice could not be found.');
        }
    }

    /**
     * Returns the last successful refund of the given transaction, excluding the given refund.
     *
     * @param int $spaceId
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @param \Wallee\Sdk\Model\Refund $refund
     * @return \Wallee\Sdk\Model\Refund
     */
    protected function getLastSuccessfulRefund($spaceId, \Wallee\Sdk\Model\Transaction $transaction, \Wallee\Sdk\Model\Refund $refund = null)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();

        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
        $filters = array(
            $this->createEntityFilter('state', \Wallee\Sdk\Model\RefundState::SUCCESSFUL),
            $this->createEntityFilter('transaction.id', $transaction->getId())
        );
        if ($refund != null) {
            $filters[] = $this->createEntityFilter('id', $refund->getId(), \Wallee\Sdk\Model\CriteriaOperator::NOT_EQUALS);
        }

        $filter->setChildren($filters);
        $query->setFilter($filter);

        $query->setOrderBys(
            array(
            $this->createEntityOrderBy('createdOn', \Wallee\Sdk\Model\EntityQueryOrderByType::DESC)
            )
        );

        $query->setNumberOfEntities(1);

        $result = $this->getRefundService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns the refund API service.
     *
     * @return \Wallee\Sdk\Service\RefundService
     */
    protected function getRefundService()
    {
        if ($this->refundService == null) {
            $this->refundService = new \Wallee\Sdk\Service\RefundService($this->getHelper()->getApiClient());
        }

        return $this->refundService;
    }
}