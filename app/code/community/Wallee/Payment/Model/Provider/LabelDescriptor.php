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
 * Provider of label descriptor information from the gateway.
 */
class Wallee_Payment_Model_Provider_LabelDescriptor extends Wallee_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('wallee_payment_label_descriptor');
    }

    /**
     * Returns the label descriptor by the given code.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\LabelDescriptor
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptors.
     *
     * @return \Wallee\Sdk\Model\LabelDescriptor[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorService = new \Wallee\Sdk\Service\LabelDescriptionService(
            Mage::helper('wallee_payment')->getApiClient());
        return $labelDescriptorService->all();
    }

    protected function getId($entry)
    {
        /* @var \Wallee\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}