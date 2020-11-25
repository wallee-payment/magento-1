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
 * This block renders the state column in the token grid.
 */
class Wallee_Payment_Block_Adminhtml_Customer_Token_State extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{

    public function _getValue(Varien_Object $row)
    {
        $helper = Mage::helper('wallee_payment');
        switch ($row->state) {
            case \Wallee\Sdk\Model\CreationEntityState::ACTIVE:
                return $helper->__('Active');
            case \Wallee\Sdk\Model\CreationEntityState::INACTIVE:
                return $helper->__('Inactive');
        }
    }
}