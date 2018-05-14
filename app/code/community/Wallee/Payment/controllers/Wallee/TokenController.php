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
 * This controller provides actions to display and handle tokens.
 */
class Wallee_Payment_Wallee_TokenController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('customer/manage/wallee_token');
    }

    protected function _initCustomer($idFieldName = 'id')
    {
        $customerId = (int) $this->getRequest()->getParam($idFieldName);
        $customer = Mage::getModel('customer/customer');

        if ($customerId) {
            $customer->load($customerId);
        }

        Mage::register('current_customer', $customer);
        return $this;
    }

    /**
     * Renders the grid listing the customer's tokens.
     */
    public function gridAction()
    {
        $this->_initCustomer();
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()
            ->createBlock('wallee_payment/adminhtml_customer_token')
            ->toHtml()
        );
    }

    /**
     * Redirects to the token's detail page in wallee.
     */
    public function viewAction()
    {
        $tokenInfoId = (int) $this->getRequest()->getParam('id');
        /* @var Wallee_Payment_Model_Entity_TokenInfo $tokenInfo */
        $tokenInfo = Mage::getModel('wallee_payment/entity_tokenInfo')->load($tokenInfoId);
        if ($tokenInfo->getId() == null) {
            Mage::throwException('Token not found.');
        }

        $this->_redirectUrl(Mage::helper('wallee_payment')->getBaseGatewayUrl() . '/s/' . $tokenInfo->getSpaceId() . '/payment/token/view/' . $tokenInfo->getTokenId());
    }

    /**
     * Delets the token with the given id on the gateway.
     */
    public function deleteAction()
    {
        $tokenInfoId = (int) $this->getRequest()->getParam('id');
        /* @var Wallee_Payment_Model_Entity_TokenInfo $tokenInfo */
        $tokenInfo = Mage::getModel('wallee_payment/entity_tokenInfo')->load($tokenInfoId);
        if ($tokenInfo->getId() != null) {
            /* @var Wallee_Payment_Model_Service_Token $tokenService */
            $tokenService = Mage::getSingleton('wallee_payment/service_token');
            $tokenService->deleteToken($tokenInfo->getSpaceId(), $tokenInfo->getTokenId());
        }

        $this->_getSession()->addSuccess(Mage::helper('wallee_payment')->__('The token has been deleted.'));
        $this->_redirect(
            'adminhtml/customer/edit', array(
            'id' => $tokenInfo->getCustomerId(),
            'active_tab' => 'wallee_payment_token'
            )
        );
    }
}