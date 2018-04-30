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
 * This service provides functions to deal with wallee charge flows.
 */
class Wallee_Payment_Model_Service_ChargeFlow extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * The charge flow API service.
     *
     * @var \Wallee\Sdk\Service\ChargeFlowService
     */
    private $chargeFlowService;

    /**
     * Apply a charge flow to the given transaction.
     *
     * @param \Wallee\Sdk\Model\Transaction $transaction
     */
    public function applyFlow(\Wallee\Sdk\Model\Transaction $transaction)
    {
        $this->getChargeFlowService()->applyFlow($transaction->getLinkedSpaceId(), $transaction->getId());
    }

    /**
     * Returns the charge flow API service.
     *
     * @return \Wallee\Sdk\Service\ChargeFlowService
     */
    protected function getChargeFlowService()
    {
        if ($this->chargeFlowService == null) {
            $this->chargeFlowService = new \Wallee\Sdk\Service\ChargeFlowService($this->getHelper()->getApiClient());
        }

        return $this->chargeFlowService;
    }
}