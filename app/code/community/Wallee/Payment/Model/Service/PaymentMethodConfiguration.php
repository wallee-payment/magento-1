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

class Wallee_Payment_Model_Service_PaymentMethodConfiguration extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * Updates the data of the payment method configuration.
     *
     * @param \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration
     */
    public function updateData(\Wallee\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        /* @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration $model */
        $model = Mage::getModel('wallee_payment/entity_paymentMethodConfiguration');
        $model->loadByConfigurationId($configuration->getLinkedSpaceId(), $configuration->getId());
        if ($model->getId()) {
            $model->setConfigurationName($configuration->getName());
            $model->setTitle($this->getTranslationsArray($configuration->getTitle()));
            $model->setDescription($this->getTranslationsArray($configuration->getDescription()));
            $model->setImage(
                $configuration->getImageResourcePath() != null ? $configuration->getImageResourcePath()
                ->getPath() : $this->getPaymentMethod($configuration->getPaymentMethod())
                ->getImagePath()
            );
            $model->setSortOrder($configuration->getSortOrder());
            $model->save();
        }
    }

    /**
     * Synchronizes the payment method configurations from Wallee.
     */
    public function synchronize()
    {
        /* @var Wallee_Payment_Model_Resource_PaymentMethodConfiguration_Collection $collection */
        $collection = Mage::getModel('wallee_payment/entity_paymentMethodConfiguration')->getCollection();
        $spaceIds = array();
        $existingConfigurations = $collection->getItems();
        $existingFound = array();
        foreach ($existingConfigurations as $existingConfiguration) {
            $existingConfiguration->setState(Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_HIDDEN);
        }

        foreach (Mage::app()->getWebsites() as $website) {
            $spaceId = $website->getConfig('wallee_payment/general/space_id');
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $paymentMethodConfigurationService = new \Wallee\Sdk\Service\PaymentMethodConfigurationService($this->getHelper()->getApiClient());
                $configurations = $paymentMethodConfigurationService->paymentMethodConfigurationSearchPost($spaceId, new \Wallee\Sdk\Model\EntityQuery());
                foreach ($configurations as $configuration) {
                    /* @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration $method */
                    $method = null;
                    foreach ($existingConfigurations as $existingConfiguration) {
                        /* @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration $existingPaymentMethod */
                        if ($existingConfiguration->getSpaceId() == $spaceId && $existingConfiguration->getConfigurationId() == $configuration->getId()) {
                            $method = $existingConfiguration;
                            $existingFound[] = $method->getId();
                            break;
                        }
                    }

                    if ($method == null) {
                        $method = $collection->getNewEmptyItem();
                    }

                    $method->setSpaceId($spaceId);
                    $method->setConfigurationId($configuration->getId());
                    $method->setConfigurationName($configuration->getName());
                    $method->setState($this->getConfigurationState($configuration));
                    $method->setTitle($this->getTranslationsArray($configuration->getTitle()));
                    $method->setDescription($this->getTranslationsArray($configuration->getDescription()));
                    $method->setImage(
                        $configuration->getImageResourcePath() != null ? $configuration->getImageResourcePath()
                        ->getPath() : $this->getPaymentMethod($configuration->getPaymentMethod())
                        ->getImagePath()
                    );
                    $method->setSortOrder($configuration->getSortOrder());
                    $method->save();
                }

                $spaceIds[] = $spaceId;
            }
        }

        foreach ($existingConfigurations as $existingConfiguration) {
            if (! in_array($existingConfiguration->getId(), $existingFound)) {
                $existingConfiguration->setState(Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_HIDDEN);
                $existingConfiguration->save();
            }
        }

        $this->createPaymentMethodModelClasses();
    }

    /**
     * Returns the payment method for the given id.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\PaymentMethod
     */
    private function getPaymentMethod($id)
    {
        /* @var Wallee_Payment_Model_Provider_PaymentMethod $methodProvider */
        $methodProvider = Mage::getSingleton('wallee_payment/provider_paymentMethod');
        return $methodProvider->find($id);
    }

    /**
     * Converts a DatabaseTranslatedString into a serializable array.
     *
     * @param \Wallee\Sdk\Model\DatabaseTranslatedString $translatedString
     * @return string[]
     */
    private function getTranslationsArray(\Wallee\Sdk\Model\DatabaseTranslatedString $translatedString)
    {
        $translations = array();
        foreach ($translatedString->getItems() as $item) {
            $translation = $item->getTranslation();
            if (! empty($translation)) {
                $translations[$item->getLanguage()] = $item->getTranslation();
            }
        }

        return $translations;
    }

    /**
     * Returns the state for the payment method configuration.
     *
     * @param \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration
     * @return number
     */
    private function getConfigurationState(\Wallee\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        switch ($configuration->getState()) {
            case 'ACTIVE':
                return Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_ACTIVE;
            case 'INACTIVE':
                return Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_INACTIVE;
            default:
                return Wallee_Payment_Model_Entity_PaymentMethodConfiguration::STATE_HIDDEN;
        }
    }

    /**
     * Creates the model classes for the payment methods.
     */
    private function createPaymentMethodModelClasses()
    {
        /* @var Wallee_Payment_Model_Resource_PaymentMethodConfiguration_Collection $collection */
        $collection = Mage::getModel('wallee_payment/entity_paymentMethodConfiguration')->getCollection();
        $generationDir = $this->getHelper()->getGenerationDirectoryPath() . DS . 'Wallee' . DS . 'Payment' . DS . 'Model';
        if (! file_exists($generationDir)) {
            mkdir($generationDir, 0777, true);
        }

        $classTemplate = file_get_contents(Mage::getModuleDir('', 'Wallee_Payment') . DS . 'Model' . DS . 'Payment' . DS . 'Method' . DS . 'Template.php.tpl');
        foreach ($collection->getItems() as $configuration) {
            $fileName = $generationDir . DS . 'PaymentMethod' . $configuration->getId() . '.php';
            if (! file_exists($fileName)) {
                file_put_contents(
                    $fileName, str_replace(
                        array(
                        '{id}'
                        ), array(
                        $configuration->getId()
                        ), $classTemplate
                    )
                );
            }
        }
    }
}