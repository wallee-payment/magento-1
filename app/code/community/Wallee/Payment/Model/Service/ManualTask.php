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
 * This service provides methods to handle manual tasks.
 */
class Wallee_Payment_Model_Service_ManualTask extends Wallee_Payment_Model_Service_Abstract
{

    const CONFIG_KEY = 'wallee_payment/general/manual_tasks';

    /**
     * Returns the number of open manual tasks.
     *
     * @return array
     */
    public function getNumberOfManualTasks()
    {
        $numberOfManualTasks = array();
        foreach (Mage::app()->getWebsites() as $website) {
            $websiteNumberOfManualTasks = $website->getConfig(self::CONFIG_KEY);
            if ($websiteNumberOfManualTasks != null && $websiteNumberOfManualTasks > 0) {
                $numberOfManualTasks[$website->getId()] = $websiteNumberOfManualTasks;
            }
        }

        return $numberOfManualTasks;
    }

    /**
     * Updates the number of open manual tasks.
     *
     * @return array
     */
    public function update()
    {
        $numberOfManualTasks = array();
        $spaceIds = array();
        $manualTaskService = new \Wallee\Sdk\Service\ManualTaskService($this->getHelper()->getApiClient());
        foreach (Mage::app()->getWebsites() as $website) {
            $spaceId = $website->getConfig('wallee_payment/general/space_id');
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $websiteNumberOfManualTasks = $manualTaskService->manualTaskCountPost($spaceId, $this->createEntityFilter('state', \Wallee\Sdk\Model\ManualTask::STATE_OPEN));
                Mage::getConfig()->saveConfig(self::CONFIG_KEY, $websiteNumberOfManualTasks, 'websites', $website->getId());
                if ($websiteNumberOfManualTasks > 0) {
                    $numberOfManualTasks[$website->getId()] = $websiteNumberOfManualTasks;
                }

                $spaceIds[] = $spaceId;
            } else {
                Mage::getConfig()->deleteConfig(self::CONFIG_KEY, 'websites', $website->getId());
            }
        }

        return $numberOfManualTasks;
    }
}