<?php

/**
 * wallee Magento
 *
 * This Magento extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * Resource collection of payment method configuration.
 */
class Wallee_Payment_Model_Resource_PaymentMethodConfiguration_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{

    protected function _construct()
    {
        $this->_init('wallee_payment/entity_paymentMethodConfiguration');
    }

    /**
     * Filters the collection by space.
     *
     * @param int $spaceId
     * @return Wallee_Payment_Model_Resource_PaymentMethodConfiguration_Collection
     */
    public function addSpaceFilter($spaceId)
    {
        $this->addFieldToFilter('main_table.space_id', $spaceId);
        return $this;
    }

    /**
     * Filters the collection by active state.
     *
     * @return Wallee_Payment_Model_Resource_PaymentMethodConfiguration_Collection
     */
    public function addActiveStateFilter()
    {
        $this->addFieldToFilter('main_table.state', Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_ACTIVE);
        return $this;
    }

    /**
     * Filters the collection by non-hidden state.
     *
     * @return Wallee_Payment_Model_Resource_PaymentMethodConfiguration_Collection
     */
    public function addStateFilter()
    {
        $this->addFieldToFilter(
            'main_table.state', array(
            'in' => array(
                Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_ACTIVE,
                Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_INACTIVE
            )
            )
        );
        return $this;
    }
}