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
 * Abstract service providing shared methods.
 */
class Wallee_Payment_Model_Service_Abstract
{

    /**
     * Returns the data helper.
     *
     * @return Wallee_Payment_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('wallee_payment');
    }

    /**
     * Returns the fraction digits for the given currency.
     *
     * @param string $currencyCode
     * @return number
     */
    protected function getCurrencyFractionDigits($currencyCode)
    {
        return $this->getHelper()->getCurrencyFractionDigits($currencyCode);
    }

    /**
     * Rounds the given amount to the currency's format.
     *
     * @param float $amount
     * @param string $currencyCode
     * @return number
     */
    protected function roundAmount($amount, $currencyCode)
    {
        return round($amount, $this->getCurrencyFractionDigits($currencyCode));
    }

    /**
     * Creates and returns a new entity filter.
     *
     * @param string $fieldName
     * @param mixed $value
     * @param string $operator
     * @return \Wallee\Sdk\Model\EntityQueryFilter
     */
    protected function createEntityFilter($fieldName, $value, $operator = \Wallee\Sdk\Model\CriteriaOperator::EQUALS)
    {
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::LEAF);
        $filter->setOperator($operator);
        $filter->setFieldName($fieldName);
        $filter->setValue($value);
        return $filter;
    }

    /**
     * Creates and returns a new entity order by.
     *
     * @param string $fieldName
     * @param string $sortOrder
     * @return \Wallee\Sdk\Model\EntityQueryOrderBy
     */
    protected function createEntityOrderBy($fieldName, $sortOrder = \Wallee\Sdk\Model\EntityQueryOrderByType::DESC)
    {
        $orderBy = new \Wallee\Sdk\Model\EntityQueryOrderBy();
        $orderBy->setFieldName($fieldName);
        $orderBy->setSorting($sortOrder);
        return $orderBy;
    }

    /**
     * Changes the given string to have no more characters as specified.
     *
     * @param string $string
     * @param int $maxLength
     * @return string
     */
    protected function fixLength($string, $maxLength)
    {
        return mb_substr($string, 0, $maxLength, 'UTF-8');
    }
}