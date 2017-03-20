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
 * Abstract implementation of a provider.
 */
abstract class Wallee_Payment_Model_Provider_Abstract
{

    private $cacheKey;

    private $cacheTag;

    private $data;

    /**
     * Constructor.
     *
     * @param string $cacheKey
     * @param string $cacheTag
     */
    public function __construct($cacheKey, $cacheTag = 'COLLECTION_DATA')
    {
        $this->cacheKey = $cacheKey;
        $this->cacheTag = $cacheTag;
    }

    /**
     * Fetch the data from the remote server.
     *
     * @return array
     */
    abstract protected function fetchData();

    /**
     * Returns the id of the given entry.
     *
     * @param mixed $entry
     * @return string
     */
    abstract protected function getId($entry);

    /**
     * Returns a single entry by id.
     *
     * @param string $id
     * @return mixed
     */
    public function find($id)
    {
        if ($this->data == null) {
            $this->loadData();
        }

        if (isset($this->data[$id])) {
            return $this->data[$id];
        } else {
            return false;
        }
    }

    /**
     * Returns all entries.
     *
     * @return array
     */
    public function getAll()
    {
        if ($this->data == null) {
            $this->loadData();
        }

        return $this->data;
    }

    private function loadData()
    {
        $cachedData = Mage::app()->loadCache($this->cacheKey);
        if ($cachedData) {
            $this->data = unserialize($cachedData);
        } else {
            $this->data = array();
            foreach ($this->fetchData() as $entry) {
                $this->data[$this->getId($entry)] = $entry;
            }

            Mage::app()->saveCache(
                serialize($this->data), $this->cacheKey, array(
                $this->cacheTag
                )
            );
        }
    }
}