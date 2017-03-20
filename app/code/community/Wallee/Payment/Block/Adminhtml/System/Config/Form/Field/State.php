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
 * This block renders the form field that displays a simple state, yes or no.
 */
class Wallee_Payment_Block_Adminhtml_System_Config_Form_Field_State extends Wallee_Payment_Block_Adminhtml_System_Config_Form_Field_Label
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $value = $element->getData('value');
        return $value == 1 ? $this->helper('wallee_payment')->__('Yes') : $this->helper('wallee_payment')->__('No');
    }
}