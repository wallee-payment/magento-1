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
 * The block renders the payment information.
 */
class Wallee_Payment_Block_Payment_Info extends Mage_Payment_Block_Info
{

    private $transaction = null;

    private $transactionInfo = null;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('wallee/payment/info.phtml');
    }

    /**
     * Returns whether the payment information are to be displayed in the creditmemo detail view in the backend.
     *
     * @return boolean
     */
    public function isCreditmemo()
    {
        return Mage::app()->getStore()->isAdmin() && strstr($this->getRequest()->getControllerName(), 'creditmemo') !== false;
    }

    /**
     * Returns whether the payment information are to be displayed in the invoice detail view in the backend.
     *
     * @return boolean
     */
    public function isInvoice()
    {
        return Mage::app()->getStore()->isAdmin() && strstr($this->getRequest()->getControllerName(), 'invoice') !== false;
    }

    /**
     * Returns whether the payment information are to be displayed in the shipment detail view in the backend.
     *
     * @return boolean
     */
    public function isShipment()
    {
        return Mage::app()->getStore()->isAdmin() && strstr($this->getRequest()->getControllerName(), 'shipment') !== false;
    }

    /**
     * Returns whether the customer is allowed to download invoice documents.
     *
     * @return boolean
     */
    public function isCustomerDownloadInvoiceAllowed()
    {
        return $this->getInfo()->getOrder() != null && Mage::getStoreConfigFlag('wallee_payment/document/customer_download_invoice', $this->getInfo()
            ->getOrder()
            ->getStore());
    }

    /**
     * Returns whether the customer is allowed to download packing slips.
     *
     * @return boolean
     */
    public function isCustomerDownloadPackingSlipAllowed()
    {
        return $this->getInfo()->getOrder() != null && Mage::getStoreConfigFlag('wallee_payment/document/customer_download_packing_slip', $this->getInfo()
            ->getOrder()
            ->getStore());
    }

    /**
     * Returns the URL to update the transaction's information.
     *
     * @return string
     */
    public function getUpdateTransactionUrl()
    {
        if ($this->getTransactionInfo() && Mage::app()->getStore()->isAdmin()) {
            /* @var Mage_Adminhtml_Helper_Data $adminHelper */
            $adminHelper = Mage::helper('adminhtml');
            return $adminHelper->getUrl('adminhtml/wallee_transaction/update', array(
                'transaction_id' => $this->getTransactionInfo()
                    ->getTransactionId(),
                'space_id' => $this->getTransactionInfo()
                    ->getSpaceId(),
                '_secure' => true
            ));
        }
    }

    /**
     * Returns the URL to download the transaction's invoice PDF document.
     *
     * @return string
     */
    public function getDownloadInvoiceUrl()
    {
        if (! $this->getTransactionInfo() || ! in_array($this->getTransactionInfo()->getState(), array(
            \Wallee\Sdk\Model\TransactionState::COMPLETED,
            \Wallee\Sdk\Model\TransactionState::FULFILL,
            \Wallee\Sdk\Model\TransactionState::DECLINE
        ))) {
            return false;
        }
        
        if (Mage::app()->getStore()->isAdmin()) {
            /* @var Mage_Adminhtml_Helper_Data $adminHelper */
            $adminHelper = Mage::helper('adminhtml');
            return $adminHelper->getUrl('adminhtml/wallee_transaction/downloadInvoice', array(
                'transaction_id' => $this->getTransactionInfo()
                    ->getTransactionId(),
                'space_id' => $this->getTransactionInfo()
                    ->getSpaceId(),
                '_secure' => true
            ));
        } else {
            return $this->getUrl('wallee/transaction/downloadInvoice', array(
                'order_id' => $this->getInfo()
                    ->getOrder()
                    ->getId()
            ));
        }
    }

    /**
     * Returns the URL to download the transaction's packing slip PDF document.
     *
     * @return string
     */
    public function getDownloadPackingSlipUrl()
    {
        if (! $this->getTransactionInfo() || $this->getTransactionInfo()->getState() != \Wallee\Sdk\Model\TransactionState::FULFILL) {
            return false;
        }
        
        if (Mage::app()->getStore()->isAdmin()) {
            /* @var Mage_Adminhtml_Helper_Data $adminHelper */
            $adminHelper = Mage::helper('adminhtml');
            return $adminHelper->getUrl('adminhtml/wallee_transaction/downloadPackingSlip', array(
                'transaction_id' => $this->getTransactionInfo()
                    ->getTransactionId(),
                'space_id' => $this->getTransactionInfo()
                    ->getSpaceId(),
                '_secure' => true
            ));
        } else {
            return $this->getUrl('wallee/transaction/downloadPackingSlip', array(
                'order_id' => $this->getInfo()
                    ->getOrder()
                    ->getId()
            ));
        }
    }

    /**
     * Returns the URL to download the refund PDF document.
     *
     * @return string
     */
    public function getDownloadRefundUrl()
    {
        /* @var Mage_Sales_Model_Order_Creditmemo $creditmemo */
        $creditmemo = Mage::registry('current_creditmemo');
        if ($creditmemo == null || $creditmemo->getWalleeExternalId() == null) {
            return false;
        }
        
        /* @var Mage_Adminhtml_Helper_Data $adminHelper */
        $adminHelper = Mage::helper('adminhtml');
        return $adminHelper->getUrl('adminhtml/wallee_transaction/downloadRefund', array(
            'external_id' => $creditmemo->getWalleeExternalId(),
            'space_id' => $this->getTransactionInfo()
                ->getSpaceId(),
            '_secure' => true
        ));
    }

    /**
     * Returns the transaction info.
     *
     * @return Wallee_Payment_Model_Entity_TransactionInfo
     */
    public function getTransactionInfo()
    {
        if ($this->transactionInfo === null) {
            if ($this->getInfo() instanceof Mage_Sales_Model_Order_Payment) {
                /* @var Wallee_Payment_Model_Entity_TransactionInfo $transactionInfo */
                $transactionInfo = Mage::getModel('wallee_payment/entity_transactionInfo')->loadByOrder($this->getInfo()
                    ->getOrder());
                if ($transactionInfo->getId()) {
                    $this->transactionInfo = $transactionInfo;
                } else {
                    $this->transactionInfo = false;
                }
            } else {
                $this->transactionInfo = false;
            }
        }
        
        return $this->transactionInfo;
    }

    /**
     * Returns the URL to the payment method image.
     *
     * @return string
     */
    public function getImageUrl()
    {
        /* @var Wallee_Payment_Model_Payment_Method_Abstract $methodInstance */
        $methodInstance = $this->getMethod();
        $spaceId = $methodInstance->getPaymentMethodConfiguration()->getSpaceId();
        $spaceViewId = $this->getTransactionInfo() ? $this->getTransactionInfo()->getSpaceViewId() : null;
        $language = $this->getTransactionInfo() ? $this->getTransactionInfo()->getLanguage() : null;
        /* @var Wallee_Payment_Helper_Data $helper */
        $helper = $this->helper('wallee_payment');
        return $helper->getResourceUrl($methodInstance->getPaymentMethodConfiguration()
            ->getResourceDomain(), $methodInstance->getPaymentMethodConfiguration()
            ->getImage(), $language, $spaceId, $spaceViewId);
    }

    /**
     * Returns the URL to the transaction detail view in wallee.
     *
     * @return string
     */
    public function getTransactionUrl()
    {
        return Mage::helper('wallee_payment')->getBaseGatewayUrl() . '/s/' . $this->getTransactionInfo()->getSpaceId() . '/payment/transaction/view/' . $this->getTransactionInfo()->getTransactionId();
    }

    /**
     * Returns the translated name of the transaction's state.
     *
     * @return string
     */
    public function getTransactionState()
    {
        /* @var Wallee_Payment_Helper_Data $helper */
        $helper = $this->helper('wallee_payment');
        switch ($this->getTransactionInfo()->getState()) {
            case \Wallee\Sdk\Model\TransactionState::AUTHORIZED:
                return $helper->__('Authorized');
            case \Wallee\Sdk\Model\TransactionState::COMPLETED:
                return $helper->__('Completed');
            case \Wallee\Sdk\Model\TransactionState::CONFIRMED:
                return $helper->__('Confirmed');
            case \Wallee\Sdk\Model\TransactionState::DECLINE:
                return $helper->__('Decline');
            case \Wallee\Sdk\Model\TransactionState::FAILED:
                return $helper->__('Failed');
            case \Wallee\Sdk\Model\TransactionState::FULFILL:
                return $helper->__('Fulfill');
            case \Wallee\Sdk\Model\TransactionState::PENDING:
                return $helper->__('Pending');
            case \Wallee\Sdk\Model\TransactionState::PROCESSING:
                return $helper->__('Processing');
            case \Wallee\Sdk\Model\TransactionState::VOIDED:
                return $helper->__('Voided');
            default:
                return $helper->__('Unknown State');
        }
    }

    /**
     * Returns the transaction's currency.
     *
     * @return Mage_Directory_Model_Currency
     */
    public function getTransactionCurrency()
    {
        return Mage::getModel('directory/currency')->load($this->getTransactionInfo()
            ->getCurrency());
    }

    /**
     * Returns the charge attempt's labels by their groups.
     *
     * @return \Wallee\Sdk\Model\Label[]
     */
    public function getGroupedChargeAttemptLabels()
    {
        if ($this->getTransactionInfo()) {
            /* @var Wallee_Payment_Model_Provider_LabelDescriptor $labelDescriptorProvider */
            $labelDescriptorProvider = Mage::getSingleton('wallee_payment/provider_labelDescriptor');
            
            /* @var Wallee_Payment_Model_Provider_LabelDescriptorGroup $labelDescriptorGroupProvider */
            $labelDescriptorGroupProvider = Mage::getSingleton('wallee_payment/provider_labelDescriptorGroup');
            
            $labelsByGroupId = array();
            foreach ($this->getTransactionInfo()->getLabels() as $descriptorId => $value) {
                $descriptor = $labelDescriptorProvider->find($descriptorId);
                if ($descriptor) {
                    $labelsByGroupId[$descriptor->getGroup()][] = array(
                        'descriptor' => $descriptor,
                        'value' => $value
                    );
                }
            }
            
            $labelsByGroup = array();
            foreach ($labelsByGroupId as $groupId => $labels) {
                $group = $labelDescriptorGroupProvider->find($groupId);
                if ($group) {
                    usort($labels, function ($a, $b) {
                        return $a['descriptor']->getWeight() - $b['descriptor']->getWeight();
                    });
                    $labelsByGroup[] = array(
                        'group' => $group,
                        'labels' => $labels
                    );
                }
            }
            
            usort($labelsByGroup, function ($a, $b) {
                return $a['group']->getWeight() - $b['group']->getWeight();
            });
            return $labelsByGroup;
        } else {
            return array();
        }
    }
}