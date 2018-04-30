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
 * This controller accepts webhook requests from wallee and redirects them to the suitable processor.
 */
class Wallee_Payment_WebhookController extends Mage_Core_Controller_Front_Action
{

    /**
     * Accepts webhook requests from wallee and redirects them to the suitable processor.
     */
    public function indexAction()
    {
        $this->getResponse()->setHttpResponseCode(500);
        $request = new Wallee_Payment_Model_Webhook_Request(json_decode($this->getRequest()->getRawBody()));
        Mage::dispatchEvent(
            'wallee_payment_webhook_' . strtolower($request->getListenerEntityTechnicalName()), array(
            'request' => $request
            )
        );
        $this->getResponse()->setHttpResponseCode(200);
    }
}