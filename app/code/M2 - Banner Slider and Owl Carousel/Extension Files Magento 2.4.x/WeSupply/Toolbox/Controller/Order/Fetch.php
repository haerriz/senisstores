<?php
namespace WeSupply\Toolbox\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use WeSupply\Toolbox\Api\OrderRepositoryInterface;
use WeSupply\Toolbox\Helper\Data as WeSupplyHelper;
use WeSupply\Toolbox\Model\ResourceModel\Order\Collection as WsOrderCollection;

class Fetch extends Action
{

    const  ALL_STORES = 'all';

    const MULTIPLE_STORE_ID_DELIMITER = ',';
    /**
     * maximum response xml file size allowed - expressed in MB
     */
    const MAX_FILE_SIZE_ALLOWED = '30';

    const ORDERS_BATCH_SIZE = 1000;

    /**
     * @var string
     */
    protected $guid;

    /**
     * @var string
     */
    protected $startDate;

    /**
     * @var string
     */
    protected $endDate;

    /**
     * @var string
     */
    protected $storeIds;

    /**
     * @var WeSupplyHelper
     */
    protected $helper;


    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var WsOrderCollection
     */
    protected $wsOrderCollection;

    /**
     * Fetch constructor.
     * @param Context $context
     * @param WeSupplyHelper $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param WsOrderCollection $wsOrderCollection
     */
    public function __construct(
        Context $context,
        WeSupplyHelper $helper,
        OrderRepositoryInterface $orderRepository,
        StoreManagerInterface $storeManager,
        WsOrderCollection $wsOrderCollection
    )
    {
        parent::__construct($context);
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->wsOrderCollection = $wsOrderCollection;
    }

    public function execute()
    {
        $response = '';
        $params = $this->getRequest()->getParams();
        $validationError = $this->_validateParams($params);

        if ($validationError) {
            /** Add the error response */
            $errorMessage = '';
            foreach ($validationError as $error) {
                $errorMessage .= $error . ' ';
            }

            $response .= $this->addResponseStatus('true', 'ERROR', trim($errorMessage));
        } else {
            /** Get the orders from the required interval */
            try {
                $xmlResponse = $this->fetchOrders();
            } catch (LocalizedException $e) {
                $xmlResponse = [
                    'error' => 504,
                    'message' => $e->getMessage()
                ];
            }

            if (is_array($xmlResponse) && array_key_exists('error', $xmlResponse)) {
                $response .= $this->addResponseStatus('true', 'ERROR', $xmlResponse['message'] ?? 'General error occurred.');
            } else {
                $response .= $xmlResponse;
            }

            $response .= $this->addResponseStatus('false', 'SUCCESS', '');
        }

        $response = '<Orders>' . $response . '</Orders>';
        $xml = simplexml_load_string($response);  // Might be ignored this and just send the $response as result

        $this->getResponse()->setHeader('Content-type', 'text/xml; charset=utf-8');
        $this->getResponse()->setBody($xml->asXML());
    }

    /**
     * @return array|string
     * @throws LocalizedException
     */
    protected function fetchOrders()
    {
        $ordersXml = '';
        $startDate = date('Y-m-d H:i:s', strtotime($this->startDate));
        $endDate = date('Y-m-d H:i:s', strtotime($this->endDate));

        $wsOrders = $this->wsOrderCollection
            ->addFieldToFilter('updated_at', ['gteq' => $startDate])
            ->addFieldToFilter('updated_at', ['lteq' => $endDate])
            ->addFieldToFilter('is_excluded', ['neg' => 0])
            ->setOrder('updated_at', 'asc');

        /**
         * if storeId param has the all stores value, we are not filtering based on store id
         */
        if ($this->storeIds <> self::ALL_STORES) {
            $storeIds = array_filter(explode(self::MULTIPLE_STORE_ID_DELIMITER, $this->storeIds));
            $wsOrders->addFieldToFilter('store_id', ['in' => $storeIds]);
        }

        $wsOrders->setPageSize(self::ORDERS_BATCH_SIZE);
        $batches = $wsOrders->getLastPageNumber();
        $currentBatch = 1;

        do {
            $wsOrders->setCurPage($currentBatch);
            $wsOrders->load();

            if (count($wsOrders)) {
                foreach($wsOrders as $item) {
                    $orderXml = $item->getInfo();
                    $ordersXml .= $orderXml;
                    /**
                     * extra check for the rare cases where massive xml file sizes are created
                     */
                    $xmlFileSizeBit = $this->helper->strbits($ordersXml);
                    $xmlFileSize = $this->helper->formatSizeUnits($xmlFileSizeBit);
                    if($xmlFileSize >= self::MAX_FILE_SIZE_ALLOWED ) {
                        return array('error' => 504,
                            'message' => 'XML File Size exceeds '.self::MAX_FILE_SIZE_ALLOWED
                        );
                    }
                }
            }

            $currentBatch +=1;
            $wsOrders->clear();

        } while ($currentBatch <= $batches);

        return $ordersXml;
    }



    /**
     * @param string $hasError
     * @param string $errorCode
     * @param string $errorDescription
     * @return string
     */
    protected function addResponseStatus($hasError, $errorCode, $errorDescription)
    {
        return "<Response>" .
            "<ResponseHasErrors>$hasError</ResponseHasErrors>" .
            "<ResponseCode>$errorCode</ResponseCode>" .
            "<ResponseDescription>$errorDescription</ResponseDescription>"
            . "</Response>";
    }

    /**
     * @param $params
     * @return array|bool
     */
    private function _validateParams($params)
    {
        $errors = [];
        $guid = $params['guid'] ?? false;
        if (!$guid) {
            $errors[] = 'Access Key is required.';
        }

        $startDate = $params['DateStart'] ?? false;
        if (!$startDate) {
            $errors[] = 'DateStart is a required field.';
        }

        $endDate = $params['DateEnd'] ?? false;
        if (!$endDate) {
            $errors[] = 'DateEnd is a required field.';
        }

        $storeIds = isset($params['AffiliateExternalId']) ?
            explode(self::MULTIPLE_STORE_ID_DELIMITER, $params['AffiliateExternalId']) : [];

        $invalidStoreIds = $this->_validateStoreIds($storeIds);
        if (!empty($invalidStoreIds)) {
            return array_merge($errors, $invalidStoreIds);
        }

        $validGuid = $this->helper->validateGuidByStoreIds($storeIds, $guid);
        if (TRUE === $validGuid['errors']) {
            return array_merge($errors, [$validGuid['errMessage']]);
        }

        $this->storeIds = implode(self::MULTIPLE_STORE_ID_DELIMITER, $storeIds);
        $this->guid = $guid;
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        return false;
    }

    /**
     * @param array $storeIds
     * @return array
     */
    private function _validateStoreIds($storeIds)
    {
        if (empty($storeIds)) {
            return ['Store Id is a required field.'];
        }

        // remove store ID "all" if multiple store IDs provided
        if (count($storeIds) > 1 && in_array('all', $storeIds)) {
            unset($storeIds[array_search('all', $storeIds)]);
        }

        // no need to validate store ids for sync all stores
        if (in_array('all', $storeIds)) {
            return [];
        }

        // check if given store IDs exists
        $storeListIds = [];
        $storeList = $this->storeManager->getStores();
        foreach ($storeList as $store) {
            $storeListIds[] = $store->getId();
        }

        $errors = [];
        $notExists = array_diff($storeIds, $storeListIds);
        if (!empty($notExists)) {
            $errors[] = 'Store View ID(s): ' . implode(', ', $notExists) . ' does not exist!';
        }

        return $errors;
    }
}
