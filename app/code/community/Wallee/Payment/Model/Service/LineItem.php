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
 * This service provides functions to convert Magento quote and order items into Wallee API line items.
 */
class Wallee_Payment_Model_Service_LineItem extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * Returns the line items for the given invoice, with reduced amounts to match the expected sum.
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param float $amount
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function collectInvoiceLineItems(Mage_Sales_Model_Order_Invoice $invoice, $amount)
    {
        $lineItems = array();

        foreach ($invoice->getAllItems() as $item) {
            /* @var Mage_Sales_Model_Order_Invoice_Item $item */
            $orderItem = $item->getOrderItem();
            if ($orderItem->getParentItemId() != null && $orderItem->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                continue;
            }

            if ($orderItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $orderItem->getParentItemId() == null) {
                continue;
            }

            $lineItems[] = $this->getProductLineItem($item, $invoice->getOrderCurrencyCode());

            $discountItem = $this->getDiscountLineItem($item, $invoice->getOrderCurrencyCode());
            if ($discountItem) {
                $lineItems[] = $discountItem;
            }
        }

        $shippingItem = $this->getInvoiceShippingLineItem($invoice);
        if ($shippingItem) {
            $lineItems[] = $shippingItem;
        }

        $surchargeItem = $this->getFoomanSurchargeLineItem($invoice->getOrder());
        if ($surchargeItem) {
            $lineItems[] = $surchargeItem;
        }

        $mx2gGiftCard = $this->getMX2GiftCardLineItem($invoice->getOrder());
        if ($mx2gGiftCard) {
            $lineItems[] = $mx2gGiftCard;
        }

        return $this->getLineItemHelper()->getItemsByReductionAmount($lineItems, $amount);
    }

    /**
     * Returns the line item for the invoice's shipping.
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    private function getInvoiceShippingLineItem(Mage_Sales_Model_Order_Invoice $invoice)
    {
        if ($invoice->getShippingAmount() > 0) {
            $lineItem = new \Wallee\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax($this->roundAmount($invoice->getShippingInclTax(), $invoice->getOrderCurrencyCode()));
            $lineItem->setName(
                $invoice->getOrder()
                ->getShippingDescription()
            );
            $lineItem->setQuantity(1);
            $lineItem->setSku('shipping');
            $tax = $this->getShippingTax($invoice->getOrder());
            if ($tax->getRate() > 0) {
                $lineItem->setTaxes(
                    array(
                    $tax
                    )
                );
            }

            $lineItem->setType(\Wallee\Sdk\Model\LineItem::TYPE_SHIPPING);
            $lineItem->setUniqueId('shipping');
            return $this->cleanLineItem($lineItem);
        }
    }

    /**
     * Returns the line items for the given order or quote.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function collectLineItems($entity)
    {
        $lineItems = $this->getProductLineItems($entity->getItemsCollection(), $this->getCurrencyCode($entity));

        $shippingItem = $this->getShippingLineItem($entity);
        if ($shippingItem) {
            $lineItems[] = $shippingItem;
        }

        $surchargeItem = $this->getFoomanSurchargeLineItem($entity);
        if ($surchargeItem) {
            $lineItems[] = $surchargeItem;
        }

        $mx2gGiftCard = $this->getMX2GiftCardLineItem($entity);
        if ($mx2gGiftCard) {
            $lineItems[] = $mx2gGiftCard;
        }

        return $this->getLineItemHelper()->cleanupLineItems($lineItems, $entity->getGrandTotal(), $this->getCurrencyCode($entity));
    }

    /**
     * Returns the line items for the given products.
     *
     * @param Mage_Sales_Model_Order_Item[]|Mage_Sales_Model_Quote_Item[] $items
     * @param string $currency
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    private function getProductLineItems($items, $currency)
    {
        $lineItems = array();

        foreach ($items as $item) {
            /* @var Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item $item */
            if ($item->getParentItemId() != null && $item->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                continue;
            }

            if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $item->getParentItemId() == null) {
                /* @var Mage_Catalog_Model_Product $product */
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                if ($product->getPriceType() != Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED) {
                    continue;
                }
            }

            $lineItems[] = $this->getProductLineItem($item, $currency);

            $discountItem = $this->getDiscountLineItem($item, $currency);
            if ($discountItem) {
                $lineItems[] = $discountItem;
            }
        }

        return $lineItems;
    }

    /**
     * Returns the line item for the given product.
     *
     * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item|Mage_Sales_Model_Order_Invoice_Item $productItem
     * @param string $currency
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    private function getProductLineItem($productItem, $currency)
    {
        $lineItem = new \Wallee\Sdk\Model\LineItemCreate();
        $lineItem->setAmountIncludingTax($this->roundAmount($productItem->getRowTotalInclTax(), $currency));
        $lineItem->setName($productItem->getName());
        $lineItem->setQuantity($productItem->getQty() ? $productItem->getQty() : $productItem->getQtyOrdered());
        $lineItem->setShippingRequired(! $productItem->getIsVirtual());
        $lineItem->setSku($productItem->getSku());
        if ($productItem->getTaxPercent() > 0) {
            $lineItem->setTaxes(
                array(
                $this->getTax($productItem)
                )
            );
        }

        $lineItem->setType(\Wallee\Sdk\Model\LineItem::TYPE_PRODUCT);
        $uniqueId = $productItem->getId();
        if ($productItem instanceof Mage_Sales_Model_Order_Item) {
            $uniqueId = $productItem->getQuoteItemId();
        } elseif ($productItem instanceof Mage_Sales_Model_Order_Invoice_Item) {
            $uniqueId = $productItem->getOrderItem()->getQuoteItemId();
        }

        $lineItem->setUniqueId($uniqueId);
        return $this->cleanLineItem($lineItem);
    }

    /**
     * Returns the line item for the discounts of the given product.
     *
     * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item|Mage_Sales_Model_Order_Invoice_Item $productItem
     * @param string $currency
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    private function getDiscountLineItem($productItem, $currency)
    {
        /* @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');
        if ($productItem->getDiscountAmount() != 0) {
            if ($taxHelper->priceIncludesTax() || ! $taxHelper->applyTaxAfterDiscount()) {
                $amountIncludingTax = -1 * $productItem->getDiscountAmount();
            } else {
                $amountIncludingTax = -1 * $productItem->getDiscountAmount() * ($productItem->getTaxPercent() / 100 + 1);
            }

            $lineItem = new \Wallee\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax($this->roundAmount($amountIncludingTax, $currency));
            $lineItem->setName(
                $this->getHelper()
                ->__('Discount')
            );
            $lineItem->setQuantity($productItem->getQty() ? $productItem->getQty() : $productItem->getQtyOrdered());
            $lineItem->setSku($productItem->getSku() . '-discount');
            if ($taxHelper->applyTaxAfterDiscount() && $productItem->getTaxPercent() > 0) {
                $lineItem->setTaxes(
                    array(
                    $this->getTax($productItem)
                    )
                );
            }

            $lineItem->setType(\Wallee\Sdk\Model\LineItem::TYPE_DISCOUNT);
            $uniqueId = $productItem->getId();
            if ($productItem instanceof Mage_Sales_Model_Order_Item) {
                $uniqueId = $productItem->getQuoteItemId();
            } elseif ($productItem instanceof Mage_Sales_Model_Order_Invoice_Item) {
                $uniqueId = $productItem->getOrderItem()->getQuoteItemId();
            }

            $lineItem->setUniqueId($uniqueId . '-discount');
            return $this->cleanLineItem($lineItem);
        }
    }

    /**
     * Returns the line item for the shipping.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    private function getShippingLineItem($entity)
    {
        $shippingInfo = $entity;
        if ($entity instanceof Mage_Sales_Model_Quote) {
            $shippingInfo = $entity->getShippingAddress();
        }

        if ($shippingInfo->getShippingAmount() > 0) {
            $lineItem = new \Wallee\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax($this->roundAmount($shippingInfo->getShippingInclTax(), $this->getCurrencyCode($entity)));
            $lineItem->setName($shippingInfo->getShippingDescription());
            $lineItem->setQuantity(1);
            $lineItem->setSku('shipping');
            $tax = $this->getShippingTax($entity);
            if ($tax->getRate() > 0) {
                $lineItem->setTaxes(
                    array(
                    $tax
                    )
                );
            }

            $lineItem->setType(\Wallee\Sdk\Model\LineItem::TYPE_SHIPPING);
            $lineItem->setUniqueId('shipping');
            return $this->cleanLineItem($lineItem);
        }
    }

    /**
     * Returns the tax for the shipping.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \Wallee\Sdk\Model\TaxCreate
     */
    private function getShippingTax($entity)
    {
        /* @var Mage_Tax_Model_Calculation $taxCalculation */
        $taxCalculation = Mage::getSingleton('tax/calculation');

        /* @var Mage_Customer_Model_Group $customerGroup */
        $customerGroup = Mage::getModel('customer/group');

        $classId = $customerGroup->getTaxClassId($entity->getCustomerGroupId());
        $request = $taxCalculation->getRateRequest($entity->getShippingAddress(), $entity->getBillingAddress(), $classId, $entity->getStoreId());
        $shippingTaxClass = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS, $entity->getStore());

        /* @var Mage_Tax_Model_Class $taxClass */
        $taxClass = Mage::getModel('tax/class')->load($shippingTaxClass);

        $tax = new \Wallee\Sdk\Model\TaxCreate();
        $tax->setRate($taxCalculation->getRate($request->setProductClassId($shippingTaxClass)));
        $tax->setTitle($taxClass->getClassName());
        return $tax;
    }

    /**
     * Returns the line item for the Fooman surcharge.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    private function getFoomanSurchargeLineItem($entity)
    {
        if (Mage::helper('core')->isModuleEnabled('Fooman_Surcharge') && $entity->getFoomanSurchargeAmount() != 0) {
            $lineItem = new \Wallee\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax($this->roundAmount($entity->getFoomanSurchargeAmount(), $this->getCurrencyCode($entity)));
            $lineItem->setName($entity->getFoomanSurchargeDescription());
            $lineItem->setQuantity(1);
            $lineItem->setSku('surcharge');
            $tax = $this->getSurchargeTax($entity);
            if ($tax != null && $tax->getRate() > 0) {
                $lineItem->setTaxes(
                    array(
                    $tax
                    )
                );
            }

            $lineItem->setType(\Wallee\Sdk\Model\LineItem::TYPE_FEE);
            $lineItem->setUniqueId('surcharge');
            return $this->cleanLineItem($lineItem);
        }
    }

    /**
     * Returns the tax for the Fooman surcharge.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \Wallee\Sdk\Model\TaxCreate
     */
    private function getSurchargeTax($entity)
    {
        $surchargeTaxClass = Mage::getStoreConfig('tax/classes/surcharge_tax_class', $entity->getStoreId());
        if ($surchargeTaxClass) {
            /* @var Mage_Tax_Model_Calculation $taxCalculation */
            $taxCalculation = Mage::getSingleton('tax/calculation');

            /* @var Mage_Customer_Model_Group $customerGroup */
            $customerGroup = Mage::getModel('customer/group');

            $classId = $customerGroup->getTaxClassId($entity->getCustomerGroupId());
            $request = $taxCalculation->getRateRequest($entity->getShippingAddress(), $entity->getBillingAddress(), $classId, $entity->getStoreId());
            $request->setStore($entity->getStore());
            if ($surchargeTaxRate = $taxCalculation->getRate($request->setProductClassId($surchargeTaxClass))) {
                /* @var Mage_Tax_Model_Class $taxClass */
                $taxClass = Mage::getModel('tax/class')->load($surchargeTaxClass);

                $tax = new \Wallee\Sdk\Model\TaxCreate();
                $tax->setRate($surchargeTaxRate);
                $tax->setTitle($taxClass->getClassName());
                return $tax;
            }
        }
    }

    /**
     * Returns the line item for the MX2 giftcard.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    private function getMX2GiftCardLineItem($entity)
    {
        if (Mage::helper('core')->isModuleEnabled('MX2_Giftcard') && $entity->getGiftCardsAmount() != 0) {
            $lineItem = new \Wallee\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax($this->roundAmount(-1 * $entity->getGiftCardsAmount(), $this->getCurrencyCode($entity)));
            $lineItem->setName(
                $this->getHelper()
                ->__('Giftcard')
            );
            $lineItem->setQuantity(1);
            $lineItem->setSku('giftcard');
            $lineItem->setType(\Wallee\Sdk\Model\LineItem::TYPE_DISCOUNT);
            $lineItem->setUniqueId('giftcard');
            return $this->cleanLineItem($lineItem);
        }
    }

    /**
     * Returns the tax for the given item.
     *
     * @param Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item $item
     * @return \Wallee\Sdk\Model\TaxCreate
     */
    private function getTax($item)
    {
        /* @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($item->getProductId());

        /* @var Mage_Tax_Model_Class $taxClass */
        $taxClass = Mage::getModel('tax/class')->load($product->getTaxClassId());

        $tax = new \Wallee\Sdk\Model\TaxCreate();
        $tax->setRate($item->getTaxPercent());
        $tax->setTitle($taxClass->getClassName());
        return $tax;
    }

    /**
     * Cleans the given line item for it to meet the API's requirements.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate $lineItem
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    private function cleanLineItem(\Wallee\Sdk\Model\LineItemCreate $lineItem)
    {
        $lineItem->setSku($this->fixLength($lineItem->getSku(), 200));
        $lineItem->setName($this->fixLength($lineItem->getName(), 40));
        return $lineItem;
    }

    /**
     * Returns the currency code to use.
     *
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $entity
     * @return string
     */
    private function getCurrencyCode($entity)
    {
        if ($entity instanceof Mage_Sales_Model_Quote) {
            return $entity->getQuoteCurrencyCode();
        } else {
            return $entity->getOrderCurrencyCode();
        }
    }

    /**
     * Returns the line item helper.
     *
     * @return Wallee_Payment_Helper_LineItem
     */
    private function getLineItemHelper()
    {
        return Mage::helper('wallee_payment/lineItem');
    }
}