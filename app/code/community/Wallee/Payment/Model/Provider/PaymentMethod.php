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
 * Provider of payment method information from the gateway.
 */
class Wallee_Payment_Model_Provider_PaymentMethod extends Wallee_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('wallee_payment_methods');
    }

    /**
     * Returns the payment method by the given id.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\PaymentMethod
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment methods.
     *
     * @return \Wallee\Sdk\Model\PaymentMethod[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $methodService = new \Wallee\Sdk\Service\PaymentMethodService(
            Mage::helper('wallee_payment')->getApiClient());
        return $methodService->all();
    }

    protected function getId($entry)
    {
        /* @var \Wallee\Sdk\Model\PaymentMethod $entry */
        return $entry->getId();
    }
}