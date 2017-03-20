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
 * This block renders the grid tab that lists the customer's tokens.
 */
class Wallee_Payment_Block_Adminhtml_Customer_Token extends Mage_Adminhtml_Block_Widget_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    protected function _construct()
    {
        parent::_construct();
        $this->setId('wallee_payment_adminhtml_customer_token');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
        $this->setSkipGenerateContent(true);
    }

    /**
     * Prepares the token grid collection.
     *
     * @return Wallee_Payment_Block_Adminhtml_Customer_Token
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('wallee_payment/entity_tokenInfo')->getCollection()->addCustomerFilter(Mage::registry('current_customer')->getId());
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepares the token grid's columns.
     *
     * @return Wallee_Payment_Block_Adminhtml_Customer_Token
     */
    protected function _prepareColumns()
    {
        $helper = Mage::helper('wallee_payment');

        $this->addColumn(
            'token_id', array(
            'header' => $helper->__('Token ID'),
            'width' => '50px',
            'type' => 'number',
            'index' => 'token_id'
            )
        );

        $this->addColumn(
            'name', array(
            'header' => $helper->__('Name'),
            'width' => '250px',
            'type' => 'text',
            'index' => 'name'
            )
        );

        $this->addColumn(
            'payment_method_id', array(
            'header' => $helper->__('Payment Method'),
            'type' => 'text',
            'index' => 'payment_method_id',
            'renderer' => 'Wallee_Payment_Block_Adminhtml_Customer_Token_PaymentMethod'
            )
        );

        $this->addColumn(
            'state', array(
            'header' => $helper->__('State'),
            'type' => 'text',
            'index' => 'state',
            'renderer' => 'Wallee_Payment_Block_Adminhtml_Customer_Token_State'
            )
        );

        $this->addColumn(
            'action', array(
            'header' => $helper->__('Action'),
            'width' => '50px',
            'type' => 'action',
            'getter' => 'getId',
            'actions' => array(
                array(
                    'caption' => $helper->__('Delete'),
                    'url' => array(
                        'base' => 'adminhtml/wallee_token/delete'
                    ),
                    'field' => 'id'
                )
            ),
            'filter' => false,
            'sortable' => false,
            'index' => 'stores',
            'is_system' => true
            )
        );

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return;
    }

    public function getGridUrl()
    {
        return $this->getTabUrl();
    }

    public function getTabUrl()
    {
        return $this->getUrl(
            'adminhtml/wallee_token/grid', array(
            'id' => Mage::registry('current_customer')->getId(),
            '_current' => true
            )
        );
    }

    public function getTabClass()
    {
        return 'ajax';
    }

    public function getTabLabel()
    {
        return Mage::helper('wallee_payment')->__('Wallee Tokens');
    }

    public function getTabTitle()
    {
        return Mage::helper('wallee_payment')->__('Wallee Tokens');
    }

    public function canShowTab()
    {
        return Mage::getSingleton('admin/session')->isAllowed('customer/manage/wallee_token');
    }

    public function isHidden()
    {
        return false;
    }
}