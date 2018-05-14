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
 * This service provides functions to deal with wallee tokens.
 */
class Wallee_Payment_Model_Service_Token extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * The token API service.
     *
     * @var \Wallee\Sdk\Service\TokenService
     */
    private $tokenService;

    /**
     * The token version API service.
     *
     * @var \Wallee\Sdk\Service\TokenVersionService
     */
    private $tokenVersionService;

    public function updateTokenVersion($spaceId, $tokenVersionId)
    {
        $tokenVersion = $this->getTokenVersionService()->read($spaceId, $tokenVersionId);
        $this->updateInfo($spaceId, $tokenVersion);
    }

    public function updateToken($spaceId, $tokenId)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
            $this->createEntityFilter('token.id', $tokenId),
            $this->createEntityFilter('state', \Wallee\Sdk\Model\TokenVersionState::ACTIVE)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $tokenVersion = $this->getTokenVersionService()->search($spaceId, $query);
        if (! empty($tokenVersion)) {
            $this->updateInfo($spaceId, current($tokenVersion));
        } else {
            /* @var Wallee_Payment_Model_Entity_TokenInfo $info */
            $info = Mage::getModel('wallee_payment/entity_tokenInfo')->loadByToken($spaceId, $tokenId);
            if ($info->getId()) {
                $info->delete();
            }
        }
    }

    protected function updateInfo($spaceId, \Wallee\Sdk\Model\TokenVersion $tokenVersion)
    {
        /* @var Wallee_Payment_Model_Entity_TokenInfo $info */
        $info = Mage::getModel('wallee_payment/entity_tokenInfo')->loadByToken(
            $spaceId, $tokenVersion->getToken()
            ->getId()
        );

        if (! in_array(
            $tokenVersion->getToken()->getState(), array(
            \Wallee\Sdk\Model\CreationEntityState::ACTIVE,
            \Wallee\Sdk\Model\CreationEntityState::INACTIVE
            )
        )) {
            if ($info->getId()) {
                $info->delete();
            }

            return;
        }

        $info->setCustomerId(
            $tokenVersion->getToken()
            ->getCustomerId()
        );
        $info->setName($tokenVersion->getName());

        /* @var Wallee_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod */
        $paymentMethod = Mage::getModel('wallee_payment/entity_paymentMethodConfiguration')->loadByConfigurationId(
            $spaceId, $tokenVersion->getPaymentConnectorConfiguration()
            ->getPaymentMethodConfiguration()
            ->getId()
        );
        $info->setPaymentMethodId($paymentMethod->getId());
        $info->setConnectorId(
            $tokenVersion->getPaymentConnectorConfiguration()
            ->getConnector()
        );

        $info->setSpaceId($spaceId);
        $info->setState(
            $tokenVersion->getToken()
            ->getState()
        );
        $info->setTokenId(
            $tokenVersion->getToken()
            ->getId()
        );
        $info->save();
    }

    public function deleteToken($spaceId, $tokenId)
    {
        $this->getTokenService()->delete($spaceId, $tokenId);
    }

    /**
     * Returns the token API service.
     *
     * @return \Wallee\Sdk\Service\TokenService
     */
    protected function getTokenService()
    {
        if ($this->tokenService == null) {
            $this->tokenService = new \Wallee\Sdk\Service\TokenService($this->getHelper()->getApiClient());
        }

        return $this->tokenService;
    }

    /**
     * Returns the token version API service.
     *
     * @return \Wallee\Sdk\Service\TokenVersionService
     */
    protected function getTokenVersionService()
    {
        if ($this->tokenVersionService == null) {
            $this->tokenVersionService = new \Wallee\Sdk\Service\TokenVersionService($this->getHelper()->getApiClient());
        }

        return $this->tokenVersionService;
    }
}