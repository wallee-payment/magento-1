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
 * Provider of payment connector information from the gateway.
 */
class Wallee_Payment_Model_Provider_PaymentConnector extends Wallee_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('wallee_payment_connectors');
    }

    /**
     * Returns the payment connector by the given id.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\PaymentConnector
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment connectors.
     *
     * @return \Wallee\Sdk\Model\PaymentConnector[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $connectorService = new \Wallee\Sdk\Service\PaymentConnectorService(Mage::helper('wallee_payment')->getApiClient());
        return $connectorService->all();
    }

    protected function getId($entry)
    {
        /* @var \Wallee\Sdk\Model\PaymentConnector $entry */
        return $entry->getId();
    }
}