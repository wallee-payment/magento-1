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
 * This block renders the state column in the token grid.
 */
class Wallee_Payment_Block_Adminhtml_Customer_Token_State extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{

    public function _getValue(Varien_Object $row)
    {
        $helper = Mage::helper('wallee_payment');
        switch ($row->state) {
            case \Wallee\Sdk\Model\Token::STATE_ACTIVE:
                return $helper->__('Active');
            case \Wallee\Sdk\Model\Token::STATE_INACTIVE:
                return $helper->__('Inactive');
        }
    }
}