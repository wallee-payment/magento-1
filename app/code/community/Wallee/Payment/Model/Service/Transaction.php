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
 * This service provides functions to deal with Wallee transactions.
 */
class Wallee_Payment_Model_Service_Transaction extends Wallee_Payment_Model_Service_Abstract
{

    /**
     * Cache for quote transactions.
     *
     * @var \Wallee\Sdk\Model\Transaction[]
     */
    private static $transactionCache = array();

    /**
     * Cache for possible payment methods by quote.
     *
     * @var \Wallee\Sdk\Model\PaymentMethodConfiguration[]
     */
    private static $possiblePaymentMethodCache = array();

    /**
     * The transaction API service.
     *
     * @var \Wallee\Sdk\Service\TransactionService
     */
    private $transactionService;

    /**
     * Returns the transaction API service.
     *
     * @return \Wallee\Sdk\Service\TransactionService
     */
    protected function getTransactionService()
    {
        if ($this->transactionService == null) {
            $this->transactionService = new \Wallee\Sdk\Service\TransactionService($this->getHelper()->getApiClient());
        }

        return $this->transactionService;
    }

    /**
     * Wait for the transaction to be in one of the given states.
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $states
     * @param int $maxWaitTime
     * @return boolean
     */
    public function waitForTransactionState(Mage_Sales_Model_Order $order, array $states, $maxWaitTime = 10)
    {
        $startTime = microtime(true);
        while (true) {
            if (microtime(true) - $startTime >= $maxWaitTime) {
                return false;
            }

            /* @var Wallee_Payment_Model_Entity_TransactionInfo $transactionInfo */
            $transactionInfo = Mage::getModel('wallee_payment/entity_transactionInfo');
            $transactionInfo->loadByOrder($order);
            if (! in_array($transactionInfo->getState(), $states)) {
                return true;
            }

            sleep(2);
        }
    }

    /**
     * Returns the URL to Wallee's JavaScript library that is necessary to display the payment form.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    public function getJavaScriptUrl(Mage_Sales_Model_Quote $quote)
    {
        $transaction = $this->getTransactionByQuote($quote);
        return $this->getTransactionService()->transactionBuildJavaScriptUrlGet($transaction->getLinkedSpaceId(), $transaction->getId());
    }

    /**
     * Returns the transaction with the given id.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \Wallee\Sdk\Model\Transaction
     */
    public function getTransaction($spaceId, $transactionId)
    {
        return $this->getTransactionService()->transactionReadGet($spaceId, $transactionId);
    }

