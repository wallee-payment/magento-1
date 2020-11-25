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
 * Provider of label descriptor group information from the gateway.
 */
class Wallee_Payment_Model_Provider_LabelDescriptorGroup extends Wallee_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('wallee_payment_label_descriptor_group');
    }

    /**
     * Returns the label descriptor group by the given code.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\LabelDescriptorGroup
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptor groups.
     *
     * @return \Wallee\Sdk\Model\LabelDescriptorGroup[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorGroupService = new \Wallee\Sdk\Service\LabelDescriptionGroupService(
            Mage::helper('wallee_payment')->getApiClient());
        return $labelDescriptorGroupService->all();
    }

    protected function getId($entry)
    {
        /* @var \Wallee\Sdk\Model\LabelDescriptorGroup $entry */
        return $entry->getId();
    }
}