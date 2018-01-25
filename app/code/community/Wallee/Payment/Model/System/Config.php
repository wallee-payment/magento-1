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
 * Handles the dynamic payment method configs.
 */
class Wallee_Payment_Model_System_Config
{

    /**
     * Initializes the dynamic payment method system config.
     *
     * @param Mage_Core_Model_Config_Base $config
     */
    public function initSystemConfig(Mage_Core_Model_Config_Base $config)
    {
        $spaceId = Mage::getModel('core/website')->load(Mage::getSingleton('adminhtml/config_data')->getWebsite())
            ->getConfig('wallee_payment/general/space_id');
        if ($spaceId) {
            $mergeModel = new Mage_Core_Model_Config_Base();
            $paymentMethodTemplate = file_get_contents(Mage::getModuleDir('etc', 'Wallee_Payment') . DS . 'payment_method.system.xml.tpl');
            foreach (Mage::getModel('wallee_payment/entity_paymentMethodConfiguration')->getCollection()
                ->addSpaceFilter($spaceId)
                ->addStateFilter() as $paymentMethod) {
                $mergeModel->loadString(
                    str_replace(
                        array(
                        '{id}',
                        '{name}'
                        ), array(
                        $paymentMethod->getId(),
                        $paymentMethod->getConfigurationName()
                        ), $paymentMethodTemplate
                    )
                );
                $config->extend($mergeModel, true);
            }
        }
    }

    /**
     * Initializes the dynamic payment method config values.
     */
    public function initConfigValues()
    {
        if (! $this->isTableExists()) {
            return;
        }

        $websiteMap = array();
        foreach (Mage::app()->getWebsites() as $website) {
            $websiteMap[$website->getConfig('wallee_payment/general/space_id')][] = $website;
        }

        /* @var Wallee_Payment_Model_Resource_PaymentMethodConfiguration_Collection $collection */
        $collection = Mage::getModel('wallee_payment/entity_paymentMethodConfiguration')->getCollection();
        foreach ($collection as $paymentMethod) {
            /* @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod */
            if (isset($websiteMap[$paymentMethod->getSpaceId()])) {
                $basePath = '/payment/wallee_payment_' . $paymentMethod->getId() . '/';
                $active = $paymentMethod->getState() == Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_ACTIVE ? 1 : 0;
                $model = 'wallee_payment/paymentMethod' . $paymentMethod->getId();
                $action = Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;

                $this->setConfigValue('stores/admin' . $basePath . 'active', $active);
                $this->setConfigValue('stores/admin' . $basePath . 'title', $this->getPaymentMethodTitle($paymentMethod, 'en-US'));
                $this->setConfigValue('stores/admin' . $basePath . 'model', $model);
                $this->setConfigValue('stores/admin' . $basePath . 'payment_action', $action);

                foreach ($websiteMap[$paymentMethod->getSpaceId()] as $website) {
                    $this->setConfigValue('websites/' . $website->getCode() . $basePath . 'active', $active);
                    $this->setConfigValue('websites/' . $website->getCode() . $basePath . 'title', $this->getPaymentMethodTitle($paymentMethod, $website->getConfig('general/locale/code')));
                    $this->setConfigValue('websites/' . $website->getCode() . $basePath . 'description', $paymentMethod->getDescription($website->getConfig('general/locale/code')));
                    $this->setConfigValue('websites/' . $website->getCode() . $basePath . 'sort_order', $paymentMethod->getSortOrder());
                    $this->setConfigValue('websites/' . $website->getCode() . $basePath . 'show_description', 1);
                    $this->setConfigValue('websites/' . $website->getCode() . $basePath . 'show_image', 1);
                    $this->setConfigValue('websites/' . $website->getCode() . $basePath . 'model', $model);
                    $this->setConfigValue('websites/' . $website->getCode() . $basePath . 'payment_action', $action);

                    foreach ($website->getStores() as $store) {
                        /* @var Mage_Core_Model_Store $store */
                        $this->setConfigValue('stores/' . $store->getCode() . $basePath . 'active', $active);
                        $this->setConfigValue('stores/' . $store->getCode() . $basePath . 'title', $this->getPaymentMethodTitle($paymentMethod, $store->getConfig('general/locale/code')));
                        $this->setConfigValue('stores/' . $store->getCode() . $basePath . 'description', $paymentMethod->getDescription($store->getConfig('general/locale/code')));
                        $this->setConfigValue('stores/' . $store->getCode() . $basePath . 'sort_order', $paymentMethod->getSortOrder());
                        $this->setConfigValue('stores/' . $store->getCode() . $basePath . 'show_description', 1);
                        $this->setConfigValue('stores/' . $store->getCode() . $basePath . 'show_image', 1);
                        $this->setConfigValue('stores/' . $store->getCode() . $basePath . 'model', $model);
                        $this->setConfigValue('stores/' . $store->getCode() . $basePath . 'payment_action', $action);
                    }
                }
            }
        }
    }

    /**
     * Returns the title for the payment method in the given language.
     *
     * @param Wallee_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod
     * @param string $locale
     * @return string
     */
    private function getPaymentMethodTitle(Wallee_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod, $locale)
    {
        $translatedTitle = $paymentMethod->getTitle($locale);
        if (! empty($translatedTitle)) {
            return $translatedTitle;
        } else {
            return $paymentMethod->getConfigurationName();
        }
    }

    /**
     * Sets the config value if not already set.
     *
     * @param string $path
     * @param mixed $value
     */
    private function setConfigValue($path, $value)
    {
        if (Mage::getConfig()->getNode($path) === false) {
            Mage::getConfig()->setNode($path, $value);
        }
    }

    /**
     * Returns whether the payment method configuration database table exists.
     *
     * @return boolean
     */
    private function isTableExists()
    {
        /* @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        if ($connection) {
            return $connection->isTableExists($resource->getTableName('wallee_payment/payment_method_configuration'));
        } else {
            return false;
        }
    }
}