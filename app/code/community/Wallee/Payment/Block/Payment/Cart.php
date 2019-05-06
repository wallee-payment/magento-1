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
 * This block extends the cart to be able to collect device data.
 */
class Wallee_Payment_Block_Payment_Cart extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('wallee/payment/cart.phtml');
    }

    /**
     * Returns the URL to wallee's Javascript library to collect customer data.
     *
     * @return string
     */
    public function getDeviceJavascriptUrl()
    {
        /* @var Wallee_Payment_Helper_Data $helper */
        $helper = Mage::helper('wallee_payment');
        return $helper->getDeviceJavascriptUrl();
    }
}