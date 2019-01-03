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
 * The observer handles general events.
 */
class Wallee_Payment_Model_Observer_Core
{

    protected $_autoloaderRegistered = false;

    /**
     * Registers an autoloader that provides the generated payment method model classes.
     *
     * The varien autoloader is unregistered and registered again to allow the wallee SDK autoloader to come
     * first.
     */
    public function addAutoloader()
    {
        if (! $this->_autoloaderRegistered) {
            spl_autoload_unregister(array(
                Varien_Autoload::instance(),
                'autoload'
            ));
            require_once Mage::getBaseDir('lib') . '/Wallee/Sdk/autoload.php';
            spl_autoload_register(array(
                Varien_Autoload::instance(),
                'autoload'
            ));

            set_include_path(
                get_include_path() . PATH_SEPARATOR .
                Mage::helper('wallee_payment')->getGenerationDirectoryPath());

            spl_autoload_register(
                function ($class) {
                    if (strpos($class, 'Wallee_Payment_Model_PaymentMethod') === 0) {
                        $file = Mage::helper('wallee_payment')->getGenerationDirectoryPath() . DS .
                        uc_words($class, DIRECTORY_SEPARATOR) . '.php';
                        if (file_exists($file)) {
                            require $file;
                        }
                    }
                }, true, true);
            $this->_autoloaderRegistered = true;
        }
    }

    /**
     * Initializes the dynamic payment method system config.
     *
     * @param Varien_Event_Observer $observer
     */
    public function initSystemConfig(Varien_Event_Observer $observer)
    {
        $this->getConfigModel()->initSystemConfig($observer->getConfig());
    }

    /**
     * Initializes the dynamic payment method config values.
     */
    public function frontInitBefore()
    {
        $this->getConfigModel()->initConfigValues();
    }

    /**
     * Synchronizes the data with wallee.
     */
    public function configChanged()
    {
        $userId = Mage::getStoreConfig('wallee_payment/general/api_user_id');
        $applicationKey = Mage::getStoreConfig('wallee_payment/general/api_user_secret');
        if ($userId && $applicationKey) {
            try {
                Mage::dispatchEvent('wallee_payment_config_synchronize');
            } catch (Exception $e) {
                Mage::throwException(
                    Mage::helper('wallee_payment')->__('Synchronizing with wallee failed:') . ' ' .
                    $e->getMessage());
            }
        }
    }

    /**
     * Returns the model that handles dynamic payment method configs.
     *
     * @return Wallee_Payment_Model_System_Config
     */
    private function getConfigModel()
    {
        return Mage::getSingleton('wallee_payment/system_config');
    }
}