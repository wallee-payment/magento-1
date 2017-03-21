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
 * This entity holds data about a token on the gateway.
 *
 * @method int getTokenId()
 * @method Wallee_Payment_Model_Entity_TokenInfo setTokenId(int tokenId)
 * @method string getState()
 * @method Wallee_Payment_Model_Entity_TokenInfo setState(string state)
 * @method int getSpaceId()
 * @method Wallee_Payment_Model_Entity_TokenInfo setSpaceId(int spaceId)
 * @method string getName()
 * @method Wallee_Payment_Model_Entity_TokenInfo setName(string name)
 * @method string getCreatedAt()
 * @method int getCustomerId()
 * @method Wallee_Payment_Model_Entity_TokenInfo setCustomerId(int customerId)
 * @method int getPaymentMethodId()
 * @method Wallee_Payment_Model_Entity_TokenInfo setPaymentMethodId(int paymentMethodId)
 * @method int getConnectorId()
 * @method Wallee_Payment_Model_Entity_TokenInfo setConnectorId(int connectorId)
 */
class Wallee_Payment_Model_Entity_TokenInfo extends Mage_Core_Model_Abstract
{

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'wallee_payment_token_info';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    protected $_eventObject = 'tokenInfo';

    /**
     *
     * @var Mage_Customer_Model_Customer
     */
    private $_customer;

    /**
     *
     * @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration
     */
    private $_paymentMethod;

    /**
     *
     * @var \Wallee\Sdk\Model\PaymentConnector
     */
    private $_connector;

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('wallee_payment/tokenInfo');
    }

    protected function _beforeSave()
    {
        parent::_beforeSave();

        if ($this->isObjectNew()) {
            $this->setCreatedAt(date("Y-m-d H:i:s"));
        }
    }

    /**
     * Loading token info by token id.
     *
     * @param int $spaceId
     * @param int $tokenId
     * @return Wallee_Payment_Model_Entity_TokenInfo
     */
    public function loadByToken($spaceId, $tokenId)
    {
        $this->_getResource()->loadByToken($this, $spaceId, $tokenId);
        return $this;
    }

    /**
     * Returns the customer the token belongs to.
     *
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer()
    {
        if (! $this->_customer instanceof Mage_Customer_Model_Customer) {
            $this->_customer = Mage::getModel('customer/customer')->load($this->getCustomerId());
        }

        return $this->_customer;
    }

    /**
     * Returns the payment method the token belongs to.
     *
     * @return Wallee_Payment_Model_Entity_PaymentMethodConfiguration
     */
    public function getPaymentMethod()
    {
        if (! $this->_paymentMethod instanceof Wallee_Payment_Model_Entity_PaymentMethodConfiguration) {
            $this->_paymentMethod = Mage::getModel('wallee_payment/entity_paymentMethodConfiguration')->load($this->getPaymentMethodId());
        }

        return $this->_paymentMethod;
    }

    /**
     * Returns the payment method the token belongs to.
     *
     * @return \Wallee\Sdk\Model\PaymentConnector
     */
    public function getConnector()
    {
        if (! $this->_connector instanceof \Wallee\Sdk\Model\PaymentConnector) {
            $this->_connector = Mage::getSingleton('wallee_payment/provider_paymentConnector')->find($this->getConnectorId());
        }

        return $this->_connector;
    }
}