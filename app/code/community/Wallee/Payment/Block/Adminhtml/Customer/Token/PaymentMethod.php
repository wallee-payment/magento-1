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
 * This block renders the payment method column in the token grid.
 */
class Wallee_Payment_Block_Adminhtml_Customer_Token_PaymentMethod extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{

    public function _getValue(Varien_Object $row)
    {
        /* @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod */
        $paymentMethod = Mage::getModel('wallee_payment/entity_paymentMethodConfiguration')->load($row->payment_method_id);
        return $paymentMethod->getConfigurationName();
    }
}