    /**
     * Returns the last failed charge attempt of the transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \Wallee\Sdk\Model\ChargeAttempt
     */
    public function getFailedChargeAttempt($spaceId, $transactionId)
    {
        $chargeAttemptService = new \Wallee\Sdk\Service\ChargeAttemptService(Mage::helper('wallee_payment')->getApiClient());
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilter::TYPE_AND);
        $filter->setChildren(
            array(
            $this->createEntityFilter('charge.transaction.id', $transactionId),
            $this->createEntityFilter('state', \Wallee\Sdk\Model\ChargeAttempt::STATE_FAILED)
            )
        );
        $query->setFilter($filter);
        $query->setOrderBys(
            array(
            $this->createEntityOrderBy('failedOn')
            )
        );
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->chargeAttemptSearchPost($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Updates the line items of the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \Wallee\Sdk\Model\LineItem[] $lineItems
     * @return \Wallee\Sdk\Model\TransactionLineItemVersion
     */
    public function updateLineItems($spaceId, $transactionId, $lineItems)
    {
        $updateRequest = new \Wallee\Sdk\Model\TransactionLineItemUpdateRequest();
        $updateRequest->setTransactionId($transactionId);
        $updateRequest->setNewLineItems($lineItems);
        return $this->getTransactionService()->transactionUpdateTransactionLineItemsPost($spaceId, $updateRequest);
    }

    /**
     * Stores the transaction data in the database.
     *
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @param Mage_Sales_Model_Order $order
     * @return Wallee_Payment_Model_Entity_TransactionInfo
     */
    public function updateTransactionInfo(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        /* @var Wallee_Payment_Model_Entity_TransactionInfo $info */
        $info = Mage::getModel('wallee_payment/entity_transactionInfo')->loadByTransaction($transaction->getLinkedSpaceId(), $transaction->getId());
        $info->setTransactionId($transaction->getId());
        $info->setAuthorizationAmount($transaction->getAuthorizationAmount());
        $info->setOrderId($order->getId());
        $info->setState($transaction->getState());
        $info->setSpaceId($transaction->getLinkedSpaceId());
        $info->setSpaceViewId($transaction->getSpaceViewId());
        $info->setLanguage($transaction->getLanguage());
        $info->setCurrency($transaction->getCurrency());
        $info->setConnectorId(
            $transaction->getPaymentConnectorConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
            ->getConnector() : null
        );
        $info->setPaymentMethodId(
            $transaction->getPaymentConnectorConfiguration() != null && $transaction->getPaymentConnectorConfiguration()
            ->getPaymentMethodConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
            ->getPaymentMethodConfiguration()
            ->getPaymentMethod() : null
        );
        $info->setImage($this->getPaymentMethodImage($transaction, $order));
        $info->setLabels($this->getTransactionLabels($transaction));
        if ($transaction->getState() == \Wallee\Sdk\Model\Transaction::STATE_FAILED || $transaction->getState() == \Wallee\Sdk\Model\Transaction::STATE_DECLINE) {
            $failedChargeAttempt = $this->getFailedChargeAttempt($transaction->getLinkedSpaceId(), $transaction->getId());
            if ($failedChargeAttempt != null && $failedChargeAttempt->getFailureReason() != null) {
                $info->setFailureReason(
                    $failedChargeAttempt->getFailureReason()
                    ->getDescription()
                );
            }
        }

        $info->save();
        return $info;
    }

    /**
     * Returns an array of the transaction's labels.
     *
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @return string[]
     */
    protected function getTransactionLabels(\Wallee\Sdk\Model\Transaction $transaction)
    {
        $chargeAttempt = $this->getChargeAttempt($transaction);
        if ($chargeAttempt != null) {
            $labels = array();
            foreach ($chargeAttempt->getLabels() as $label) {
                $labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
            }

            return $labels;
        } else {
            return array();
        }
    }

    /**
     * Returns the successful charge attempt of the transaction.
     *
     * @return \Wallee\Sdk\Model\ChargeAttempt
     */
    protected function getChargeAttempt(\Wallee\Sdk\Model\Transaction $transaction)
    {
        $chargeAttemptService = new \Wallee\Sdk\Service\ChargeAttemptService(Mage::helper('wallee_payment')->getApiClient());
        $query = new \Wallee\Sdk\Model\EntityQuery();
        $filter = new \Wallee\Sdk\Model\EntityQueryFilter();
        $filter->setType(\Wallee\Sdk\Model\EntityQueryFilter::TYPE_AND);
        $filter->setChildren(
            array(
            $this->createEntityFilter('charge.transaction.id', $transaction->getId()),
            $this->createEntityFilter('state', \Wallee\Sdk\Model\ChargeAttempt::STATE_SUCCESSFUL)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->chargeAttemptSearchPost($transaction->getLinkedSpaceId(), $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Returns the payment method's image.
     *
     * @param \Wallee\Sdk\Model\Transaction $transaction
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    protected function getPaymentMethodImage(\Wallee\Sdk\Model\Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        if ($transaction->getPaymentConnectorConfiguration() == null) {
            return $order->getPayment()
                ->getMethodInstance()
                ->getPaymentMethodConfiguration()
                ->getImage();
        }

        /* @var Wallee_Payment_Model_Provider_PaymentConnector $connectorProvider */
        $connectorProvider = Mage::getSingleton('wallee_payment/provider_paymentConnector');
        $connector = $connectorProvider->find(
            $transaction->getPaymentConnectorConfiguration()
            ->getConnector()
        );

        /* @var Wallee_Payment_Model_Provider_PaymentMethod $methodProvider */
        $methodProvider = Mage::getSingleton('wallee_payment/provider_paymentMethod');
        $method = $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null ? $methodProvider->find(
            $transaction->getPaymentConnectorConfiguration()
            ->getPaymentMethodConfiguration()
            ->getPaymentMethod()
        ) : null;

        if ($connector != null && $connector->getPaymentMethodBrand() != null) {
            return $connector->getPaymentMethodBrand()->getImagePath();
        } elseif ($transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null && $transaction->getPaymentConnectorConfiguration()
            ->getPaymentMethodConfiguration()
            ->getImageResourcePath() != null) {
            return $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getImageResourcePath()
                ->getPath();
        } elseif ($method != null) {
            return $method->getImagePath();
        } else {
            return $order->getPayment()
                ->getMethodInstance()
                ->getPaymentMethodConfiguration()
                ->getImage();
        }
    }

    /**
     * Returns the payment methods that can be used with the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \Wallee\Sdk\Model\PaymentMethodConfiguration[]
     */
    public function getPossiblePaymentMethods(Mage_Sales_Model_Quote $quote)
    {
        if (! isset(self::$possiblePaymentMethodCache[$quote->getId()]) || self::$possiblePaymentMethodCache[$quote->getId()] == null) {
            $transaction = $this->getTransactionByQuote($quote);
            $paymentMethods = $this->getTransactionService()->transactionFetchPossiblePaymentMethodsGet($transaction->getLinkedSpaceId(), $transaction->getId());

            /* @var Wallee_Payment_Model_Service_PaymentMethodConfiguration $paymentMethodConfigurationService */
            $paymentMethodConfigurationService = Mage::getSingleton('wallee_payment/service_paymentMethodConfiguration');
            foreach ($paymentMethods as $paymentMethod) {
                $paymentMethodConfigurationService->updateData($paymentMethod);
            }

            self::$possiblePaymentMethodCache[$quote->getId()] = $paymentMethods;
        }

        return self::$possiblePaymentMethodCache[$quote->getId()];
    }

    /**
     * Update the transaction with the given order's data.
     *
     * @param int $transactionId
     * @param int $spaceId
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param bool $chargeFlow
     * @return \Wallee\Sdk\Model\Transaction
     */
    public function updateTransaction($transactionId, $spaceId, Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Invoice $invoice, $chargeFlow = false, \Wallee\Sdk\Model\Token $token = null)
    {
        $transaction = $this->getTransactionService()->transactionReadGet($spaceId, $transactionId);
        if (!($transaction instanceof \Wallee\Sdk\Model\Transaction) || $transaction->getState() != \Wallee\Sdk\Model\Transaction::STATE_PENDING) {
            return $this->createTransactionByOrder($order);
        }

        $pendingTransaction = new \Wallee\Sdk\Model\TransactionPending();
        $pendingTransaction->setId($transaction->getId());
        $pendingTransaction->setVersion($transaction->getVersion());
        $this->assembleOrderTransactionData($order, $invoice, $pendingTransaction, $chargeFlow);
        if ($token != null) {
            $pendingTransaction->setToken($token);
        }

        return $this->getTransactionService()->transactionUpdatePost($spaceId, $pendingTransaction);
    }

    /**
     * Creates a transaction for the given order.
     *
     * @param int $spaceId
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @return \Wallee\Sdk\Model\TransactionCreate
     */
    protected function createTransactionByOrder($spaceId, Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Invoice $invoice, $chargeFlow = false, \Wallee\Sdk\Model\Token $token = null)
    {
        $createTransaction = new \Wallee\Sdk\Model\TransactionCreate();
        $createTransaction->setCustomersPresence(\Wallee\Sdk\Model\Transaction::CUSTOMERS_PRESENCE_VIRTUAL_PRESENT);
        $this->assembleOrderTransactionData($order, $invoice, $createTransaction, $chargeFlow);
        if ($token != null) {
            $createTransaction->setToken($token);
        }

        $transaction = $this->getTransactionService()->transactionCreatePost($spaceId, $createTransaction);
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        $quote->setWalleeSpaceId($transaction->getLinkedSpaceId());
        $quote->setWalleeTransactionId($transaction->getId());
        $quote->save();
        return $transaction;
    }

    /**
     * Assemble the transaction data for the given order and invoice.
     *
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param \Wallee\Sdk\Model\TransactionPending $transaction
     * @param bool $chargeFlow
     */
    protected function assembleOrderTransactionData(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Invoice $invoice, \Wallee\Sdk\Model\TransactionPending $transaction, $chargeFlow = false)
    {
        $transaction->setCurrency($order->getOrderCurrencyCode());
        $transaction->setBillingAddress($this->getOrderBillingAddress($order));
        $transaction->setShippingAddress($this->getOrderShippingAddress($order));
        $transaction->setCustomerEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        $transaction->setCustomerId($order->getCustomerId());
        $transaction->setLanguage(
            $order->getStore()
            ->getConfig('general/locale/code')
        );
        if ($order->getShippingAddress()) {
            $transaction->setShippingMethod(
                $this->fixLength(
                    $order->getShippingAddress()
                    ->getShippingDescription(), 200
                )
            );
        }

        $transaction->setSpaceViewId(
            $order->getStore()
            ->getConfig('wallee_payment/general/store_view_id')
        );
        /* @var Wallee_Payment_Model_Service_LineItem $lineItems */
        $lineItems = Mage::getSingleton('wallee_payment/service_lineItem');
        $transaction->setLineItems($lineItems->collectLineItems($order));
        $transaction->setMerchantReference($order->getIncrementId());
        $transaction->setInvoiceMerchantReference($invoice->getIncrementId());
        if ($chargeFlow) {
            $transaction->setAllowedPaymentMethodConfigurations(
                array(
                $order->getPayment()
                    ->getMethodInstance()
                    ->getPaymentMethodConfiguration()
                    ->getConfigurationId()
                )
            );
        } else {
            $transaction->setSuccessUrl(
                Mage::getUrl(
                    'wallee/transaction/success', array(
                    '_secure' => true,
                    'order_id' => $order->getId(),
                    'secret' => $this->getHelper()
                    ->hash($order->getId())
                    )
                )
            );
            $transaction->setFailedUrl(
                Mage::getUrl(
                    'wallee/transaction/failure', array(
                    '_secure' => true,
                    'order_id' => $order->getId(),
                    'secret' => $this->getHelper()
                    ->hash($order->getId())
                    )
                )
            );
        }
    }

    /**
     * Returns the billing address of the given order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    protected function getOrderBillingAddress(Mage_Sales_Model_Order $order)
    {
        if (! $order->getBillingAddress()) {
            return null;
        }

        $address = $this->getAddress($order->getBillingAddress());
        $address->setDateOfBirth($this->getDateOfBirth($order->getCustomerDob(), $order->getCustomerId()));
        $address->setEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        $address->setGender($this->getGender($order->getCustomerGender(), $order->getCustomerId()));
        return $address;
    }

    /**
     * Returns the shipping address of the given order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    protected function getOrderShippingAddress(Mage_Sales_Model_Order $order)
    {
        if (! $order->getShippingAddress()) {
            return null;
        }

        $address = $this->getAddress($order->getShippingAddress());
        $address->setEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        return $address;
    }

    /**
     * Returns the transaction for the given quote.
     *
     * If no transaction exists, a new one is created.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \Wallee\Sdk\Model\Transaction
     */
    public function getTransactionByQuote(Mage_Sales_Model_Quote $quote)
    {
        if (! isset(self::$transactionCache[$quote->getId()]) || self::$transactionCache[$quote->getId()] == null) {
            if ($quote->getWalleeTransactionId() == null) {
                $transaction = $this->createTransactionByQuote($quote);
            } else {
                $transaction = $this->loadAndUpdateTransaction($quote);
            }

            self::$transactionCache[$quote->getId()] = $transaction;
        }

        return self::$transactionCache[$quote->getId()];
    }

    /**
     * Creates a transaction for the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \Wallee\Sdk\Model\TransactionCreate
     */
    protected function createTransactionByQuote(Mage_Sales_Model_Quote $quote)
    {
        $spaceId = $quote->getStore()->getConfig('wallee_payment/general/space_id');
        $createTransaction = new \Wallee\Sdk\Model\TransactionCreate();
        $createTransaction->setCustomersPresence(\Wallee\Sdk\Model\Transaction::CUSTOMERS_PRESENCE_VIRTUAL_PRESENT);
        $this->assembleQuoteTransactionData($quote, $createTransaction);
        $transaction = $this->getTransactionService()->transactionCreatePost($spaceId, $createTransaction);
        $quote->setWalleeSpaceId($transaction->getLinkedSpaceId());
        $quote->setWalleeTransactionId($transaction->getId());
        $quote->save();
        return $transaction;
    }

    /**
     * Loads the transaction for the given quote and updates it if necessary.
     *
     * If the transaction is not in pending state, a new one is created.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \Wallee\Sdk\Model\TransactionPending
     */
    protected function loadAndUpdateTransaction(Mage_Sales_Model_Quote $quote)
    {
        $transaction = $this->getTransactionService()->transactionReadGet($quote->getWalleeSpaceId(), $quote->getWalleeTransactionId());
        if (!($transaction instanceof \Wallee\Sdk\Model\Transaction) || $transaction->getState() != \Wallee\Sdk\Model\Transaction::STATE_PENDING) {
            return $this->createTransactionByQuote($quote);
        }

        $pendingTransaction = new \Wallee\Sdk\Model\TransactionPending();
        $pendingTransaction->setId($transaction->getId());
        $pendingTransaction->setVersion($transaction->getVersion());
        $this->assembleQuoteTransactionData($quote, $pendingTransaction);
        return $this->getTransactionService()->transactionUpdatePost($quote->getWalleeSpaceId(), $pendingTransaction);
    }

    /**
     * Assemble the transaction data for the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param \Wallee\Sdk\Model\TransactionPending $transaction
     */
    protected function assembleQuoteTransactionData(Mage_Sales_Model_Quote $quote, \Wallee\Sdk\Model\TransactionPending $transaction)
    {
        $transaction->setCurrency($quote->getQuoteCurrencyCode());
        $transaction->setBillingAddress($this->getQuoteBillingAddress($quote));
        $transaction->setShippingAddress($this->getQuoteShippingAddress($quote));
        $transaction->setCustomerEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        $transaction->setCustomerId($quote->getCustomerId());
        $transaction->setLanguage(
            $quote->getStore()
            ->getConfig('general/locale/code')
        );
        if ($quote->getShippingAddress()) {
            $transaction->setShippingMethod(
                $this->fixLength(
                    $quote->getShippingAddress()
                    ->getShippingDescription(), 200
                )
            );
        }

        $transaction->setSpaceViewId(
            $quote->getStore()
            ->getConfig('wallee_payment/general/store_view_id')
        );
        /* @var Wallee_Payment_Model_Service_LineItem $lineItems */
        $lineItems = Mage::getSingleton('wallee_payment/service_lineItem');
        $transaction->setLineItems($lineItems->collectLineItems($quote));
        $transaction->setAllowedPaymentMethodConfigurations(array());
    }

    /**
     * Returns the billing address of the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    protected function getQuoteBillingAddress(Mage_Sales_Model_Quote $quote)
    {
        if (! $quote->getBillingAddress()) {
            return null;
        }

        $address = $this->getAddress($quote->getBillingAddress());
        $address->setDateOfBirth($this->getDateOfBirth($quote->getCustomerDob(), $quote->getCustomerId()));
        $address->setEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        $address->setGender($this->getGender($quote->getCustomerGender(), $quote->getCustomerId()));
        $address->setSalesTaxNumber($this->getTaxNumber($quote->getCustomerTaxvat(), $quote->getCustomerId()));
        return $address;
    }

    /**
     * Returns the shipping address of the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    protected function getQuoteShippingAddress(Mage_Sales_Model_Quote $quote)
    {
        if (! $quote->getShippingAddress()) {
            return null;
        }

        $address = $this->getAddress($quote->getShippingAddress());
        $address->setEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        return $address;
    }

    /**
     * Returns the customer's email address.
     *
     * @param string $customerEmailAddress
     * @param int $customerId
     * @return string
     */
    protected function getCustomerEmailAddress($customerEmailAddress, $customerId)
    {
        if ($customerEmailAddress != null) {
            return $customerEmailAddress;
        } else {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $customerMail = $customer->getEmail();
            if (! empty($customerMail)) {
                return $customerMail;
            } else {
                return null;
            }
        }
    }

    /**
     * Returns the customer's gender.
     *
     * @param string $gender
     * @param int $customerId
     * @return string
     */
    protected function getGender($gender, $customerId)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if ($gender !== null) {
            $gender = $customer->getAttribute('gender')
                ->getSource()
                ->getOptionText($gender);
            return strtoupper($gender);
        }

        if ($customer->getGender() !== null) {
            $gender = $customer->getAttribute('gender')
                ->getSource()
                ->getOptionText($customer->getGender());
            return strtoupper($gender);
        }
    }

    /**
     * Returns the customer's date of birth.
     *
     * @param string $customerDob
     * @param int $customerId
     * @return string
     */
    protected function getDateOfBirth($dateOfBirth, $customerId)
    {
        if ($dateOfBirth === null) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $dateOfBirth = $customer->getDob();
        }

        if ($dateOfBirth !== null) {
            return DateTime::createFromFormat('Y-m-d H:i:s', $dateOfBirth)->format(DateTime::W3C);
        }
    }

    /**
     * Returns the customer's tax number.
     *
     * @param string $taxNumber
     * @param int $customerId
     * @return string
     */
    protected function getTaxNumber($taxNumber, $customerId)
    {
        if ($taxNumber !== null) {
            return $taxNumber;
        }

        $customer = Mage::getModel('customer/customer')->load($customerId);
        return $customer->getTaxvat();
    }

    /**
     * Converts the Magento address model to a Wallee API address model.
     *
     * @param Mage_Customer_Model_Address_Abstract $customerAddress
     * @return \Wallee\Sdk\Model\AddressCreate
     */
    protected function getAddress(Mage_Customer_Model_Address_Abstract $customerAddress)
    {
        $address = new \Wallee\Sdk\Model\AddressCreate();
        $address->setSalutation($this->fixLength($customerAddress->getPrefix(), 20));
        $address->setCity($this->fixLength($customerAddress->getCity(), 100));
        $address->setCountry($customerAddress->getCountryId());
        $address->setFamilyName($this->fixLength($customerAddress->getLastname(), 100));
        $address->setGivenName($this->fixLength($customerAddress->getFirstname(), 100));
        $address->setOrganizationName($this->fixLength($customerAddress->getCompany(), 100));
        $address->setPhoneNumber($customerAddress->getTelephone());
        $address->setPostalState($customerAddress->getRegionCode());
        $address->setPostCode($this->fixLength($customerAddress->getPostcode(), 40));
        $address->setStreet($this->fixLength($customerAddress->getStreetFull(), 300));
        return $address;
    }
}