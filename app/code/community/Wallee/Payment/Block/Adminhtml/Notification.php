<?php

/**
 * Wallee Magento
 *
 * This Magento extension enables to process payments with Wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * This block renders an information bar in the store's backend to signalize if there are open manual tasks.
 */
class Wallee_Payment_Block_Adminhtml_Notification extends Mage_Adminhtml_Block_Template
{

    /**
     * Returns whether output is enabled for the admin notification module.
     *
     * @return boolean
     */
    public function isAdminNotificationEnabled()
    {
        if (! $this->isOutputEnabled('Mage_AdminNotification')) {
            return false;
        }

        return true;
    }

    /**
     * Returns the URL to check the open manual tasks.
     *
     * @return string
     */
    public function getManualTasksUrl($websiteId = null)
    {
        $manualTaskUrl = Mage::helper('wallee_payment')->getBaseGatewayUrl();
        if ($websiteId != null) {
            $spaceId = Mage::app()->getWebsite($websiteId)->getConfig('wallee_payment/general/space_id');
            $manualTaskUrl .= '/s/' . $spaceId . '/manual-task/list';
        }

        return $manualTaskUrl;
    }

    /**
     * Returns the number of open manual tasks.
     *
     * @return number
     */
    public function getNumberOfManualTasks()
    {
        /* @var Wallee_Payment_Model_Service_ManualTask $manualTaskService */
        $manualTaskService = Mage::getSingleton('wallee_payment/service_manualTask');
        return $manualTaskService->getNumberOfManualTasks();
    }
}