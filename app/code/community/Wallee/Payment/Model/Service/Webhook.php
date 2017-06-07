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
 * This service handles webhooks.
 */
class Wallee_Payment_Model_Service_Webhook extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * The webhook listener API service.
     *
     * @var \Wallee\Sdk\Service\WebhookListenerService
     */
    private $webhookListenerService;

    /**
     * The webhook url API service.
     *
     * @var \Wallee\Sdk\Service\WebhookUrlService
     */
    private $webhookUrlService;

    private $webhookEntities = array();

    /**
     * Constructor to register the webhook entites.
     */
    public function __construct()
    {
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1487165678181, 'Manual Task', array(
            \Wallee\Sdk\Model\ManualTask::STATE_DONE,
            \Wallee\Sdk\Model\ManualTask::STATE_EXPIRED,
            \Wallee\Sdk\Model\ManualTask::STATE_OPEN
            )
        );
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1472041857405, 'Payment Method Configuration', array(
            \Wallee\Sdk\Model\PaymentMethodConfiguration::STATE_ACTIVE,
            \Wallee\Sdk\Model\PaymentMethodConfiguration::STATE_DELETED,
            \Wallee\Sdk\Model\PaymentMethodConfiguration::STATE_DELETING,
            \Wallee\Sdk\Model\PaymentMethodConfiguration::STATE_INACTIVE
            ), true
        );
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1472041829003, 'Transaction', array(
            \Wallee\Sdk\Model\Transaction::STATE_AUTHORIZED,
            \Wallee\Sdk\Model\Transaction::STATE_DECLINE,
            \Wallee\Sdk\Model\Transaction::STATE_FAILED,
            \Wallee\Sdk\Model\Transaction::STATE_FULFILL,
            \Wallee\Sdk\Model\Transaction::STATE_VOIDED,
            \Wallee\Sdk\Model\Transaction::STATE_COMPLETED,
            \Wallee\Sdk\Model\Transaction::STATE_PROCESSING,
            \Wallee\Sdk\Model\Transaction::STATE_CONFIRMED
            )
        );
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1472041819799, 'Delivery Indication', array(
            \Wallee\Sdk\Model\DeliveryIndication::STATE_MANUAL_CHECK_REQUIRED
            )
        );
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1472041816898, 'Transaction Invoice', array(
            \Wallee\Sdk\Model\TransactionInvoice::STATE_NOT_APPLICABLE,
            \Wallee\Sdk\Model\TransactionInvoice::STATE_PAID,
            \Wallee\Sdk\Model\TransactionInvoice::STATE_DERECOGNIZED
            )
        );
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1472041831364, 'Transaction Completion', array(
            \Wallee\Sdk\Model\TransactionCompletion::STATE_FAILED
            )
        );
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1472041839405, 'Refund', array(
            \Wallee\Sdk\Model\Refund::STATE_FAILED,
            \Wallee\Sdk\Model\Refund::STATE_SUCCESSFUL
            )
        );
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1472041806455, 'Token', array(
            \Wallee\Sdk\Model\Token::STATE_ACTIVE,
            \Wallee\Sdk\Model\Token::STATE_DELETED,
            \Wallee\Sdk\Model\Token::STATE_DELETING,
            \Wallee\Sdk\Model\Token::STATE_INACTIVE
            )
        );
        $this->webhookEntities[] = new Wallee_Payment_Model_Webhook_Entity(
            1472041811051, 'Token Version', array(
            \Wallee\Sdk\Model\TokenVersion::STATE_ACTIVE,
            \Wallee\Sdk\Model\TokenVersion::STATE_OBSOLETE
            )
        );
    }

    /**
     * Installs the necessary webhooks in Wallee.
     */
    public function install()
    {
        $spaceIds = array();
        foreach (Mage::app()->getWebsites() as $website) {
            $spaceId = $website->getConfig('wallee_payment/general/space_id');
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $webhookUrl = $this->getWebhookUrl($spaceId);
                if ($webhookUrl == null) {
                    $webhookUrl = $this->createWebhookUrl($spaceId);
                }

                $existingListeners = $this->getWebhookListeners($spaceId, $webhookUrl);
                foreach ($this->webhookEntities as $webhookEntity) {
                    /* @var Wallee_Payment_Model_Webhook_Entity $webhookEntity */
                    $exists = false;
                    foreach ($existingListeners as $existingListener) {
                        if ($existingListener->getEntity() == $webhookEntity->getId()) {
                            $exists = true;
                        }
                    }

                    if (! $exists) {
                        $this->createWebhookListener($webhookEntity, $spaceId, $webhookUrl);
                    }
                }

                $spaceIds[] = $spaceId;
            }
        }
    }

    /**
     * Create a webhook listener.
     *
     * @param Wallee_Payment_Model_Webhook_Entity $entity
     * @param int $spaceId
     * @param \Wallee\Sdk\Model\WebhookUrl $webhookUrl
     * @return \Wallee\Sdk\Model\WebhookListenerCreate
     */
    protected function createWebhookListener(Wallee_Payment_Model_Webhook_Entity $entity, $spaceId, \Wallee\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $webhookListener = new \Wallee\Sdk\Model\WebhookListenerCreate();
        $webhookListener->setEntity($entity->getId());
        $webhookListener->setEntityStates($entity->getStates());
        $webhookListener->setLinkedSpaceId($spaceId);
        $webhookListener->setName('Magento ' . $entity->getName());
        $webhookListener->setState(\Wallee\Sdk\Model\WebhookListenerCreate::STATE_ACTIVE);
        $webhookListener->setUrl($webhookUrl->getId());
        $webhookListener->setNotifyEveryChange($entity->isNotifyEveryChange());
        return $this->getWebhookListenerService()->create($spaceId, $webhookListener);
    }

    /**
     * Returns the existing webhook listeners.
     *
     * @param int $spaceId
     * @param \Wallee\Sdk\Model\WebhookUrl $webhookUrl
     * @return \Wallee\Sdk\Model\WebhookListener[]
     */
    protected function getWebhookListeners($spaceId, \Wallee\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilter::TYPE_AND);
        $filter->setChildren(
            array(
            $this->createEntityFilter('state', \Wallee\Sdk\Model\WebhookListener::STATE_ACTIVE),
            $this->createEntityFilter('url.id', $webhookUrl->getId())
            )
        );
        $query->setFilter($filter);
        return $this->getWebhookListenerService()->search($spaceId, $query);
    }

    /**
     * Creates a webhook url.
     *
     * @param int $spaceId
     * @return \Wallee\Sdk\Model\WebhookUrlCreate
     */
    protected function createWebhookUrl($spaceId)
    {
        $webhookUrl = new \Wallee\Sdk\Model\WebhookUrlCreate();
        $webhookUrl->setLinkedSpaceId($spaceId);
        $webhookUrl->setUrl($this->getUrl());
        $webhookUrl->setState(\Wallee\Sdk\Model\WebhookUrlCreate::STATE_ACTIVE);
        $webhookUrl->setName('Magento');
        return $this->getWebhookUrlService()->create($spaceId, $webhookUrl);
    }

    /**
     * Returns the existing webhook url if there is one.
     *
     * @param int $spaceId
     * @return \Wallee\Sdk\Model\WebhookUrl
     */
    protected function getWebhookUrl($spaceId)
    {
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $query->setNumberOfEntities(1);
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilter::TYPE_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \Wallee\Sdk\Model\WebhookUrl::STATE_ACTIVE),
                $this->createEntityFilter('url', $this->getUrl())
            )
            );
        $query->setFilter($filter);
        $result = $this->getWebhookUrlService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Returns the webhook endpoint URL.
     *
     * @return string
     */
    protected function getUrl()
    {
        return Mage::getUrl(
            'wallee/webhook', array(
            '_secure' => true,
            '_store' => Mage::app()->getDefaultStoreView()->getId()
            )
        );
    }

    /**
     * Returns the webhook listener API service.
     *
     * @return \Wallee\Sdk\Service\WebhookListenerService
     */
    protected function getWebhookListenerService()
    {
        if ($this->webhookListenerService == null) {
            $this->webhookListenerService = new \Wallee\Sdk\Service\WebhookListenerService(Mage::helper('wallee_payment')->getApiClient());
        }

        return $this->webhookListenerService;
    }

    /**
     * Returns the webhook url API service.
     *
     * @return \Wallee\Sdk\Service\WebhookUrlService
     */
    protected function getWebhookUrlService()
    {
        if ($this->webhookUrlService == null) {
            $this->webhookUrlService = new \Wallee\Sdk\Service\WebhookUrlService(Mage::helper('wallee_payment')->getApiClient());
        }

        return $this->webhookUrlService;
    }
}