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
 * This block renders the payment method column in the token grid.
 */
class Wallee_Payment_Block_Adminhtml_Customer_Token_PaymentMethod extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{

    public function _getValue(Varien_Object $row)
    {
        /* @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod */
        $paymentMethod = Mage::getModel('wallee_payment/entity_paymentMethodConfiguration')->load(
            $row->payment_method_id);
        return $paymentMethod->getConfigurationName();
    }
}