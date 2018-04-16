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
 * This helper provides functions to handle line items.
 */
class Wallee_Payment_Helper_LineItem extends Mage_Core_Helper_Abstract
{

    /**
     * Returns the amount of the line item's reductions.
     *
     * @param \Wallee\Sdk\Model\LineItem[] $lineItems
     * @param \Wallee\Sdk\Model\LineItemReduction[] $reductions
     * @param string $currencyCode
     * @return float
     */
    public function getReductionAmount(array $lineItems, array $reductions, $currencyCode)
    {
        $lineItemMap = array();
        foreach ($lineItems as $lineItem) {
            $lineItemMap[$lineItem->getUniqueId()] = $lineItem;
        }

        $amount = 0;
        foreach ($reductions as $reduction) {
            $lineItem = $lineItemMap[$reduction->getLineItemUniqueId()];
            $unitPrice = $lineItem->getAmountIncludingTax() / $lineItem->getQuantity();
            $amount += $unitPrice * $reduction->getQuantityReduction();
            $amount += $reduction->getUnitPriceReduction() * ($lineItem->getQuantity() - $reduction->getQuantityReduction());
        }

        return $this->roundAmount($amount, $currencyCode);
    }

    /**
     * Returns the total amount including tax of the given line items.
     *
     * @param \Wallee\Sdk\Model\LineItem[] $lineItems
     * @return float
     */
    public function getTotalAmountIncludingTax(array $lineItems)
    {
        $sum = 0;
        foreach ($lineItems as $lineItem) {
            $sum += $lineItem->getAmountIncludingTax();
        }

        return $sum;
    }

    /**
     * Reduces the amounts of the given line items proportionally to match the given expected sum.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $originalLineItems
     * @param float $expectedSum
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function getItemsByReductionAmount(array $lineItems, $expectedSum)
    {
        if (count($lineItems) <= 0) {
            throw new Exception("No line items provided.");
        }

        $total = $this->getTotalAmountIncludingTax($lineItems);
        $factor = $expectedSum / $total;

        $appliedTotal = 0;
        foreach ($lineItems as $lineItem) {
            /* @var \Wallee\Sdk\Model\LineItem $lineItem */
            $lineItem->setAmountIncludingTax($lineItem->getAmountIncludingTax() * $factor);
            $appliedTotal += $lineItem->getAmountIncludingTax() * $factor;
        }

        // Fix rounding error
        $roundingDifference = $expectedSum - $appliedTotal;
        $lineItems[0]->setAmountIncludingTax($lineItems[0]->getAmountIncludingTax() + $roundingDifference);
        return $this->ensureUniqueIds($lineItems);
    }

    /**
     * Cleans the given line items by ensuring uniqueness and introducing adjustment line items if necessary.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $expectedSum
     * @param string $currency
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function cleanupLineItems(array $lineItems, $expectedSum, $currency)
    {
        $diff = $this->getDifference($lineItems, $expectedSum, $currency);
        if ($diff != 0) {
            $currencyFractionDigits = Mage::helper('wallee_payment')->getCurrencyFractionDigits($currency);
            if (abs($diff) < count($lineItems) * pow(10, -$currencyFractionDigits)) {
                $this->fixDiscountLineItem($lineItems, $diff, $currency);
            }
            
            $this->checkAmount($lineItems, $expectedSum, $currency);
        }

        return $this->ensureUniqueIds($lineItems);
    }
    
    /**
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $amount
     * @param string $currency
     */
    private function getDifference(array $lineItems, $expectedSum, $currency) {
        $effectiveSum = $this->roundAmount($this->getTotalAmountIncludingTax($lineItems), $currency);
        return $this->roundAmount($expectedSum, $currency) - $effectiveSum;
    }
    
    /**
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $amount
     * @param string $currency
     */
    private function checkAmount(array $lineItems, $expectedSum, $currency) {
        $effectiveSum = $this->roundAmount($this->getTotalAmountIncludingTax($lineItems), $currency);
        $diff = $this->roundAmount($expectedSum, $currency) - $effectiveSum;
        if ($diff != 0) {
            throw new \Exception('The line item total amount of ' . $effectiveSum . ' does not match the order\'s invoice amount of ' . $expectedSum . '.');
        }
    }
    
    /**
     * 
     * @param \Wallee\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $amount
     * @param string $currency
     */
    private function fixDiscountLineItem(array &$lineItems, $amount, $currency) {
        foreach (array_reverse($lineItems, true) as $index => $lineItem) {
            if (preg_match('/^(\d+)-discount$/', $lineItem->getUniqueId())) {
                $updatedLineItem = new \Wallee\Sdk\Model\LineItemCreate();
                $updatedLineItem->setAmountIncludingTax($this->roundAmount($lineItem->getAmountIncludingTax() + $amount, $currency));
                $updatedLineItem->setName($lineItem->getName());
                $updatedLineItem->setQuantity($lineItem->getQuantity());
                $updatedLineItem->setSku($lineItem->getSku());
                $updatedLineItem->setUniqueId($lineItem->getUniqueId());
                $updatedLineItem->setShippingRequired($lineItem->getShippingRequired());
                $updatedLineItem->setTaxes($lineItem->getTaxes());
                $updatedLineItem->setType($lineItem->getType());
                $updatedLineItem->setAttributes($lineItem->getAttributes());
                $lineItems[$index] = $updatedLineItem;
                return;
            }
        }
    }

    /**
     * Ensures uniqueness of the line items.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $lineItems
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function ensureUniqueIds(array $lineItems)
    {
        $uniqueIds = array();
        foreach ($lineItems as $lineItem) {
            $uniqueId = $lineItem->getUniqueId();
            if (empty($uniqueId)) {
                $uniqueId = preg_replace("/[^a-z0-9]/", '', strtolower($lineItem->getSku()));
            }

            if (empty($uniqueId)) {
                throw new Exception("There is an invoice item without unique id.");
            }

            if (isset($uniqueIds[$uniqueId])) {
                $backup = $uniqueId;
                $uniqueId = $uniqueId . '_' . $uniqueIds[$uniqueId];
                $uniqueIds[$backup] ++;
            } else {
                $uniqueIds[$uniqueId] = 1;
            }

            $lineItem->setUniqueId($uniqueId);
        }

        return $lineItems;
    }

    private function roundAmount($amount, $currencyCode)
    {
        /* @var Wallee_Payment_Helper_Data $helper */
        $helper = Mage::helper('wallee_payment');
        return round($amount, $helper->getCurrencyFractionDigits($currencyCode));
    }
}