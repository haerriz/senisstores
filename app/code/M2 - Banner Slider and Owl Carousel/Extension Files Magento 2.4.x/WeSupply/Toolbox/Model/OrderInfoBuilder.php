<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Model;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\Country;
use Magento\Downloadable\Model\Link\Purchased\Item;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\Collection as PurchasedCollection;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\Collection as PurchasedItemCollection;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use WeSupply\Toolbox\Api\OrderInfoBuilderInterface;
use WeSupply\Toolbox\Helper\Data;
use WeSupply\Toolbox\Helper\WeSupplyMappings;
use WeSupply\Toolbox\Logger\Logger;

/**
 * Class OrderInfoBuilder
 * @package WeSupply\Toolbox\Model
 */
class OrderInfoBuilder implements OrderInfoBuilderInterface
{
    /**
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @var Country
     */
    protected $country;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customer;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepositoryInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var Attribute
     */
    protected $productAttr;

    /**
     * @var AttributeInterface
     */
    protected $attributeInterface;

    /**
     * @var string
     * url to media directory
     */
    protected $mediaUrl;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     * order status label
     */
    protected $wsOrderStatus;

    /**
     * @var string
     * original order status
     */
    protected $orderOrigStatusCode;

    /**
     * @var array
     */
    protected $weSupplyStatusIdMappedArray;

    /**
     * @var array
     */
    protected $weSupplyStatusMappedArray;

    /**
     * @var WeSupplyMappings
     */
    protected $weSupplyMappings;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var Data
     */
    private $_helper;

    /**
     * @var Reader
     */
    private $moduleReader;

    /**
     * @var Repository
     */
    private $assetRepos;

    /**
     * @var ImageHelper
     */
    private $imageHelper;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * Product image subdirectory
     * @var string
     */
    const PRODUCT_IMAGE_SUBDIRECTORY = 'catalog/product/';

    /**
     * Used as prefix for wesupply order id
     * to avoid duplicate id with other providers (aptos)
     * @var string
     */
    const PREFIX = 'mage_';

    /**
     * Product attributes whose value must remain as they were
     * when placing the order
     * @var array
     */
    const DO_NOT_UPDATE =
        [
            'ItemImageUri',
            'ItemProductUri',
            'OptionHidden',
            'ItemWeight',
            'ItemWidth',
            'ItemHeight',
            'ItemLength',
            'ItemWeightUnit',
            'ItemMeasureUnit'
        ];

    /**
     * @var PurchasedCollection
     */
    private $downloadableLinks;

    /**
     * @var PurchasedItemCollection
     */
    private $downloadableItemLinks;

    /**
     * @var int
     */
    private $availableDownloadableItems;

    /**
     * @var array
     */
    private $availableOrderStatuses = [];

    /**
     * @param ProductRepositoryInterface  $productRepositoryInterface
     * @param ImageHelper                 $imageHelper
     * @param CustomerRepositoryInterface $customer
     * @param Country                     $country
     * @param AttributeInterface          $attributeInterface
     * @param Attribute                   $productAttr
     * @param SearchCriteriaBuilder       $searchCriteriaBuilder
     * @param ManagerInterface            $eventManager
     * @param Filesystem                  $filesystem
     * @param Reader                      $moduleReader
     * @param TimezoneInterface           $timezone
     * @param Repository                  $assetRepos
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param StoreManagerInterface       $storeManagerInterface
     * @param Data                        $helper
     * @param WeSupplyMappings            $weSupplyMappings
     * @param Logger                      $logger
     * @param PurchasedCollection         $downloadableLinks
     * @param PurchasedItemCollection     $downloadableItemLinks
     * @param OrderStatusCollection       $orderStatusCollection
     */
    public function __construct(
        ProductRepositoryInterface $productRepositoryInterface,
        ImageHelper $imageHelper,
        CustomerRepositoryInterface $customer,
        Country $country,
        AttributeInterface $attributeInterface,
        Attribute $productAttr,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ManagerInterface $eventManager,
        Filesystem $filesystem,
        Reader $moduleReader,
        TimezoneInterface $timezone,
        Repository $assetRepos,
        ShipmentRepositoryInterface $shipmentRepository,
        StoreManagerInterface $storeManagerInterface,
        Data $helper,
        WeSupplyMappings $weSupplyMappings,
        Logger $logger,
        PurchasedCollection $downloadableLinks,
        PurchasedItemCollection $downloadableItemLinks,
        OrderStatusCollection $orderStatusCollection
    ) {
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->imageHelper = $imageHelper;
        $this->customer = $customer;
        $this->country = $country;
        $this->attributeInterface = $attributeInterface;
        $this->productAttr = $productAttr;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->eventManager = $eventManager;
        $this->filesystem = $filesystem;
        $this->moduleReader = $moduleReader;
        $this->timezone = $timezone;
        $this->assetRepos = $assetRepos;
        $this->shipmentRepository = $shipmentRepository;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->_helper = $helper;
        $this->weSupplyMappings = $weSupplyMappings;
        $this->logger = $logger;
        $this->downloadableLinks = $downloadableLinks;
        $this->downloadableItemLinks = $downloadableItemLinks;

        $this->availableOrderStatuses = $orderStatusCollection->getItems();
        $this->weSupplyStatusIdMappedArray = $weSupplyMappings->mapOrderStateToWeSupplyStatusId();
        $this->weSupplyStatusMappedArray = $weSupplyMappings->mapOrderStateToWeSupplyStatus();
    }

    /**
     * @param $order
     * @param $existingOrderData
     *
     * @return array|bool|mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function gatherInfo($order, $existingOrderData)
    {
        $orderData = $order->getData();

        $this->orderOrigStatusCode = $orderData['status'];
        $this->wsOrderStatus = $this->weSupplyMappings
            ->prepareOrderStatus($orderData['status'], $this->availableOrderStatuses);

        /** Define carrier and supplier code */
        $this->prepareCarrierSupplierCodes($order, $orderData);

        /** Gather order items information */
        $this->getOrderItems($order, $orderData);

        /** Set billing and shipping Address */
        $billingAddressData = $order->getBillingAddress()->getData();
        $orderData['billingAddressInfo'] = $billingAddressData;

        /** Downloadable product order have no shipping address */
        $shippingAddressData = $billingAddressData;
        if ($order->getShippingAddress()) {
            $shippingAddressData = $order->getShippingAddress()->getData();
        }
        $orderData['shippingAddressInfo'] = $shippingAddressData;

        /** Gather the shipments and tracking information */
        $this->getShipmentData($order, $orderData);

        /** Set payment data */
        $paymentData = $order->getPayment()->getData();
        $orderData['paymentInfo'] = $paymentData;

        return $this->mapFieldsForWeSupplyOrderStructure($orderData, $existingOrderData);
    }

    /**
     * Prepares the order information for db storage
     * @param array $orderData
     * @return mixed|string
     */
    public function prepareForStorage($orderData)
    {
        return $this->convertInfoToXml($orderData);
    }

    /**
     * Returns the order last updated time
     * @param array $orderData
     * @return mixed|string
     */
    public function getUpdatedAt($orderData)
    {
        return $orderData['OrderModified'];
    }

    /**
     * Return the store id from the order information array
     * @param array $orderData
     * @return int|mixed
     */
    public function getStoreId($orderData)
    {
        return $orderData['StoreId'];
    }

    /**
     * Return the order number from the order information array
     * @param array $orderData
     * @return int
     */
    public function getOrderNumber($orderData)
    {
        return $orderData['OrderNumber'];
    }

    /**
     * @param $order
     * @param $orderData
     */
    protected function prepareCarrierSupplierCodes($order, &$orderData)
    {
        $carrierCode = '';
        $supplierCode = $this->_helper->recursivelyGetArrayData(['store_id'], $orderData);

        if ($shippingMethod = $order->getShippingMethod()) {
            $shippingMethodArr = explode('_', $shippingMethod);
            $carrierCode = reset($shippingMethodArr);

            $mappedCarrierCodes = $this->weSupplyMappings->getMappedCarrierCodes();
            if (isset($mappedCarrierCodes[$carrierCode])) {
                $carrierCode = $mappedCarrierCodes[$carrierCode];
            }

            if ($extAttrs = $order->getExtensionAttributes()) {
                $extAttrsArr = $extAttrs->__toArray();
                if (isset($extAttrsArr['pickup_location_code'])) {
                    $supplierCode = $extAttrsArr['pickup_location_code'];
                }

                unset($orderData['extension_attributes']);
            }
        }

        $orderData['carrier_code'] = $carrierCode;
        $orderData['supplier_code'] = $supplierCode;
    }

    /**
     * @param $order
     * @param $orderData
     */
    protected function getOrderItems($order, &$orderData)
    {
        $items = $order->getItems();
        $orderData['OrderItems'] = [];

        $i = 0;
        foreach ($items as $item) {
            $itemData = $item->getData();
            if (isset($itemData['parent_item'])) { // that means it is a simple associated product
                if (array_key_exists($i-1, $orderData['OrderItems'])) { // try to get and set product cost
                    $orderData['OrderItems'][$i - 1]['base_cost'] = $this->_helper->recursivelyGetArrayData(['base_cost'], $itemData, 0);
                }

                continue;
            }

            unset($itemData['has_children']);
            $orderData['OrderItems'][] = $itemData;

            $i++;
        }

        unset($orderData['items']);
    }

    /**
     * @param $order
     * @param $orderData
     */
    protected function getShipmentData($order, &$orderData)
    {
        $shipmentTracks = [];
        $shipmentData = [];
        $shipmentCollection = $order->getShipmentsCollection();

        $inventorySourcesByItemIds = $this->_fetchInventorySourcesByItems($orderData);
        if ($shipmentCollection->getSize()) {
            foreach ($shipmentCollection->getItems() as $shipment) {
                $tracks = $shipment->getTracksCollection();

                foreach ($tracks->getItems() as $track) {
                    $trackId = $track->getParentId();
                    $shipmentTracks[$trackId]['track_number'] = $track['track_number'];
                    $shipmentTracks[$trackId]['title'] = $track['title'];
                    $shipmentTracks[$trackId]['carrier_code'] = $track['carrier_code'];
                }

                $sItems = $shipment->getItemsCollection();
                foreach ($sItems as $shipmentItem) {
                    /** Default empty values for non existing tracking */
                    $shipmentId = $shipmentItem->getParentId();
                    if (!isset($shipmentTracks[$shipmentId])) {
                        $shipmentTracks[$shipmentId]['track_number'] = '';
                        $shipmentTracks[$shipmentId]['title'] = '';
                        $shipmentTracks[$shipmentId]['carrier_code'] =
                            $this->_helper->recursivelyGetArrayData(['carrier_code'], $orderData);
                    }

                    $shipmentTracks[$shipmentId]['inventory_source'] =
                        !empty($inventorySourcesByItemIds) && isset($inventorySourcesByItemIds[$shipmentId]) ?
                            $inventorySourcesByItemIds[$shipmentId] :
                            $this->_helper->recursivelyGetArrayData(['store_id'], $orderData);

                    $shipmentData[$shipmentItem['order_item_id']][] = array_merge(
                        [
                            'qty' => floatval($shipmentItem['qty']),
                            'sku' => $shipmentItem['sku']
                        ],
                        $shipmentTracks[$shipmentId]
                    );
                }
            }
        }

        $orderData['shipmentTracking'] = $shipmentData;
    }

    /**
     * @param $date
     * @return false|string
     */
    protected function modifyToLocalTimezone($date)
    {
        if ($date) {
            try {
                $formattedDate = $this->timezone->formatDateTime(
                    $date,
                    \IntlDateFormatter::SHORT,
                    \IntlDateFormatter::MEDIUM,
                    null,
                    null,
                    'yyyy-MM-dd HH:mm:ss'
                );
            } catch (\Exception $e) {
                $this->logger->error("WeSupply Error when changing date to local timezone:" . $e->getMessage());
                return false;
            }
        }

        return $formattedDate ?? date('Y-m-d H:i:s');
    }

    /**
     * @param $orderData
     * @param $existingOrderData
     * @return array|bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function mapFieldsForWeSupplyOrderStructure($orderData, $existingOrderData)
    {
        $finalOrderData = [];

        /** Collect general order data */
        $this->mapOrderStateToWeSupply($orderData, $finalOrderData);
        $this->mapOrderDataToWeSupply($orderData, $finalOrderData);

        /** Collect customer data */
        $this->collectCustomerGeneralData($finalOrderData, $orderData);
        $this->collectCustomerBillingData($finalOrderData, $orderData);
        $this->collectCustomerShippingData($finalOrderData, $orderData);

        /** Prepare order items */
        $this->prepareOrderItemsData($orderData, $existingOrderData, $finalOrderData);
        if (empty($finalOrderData['OrderItems'])) {
            return [];
        }

        if (count($finalOrderData['OrderItems']) === $this->availableDownloadableItems) {
            // force orders status to complete
            $finalOrderData['OrderStatus'] = 'Complete';
            $finalOrderData['OrderStatusId'] = $this->weSupplyMappings->getWsOrderCompleteId();
        }

        return $finalOrderData;
    }

    /**
     * @param $timestamps
     * @return string
     */
    protected function unifyDeliveryTimestamps($timestamps)
    {
        $timestamps = $timestamps ?? '';
        $prevTstp = false;
        $timestampsArr = explode(',', $timestamps);
        foreach ($timestampsArr as $index => $timestamp) {
            $currentTstp = $timestamp;
            if (($prevTstp && $currentTstp == $prevTstp) || empty($currentTstp)) {
                unset($timestampsArr[$index]);
                $prevTstp = $currentTstp;

                continue;
            }
            $prevTstp = $currentTstp;
        }

        return implode(',', $timestampsArr);
    }

    /**
     * @param $timestamps
     * @param $offset
     * @return string
     */
    private function applyOffset($timestamps, $offset)
    {
        $timestampsArr = array_map('intval', explode(',', $timestamps));

        foreach ($timestampsArr as $key => $timestamp) {
            $timestampsArr[$key] = $timestamp + (int) $offset;
        }

        return  implode(',', $timestampsArr);
    }

    /**
     * Converts order information
     * @param $orderData
     * @return mixed
     */
    protected function convertInfoToXml($orderData)
    {
        $xmlData = $this->array2xml($orderData, false);
        $xmlData = str_replace("<?xml version=\"1.0\"?>\n", '', $xmlData);

        return $xmlData;
    }

    /**
     * Convert array to xml
     * @param $array
     * @param bool $xml
     * @param string $xmlAttribute
     * @return mixed
     */
    private function array2xml($array, $xml = false, $xmlAttribute = '')
    {
        if ($xml === false) {
            $xml = new \SimpleXMLElement('<Order/>');
        }

        foreach ($array as $key => $value) {
            $key = ucwords($key, '_');
            if (is_object($value)) {
                continue;
            }
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $this->array2xml($value, $xml->addChild($key), $key);
                } else {
                    //mapping for $key to proper
                    $xmlAttribute = $this->mapXmlAttributeForChildren($xmlAttribute);
                    $this->array2xml($value, $xml->addChild($xmlAttribute), $key);
                }
            } else {
                if (is_numeric($key)) {
                    $child = $xml->addChild($xmlAttribute);
                    $child->addAttribute('key', $key);
                    $value = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
                    $child->addAttribute('value', $value);
                } else {
                    $value = $value ?? '';
                    $value = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
                    $xml->addChild($key, $value);
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * @param $key
     * @return mixed
     */
    private function mapXmlAttributeForChildren($key)
    {
        $mappings = [
            'OrderItems' => 'Item',
            'AttributesInfo' => 'Info'
        ];

        if (isset($mappings[$key])) {
            return $mappings[$key];
        }

        return $key;
    }

    /**
     * Return country name
     * @param $countryId
     * @return string
     */
    protected function getCountryName($countryId)
    {
        $country = $this->country->loadByCode($countryId);
        return $country->getName();
    }

    /**
     * Due to possibility of endless order statuses in magento2
     * we are transferring the order status label and order state mapped to WeSupply order status
     *
     * @param $orderData
     * @param $finalOrderData
     */
    protected function mapOrderStateToWeSupply($orderData, &$finalOrderData)
    {
        $orderStatusId = $this->weSupplyStatusIdMappedArray[\Magento\Sales\Model\Order::STATE_NEW];

        if (!empty($orderData['state'])) {
            $state = $orderData['state'];
            if (array_key_exists($state, $this->weSupplyStatusIdMappedArray)) {
                $orderStatusId = $this->weSupplyStatusIdMappedArray[$state];
            }
        }

        $finalOrderData['OrderStatus'] = $this->wsOrderStatus;
        $finalOrderData['OrderStatusId'] = $orderStatusId;
    }

    /**
     * Assign Magento order data to WeSupply order fields
     *
     * @param $orderData
     * @param $finalOrderData
     */
    protected function mapOrderDataToWeSupply($orderData, &$finalOrderData)
    {
        $finalOrderData['OrderModified'] = date('Y-m-d H:i:s');
        $finalOrderData['StoreId'] = $this->_helper->recursivelyGetArrayData(['store_id'], $orderData);
        $finalOrderData['OrderID'] = self::PREFIX . $this->_helper->recursivelyGetArrayData(['entity_id'], $orderData);
        $finalOrderData['OrderNumber'] = $this->_helper->recursivelyGetArrayData(['increment_id'], $orderData);
        $finalOrderData['OrderExternalOrderID'] = $this->_helper->recursivelyGetArrayData(['increment_id'], $orderData);
        $finalOrderData['OrderDate'] = $this->modifyToLocalTimezone($orderData['created_at']);
        $finalOrderData['LastModifiedDate'] = $this->modifyToLocalTimezone($orderData['updated_at']);
        $finalOrderData['FirstName'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'firstname'], $orderData);
        $finalOrderData['LastName'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'lastname'], $orderData);
        $finalOrderData['OrderContact'] = $finalOrderData['FirstName'] . ' ' . $finalOrderData['LastName'];
        $finalOrderData['OrderAmount'] = $this->_helper->recursivelyGetArrayData(['base_subtotal'], $orderData);
        $finalOrderData['OrderAmountShipping'] = $this->_helper->recursivelyGetArrayData(['base_shipping_amount'], $orderData);
        $finalOrderData['OrderAmountTax'] = $this->_helper->recursivelyGetArrayData(['base_tax_amount'], $orderData);
        $finalOrderData['OrderAmountTotal'] = $this->_helper->recursivelyGetArrayData(['base_grand_total'], $orderData);
        $finalOrderData['OrderAmountCoupon'] = number_format(0, 4, '.', '');
        $finalOrderData['OrderAmountGiftCard'] = $this->_helper->recursivelyGetArrayData(['base_gift_cards_amount'], $orderData, '0.0000');
        $finalOrderData['OrderPaymentTypeId'] = '';
        $finalOrderData['OrderPaymentType'] = $this->_helper->recursivelyGetArrayData(['paymentInfo', 'additional_information', 'method_title'], $orderData);
        $finalOrderData['OrderDiscountDetailsTotal'] = $this->_helper->recursivelyGetArrayData(['base_discount_amount'], $orderData);
        $finalOrderData['CurrencyCode'] = $this->_helper->recursivelyGetArrayData(['order_currency_code'], $orderData);
        $finalOrderData['EstimateUTCOffset'] = $this->_helper->recursivelyGetArrayData(['delivery_utc_offset'], $orderData, 0);
        $finalOrderData['EstimateUTCTimestamp'] = $this->applyOffset(
            $this->unifyDeliveryTimestamps($this->_helper->recursivelyGetArrayData(['delivery_timestamp'], $orderData, '')),
            $finalOrderData['EstimateUTCOffset']
        );
    }

    /**
     * @param $status
     * @param $information
     */
    protected function getItemStatusInfo($status, &$information)
    {
        switch (strtolower($status)) {
            case 'canceled':
                $itemStatus = 'Canceled';
                $itemStatusId = 1;
                break;
            case 'refunded':
                $itemStatus = 'Refunded';
                $itemStatusId = 2;
                break;
            case 'shipped':
                $itemStatus = 'Shipped';
                $itemStatusId = 3;
                break;
            case 'instore_pickup':
                $itemStatus = 'Ready for Pickup';
                $itemStatusId = 15;
                break;
            case 'virtual':
                $itemStatus = 'Virtual';
                $itemStatusId = 50;
                break;
            case 'download_available':
                $itemStatus = 'Downloadable';
                $itemStatusId = 60;
                break;
            default:
                $currentStatus = $this->prepareCurrentItemStatus();
                $itemStatus = $currentStatus['status'];
                $itemStatusId = $currentStatus['status_id'];
                break;

        }

        $information['ItemStatus'] = $itemStatus;
        $information['ItemStatusId'] = $itemStatusId;
    }

    /**
     * @param $orderData
     * @param $existingOrderData
     * @param $finalOrderData
     *
     * @throws NoSuchEntityException
     */
    protected function prepareOrderItemsData($orderData, $existingOrderData, &$finalOrderData)
    {
        $orderItems = [];
        $this->availableDownloadableItems = 0;

        $itemFeeShipping = floatval($this->_helper->recursivelyGetArrayData(['base_shipping_amount'], $orderData, 0));
        $orderItemsData = $orderData['OrderItems'];
        $initItemStatus = $this->orderOrigStatusCode;

        foreach ($orderItemsData as $item) {

            $generalData = [];
            $generalData['ItemID'] = $this->_helper->recursivelyGetArrayData(['item_id'], $item);
            $generalData['ItemPrice'] = $this->_helper->recursivelyGetArrayData(['base_price'], $item);
            $generalData['ItemCost'] = $this->_helper->recursivelyGetArrayData(['base_cost'], $item, $generalData['ItemPrice']);
            $generalData['ItemAddressID'] = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'entity_id'], $orderData);
            $generalData['Option1'] = '';
            $generalData['Option2'] = $this->_fetchProductOptionsAsArray($item);
            $generalData['Option3'] = $this->_fetchProductBundleOptionsAsArray($item);
            $generalData['ItemProduct'] = [];
            $generalData['ItemProduct']['ProductID'] = $this->_helper->recursivelyGetArrayData(['product_id'], $item);
            $generalData['ItemProduct']['ProductCode'] = $this->_helper->recursivelyGetArrayData(['name'], $item);
            $generalData['ItemProduct']['ProductPartNo'] = $this->_helper->recursivelyGetArrayData(['sku'], $item);
            $generalData['ItemTitle'] = $this->_helper->recursivelyGetArrayData(['name'], $item);

            /**
             * some item data needs to remain as it was at the place order moment
             * so, we are not allowed to update it
             */
            $generalData = $this->_fetchInvariableData($existingOrderData, $item, $generalData);

            $itemQtyGrouped = $this->splitItemQty($item, $orderData);
            $itemTotals = $this->getItemTotals($item);

            $carrierCode = $orderData['carrier_code'];

            /** Send information about downloadable items */
            $generalData['ItemDownloadUrl'] = '';
            if ($item['product_type'] === DownloadableType::TYPE_DOWNLOADABLE) {
                $carrierCode = 'downloadable';
                $initItemStatus = 'download_available';
                $generalData['ItemDownloadUrl'] = $this->_getProductDownloadUrl($item);
            }

            /** Send information about virtual items */
            if ($item['product_type'] === ProductType::TYPE_VIRTUAL) {
                $carrierCode = 'virtual';
                $initItemStatus = 'virtual';
            }

            /** Send information about shipped items */
            $shippedItems = $orderData['shipmentTracking'];
            $mappedCarrierCodes = $this->weSupplyMappings->getMappedCarrierCodes();
            foreach ($shippedItems as $itemId => $shipment) {
                if ($itemId == $this->_helper->recursivelyGetArrayData(['item_id'], $item)) {
                    foreach ($shipment as $trackingInformation) {
                        $carrierCode = $this->_helper->recursivelyGetArrayData(
                            ['carrier_code'],
                            $trackingInformation
                        );

                        if (isset($mappedCarrierCodes[$carrierCode])) {
                            $carrierCode = $mappedCarrierCodes[$carrierCode];
                        }

                        $itemStatus = strtolower($carrierCode) == strtolower($this->weSupplyMappings->getInStorePickupLabel()) ?
                            'instore_pickup' : 'shipped';

                        $itemInfo = $this->getItemSpecificInformation(
                            $itemFeeShipping,
                            $itemTotals['row_total'],
                            $itemTotals['tax_amount'],
                            $itemTotals['discount_amount'],
                            $itemQtyGrouped['qty_ordered'],
                            $trackingInformation['qty'],
                            $itemStatus,
                            $trackingInformation['title'],
                            $trackingInformation['track_number'],
                            $trackingInformation['inventory_source'],
                            $carrierCode
                        );

                        $itemFeeShipping = 0; // reset shipping fee because its amount was assigned for the very firs shipped item
                        if ($this->groupItemsWithSameTracking($orderItems, $trackingInformation['track_number'], $itemId, $itemInfo)) {
                            continue;
                        }

                        $generalData = array_merge($generalData, $itemInfo);
                        $orderItems[] = $generalData;
                    }
                }
            }

            /** Send information about canceled items */
            if ($itemQtyGrouped['qty_canceled'] > 0) {
                $itemInfo = $this->getItemSpecificInformation(
                    $itemFeeShipping,
                    $itemTotals['row_total'],
                    $itemTotals['tax_amount'],
                    $itemTotals['discount_amount'],
                    $itemQtyGrouped['qty_ordered'],
                    $itemQtyGrouped['qty_canceled'],
                    'canceled',
                    '',
                    '',
                    $this->_helper->recursivelyGetArrayData(['supplier_code'], $orderData),
                    $carrierCode
                );

                $generalData = array_merge($generalData, $itemInfo);
                $orderItems[] = $generalData;
            }

            /** For more detailed data we might use information  from the created credit memos */
            if ($itemQtyGrouped['qty_refunded'] > 0) {
                $itemInfo = $this->getItemSpecificInformation(
                    $itemFeeShipping,
                    $itemTotals['row_total'],
                    $itemTotals['tax_amount'],
                    $itemTotals['discount_amount'],
                    $itemQtyGrouped['qty_ordered'],
                    $itemQtyGrouped['qty_refunded'],
                    'refunded',
                    '',
                    '',
                    $this->_helper->recursivelyGetArrayData(['supplier_code'], $orderData),
                    $carrierCode
                );

                $generalData = array_merge($generalData, $itemInfo);
                $orderItems[] = $generalData;
            }

            /** Send information about items still in processed state */
            if ($itemQtyGrouped['qty_processing'] > 0) {
                $itemInfo = $this->getItemSpecificInformation(
                    $itemFeeShipping,
                    $itemTotals['row_total'],
                    $itemTotals['tax_amount'],
                    $itemTotals['discount_amount'],
                    $itemQtyGrouped['qty_ordered'],
                    $itemQtyGrouped['qty_processing'],
                    $initItemStatus,
                    '',
                    '',
                    $this->_helper->recursivelyGetArrayData(['supplier_code'], $orderData),
                    $carrierCode
                );

                $generalData = array_merge($generalData, $itemInfo);
                $orderItems[] = $generalData;
            }
            $itemFeeShipping = 0;
        }

        $finalOrderData['OrderItems'] = $orderItems;
    }

    /**
     * @param $itemFeeShipping
     * @param $itemTotal
     * @param $taxTotal
     * @param $discountTotal
     * @param $qtyOrdered
     * @param $qtyCurrent
     * @param $status
     * @param $shippingService
     * @param $shippingTracking
     * @param $inventorySource
     * @param $carrierCode
     *
     * @return array
     */
    protected function getItemSpecificInformation(
        $itemFeeShipping,
        $itemTotal,
        $taxTotal,
        $discountTotal,
        $qtyOrdered,
        $qtyCurrent,
        $status,
        $shippingService,
        $shippingTracking,
        $inventorySource,
        $carrierCode
    ) {
        $information = [];
        $information['ItemQuantity'] = $qtyCurrent;
        $information['ItemShippingService'] = $shippingService;
        $information['ItemPOShipper'] = $carrierCode;
        $information['ItemShippingTracking'] = $shippingTracking;
        $information['ItemLevelSupplierName'] = $inventorySource;

        $information['ItemTotal'] = ($qtyCurrent * $itemTotal) / $qtyOrdered;
        $information['ItemTax'] = $this->numberFormat(($qtyCurrent * $taxTotal) / $qtyOrdered);
        $information['ItemDiscountDetailsTotal'] = $this->numberFormat(($qtyCurrent * $discountTotal) / $qtyOrdered);

        /**
         *  ItemShipping - the first item will have shipping value, all other items will have 0 value
         *  Item_CouponAmount - will always have 0, the discount amount is set trough OrderDiscountDetailsTotal field
         */
        $information['ItemShipping'] = $this->numberFormat($itemFeeShipping);
        $information['Item_CouponAmount'] = '0.0000';

        /**
         * ItemTotal will include also the shipping value
         */
        $information['ItemTotal'] += $itemFeeShipping;
        $information['ItemTotal'] = $this->numberFormat($information['ItemTotal']);

        $this->getItemStatusInfo($status, $information);

        return $information;
    }

    /**
     * @param $number
     * @param $decimals
     *
     * @return string
     */
    private function numberFormat($number, $decimals = 4)
    {
        return number_format($number, $decimals, '.', '');
    }

    private function _fetchProductBundleOptionsAsArray($item)
    {
        $bundleArray = [];
        /**
         * bundle product options
         */
        $productOptions = $item['product_options'];
        if (isset($productOptions['bundle_options'])) {
            foreach ($productOptions['bundle_options'] as $bundleOptions) {
                $bundleProductInfo = [];
                $bundleProductInfo['label'] = $bundleOptions['label'];
                $finalOptionsCounter = 0;
                foreach ($bundleOptions['value'] as $finalOptions) {
                    $bundleProductInfo['product_' . $finalOptionsCounter] = $finalOptions;
                    $finalOptionsCounter++;
                }
                $bundleArray['value_' . $bundleOptions['option_id']] = $bundleProductInfo;
            }
        }

        return $bundleArray;
    }

    /**
     * @param $item
     * @return array
     */
    private function _fetchProductAttributesToExport($item)
    {
        $attrToBeCollected = $this->_helper->getAttributesToBeExported();

        return $this->collectAttributeValues($item, $attrToBeCollected);
    }

    /**
     * Collect product attributes
     *
     * @param $item
     * @param $attrToBeCollected
     * @return array
     */
    private function collectAttributeValues($item, $attrToBeCollected)
    {
        $prodAttrs = [];
        $products = $this->getProductsFromItemByPriorityFetch($item);
        foreach ($products as $_product) {
            if (is_null($_product)) {
                continue;
            }

            foreach ($attrToBeCollected as $attrCode) {
                if (!array_key_exists($attrCode, $prodAttrs)) {
                    if ($attrCode == 'price') {
                        $prodAttrs[$attrCode] = $this->_helper->recursivelyGetArrayData(['base_price'], $item);
                        continue;
                    }
                    if ($attributeData = $_product->getData($attrCode)) {
                        $attribute = $_product->getResource()->getAttribute($attrCode);
                        if ($attribute->usesSource()) {
                            $attributeData = $attribute->getSource()->getOptionText($attributeData);
                            if ($attributeData instanceof Phrase) {
                                $attributeData = $attributeData->getText();
                            }
                        }

                        $prodAttrs[$attrCode] = is_array($attributeData) ?
                            implode(', ', $attributeData) : $attributeData;
                    }
                }
            }
        }

        return $prodAttrs;
    }

    /**
     * @param $item
     * @return array
     */
    private function getProductsFromItemByPriorityFetch($item)
    {
        $productOptions = $item['product_options'];
        $fetchFrom = $this->_helper->getAttributesFetchPriority();
        switch ($fetchFrom) {
            case 'itself_parent':
                if (isset($productOptions['simple_sku'])) {
                    $products[] = $this->_getProductBySku($productOptions['simple_sku']);
                }
                $products[] = $this->_getProductById($item['product_id']);
                break;
            case 'parent_itself':
                $products[] = $this->_getProductById($item['product_id']);
                if (isset($productOptions['simple_sku'])) {
                    $products[] = $this->_getProductBySku($productOptions['simple_sku']);
                }
                break;
            case 'itself_only':
                $products[] = isset($productOptions['simple_sku']) ? // it was ordered a configurable product?
                    $this->_getProductBySku($productOptions['simple_sku']) :
                    $this->_getProductById($item['product_id']);
                break;
            case 'parent_only':
                $products[] = $this->_getProductById($item['product_id']);
                break;
            default:
                $products = [];
                break;
        }

        return $products ?? [];
    }

    /**
     * @param $item
     * @return array
     */
    private function _fetchProductOptionsAsArray($item)
    {
        $optionsArray = [];
        /**
         * configurable product options
         */
        $productOptions = $item['product_options'];
        if (isset($productOptions['attributes_info'])) {
            foreach ($productOptions['attributes_info'] as $attributes) {
                $label = (isset($attributes['label'])) ? trim($attributes['label']) : '';
                $xmlLabel = preg_replace('/[^\w0-1]|^\d/', '_', $label);
                $optionsArray[$xmlLabel] = $attributes['value'];
            }
        }

        /**
         * custom options
         */
        if (isset($productOptions['options'])) {
            foreach ($productOptions['options'] as $customOption) {
                $label = (isset($customOption['label'])) ? trim($customOption['label']) : '';
                $xmlLabel = preg_replace('/[^\w0-1]|^\d/', '_', $label);
                $optionsArray[$xmlLabel] = $customOption['value'];
            }
        }

        return $optionsArray;
    }

    /**
     * Fetch item image
     * or fallback on getting the default placeholder
     *
     * @param $item
     * @return string
     * @throws NoSuchEntityException
     */
    private function _fetchProductImage($item)
    {
        $storeManager = $this->storeManagerInterface->getStore($item['store_id']);
        $this->mediaUrl = $storeManager->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        $productOptions = $item['product_options'];
        if (isset($productOptions['simple_sku'])) { // first, look for associated simple product image
            $_product = $this->_getProductBySku($productOptions['simple_sku']);
            if (!is_null($_product)) {
                $productImage = $_product->getImage();
                if ($this->isValidProductImage($productImage) && $this->checkRealMediaDir($productImage)) {
                    return $this->mediaUrl . self::PRODUCT_IMAGE_SUBDIRECTORY . trim($productImage, '/');
                }
            }
        }

        $_product = $this->_getProductById($item['product_id']);
        if (!is_null($_product)) {
            $productImage = $_product->getImage();
            if ($this->isValidProductImage($productImage) && $this->checkRealMediaDir($productImage)) {
                return $this->mediaUrl . self::PRODUCT_IMAGE_SUBDIRECTORY . trim($productImage, '/');
            }
        }

        /**
         * finally try to get the custom placeholder image
         * if the above methods failed
         */
        $imageUrl = $this->imageHelper->getDefaultPlaceholderUrl('image');

        return $this->convertToUnversionedFrontendUrl($imageUrl, $item['store_id']) ?? '';
    }

    /**
     * @param $item
     * @return string
     */
    private function _fetchProductUrl($item)
    {
        $product = $this->_getProductById($item['product_id']);
        if (!is_null($product)) {
            return $product->getProductUrl();
        }

        return '#';
    }

    /**
     * @return mixed
     */
    private function _fetchWeightUnit()
    {
        return $this->_helper->getWeightUnit();
    }

    /**
     * @return string
     */
    private function _fetchMeasurementsUnit()
    {
        return $this->_helper->getMeasurementsUnit();
    }

    /**
     * @param $item
     * @param $attrCode
     * @return string
     */
    private function _fetchProductAttr($item, $attrCode)
    {
        $attrData = $this->collectAttributeValues($item, [$attrCode]);

        if (isset($attrData[$attrCode])) {
            return $attrData[$attrCode];
        }

        return '';
    }

    /**
     * @param $item
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function _getProductDownloadUrl($item)
    {
        $purchased = $this->downloadableLinks
            ->addFieldToFilter('order_id', $item['order_id'])
            ->addFieldToFilter('order_item_id', $item['item_id'])
            ->getFirstItem();

        if (!$purchased->getPurchasedId()) {
            return '';
        }

        $purchasedItem = $this->downloadableItemLinks
            ->addFieldToFilter(
                'purchased_id', ['in' => $purchased->getPurchasedId()]
            )->addFieldToFilter(
                'status', ['nin' => [Item::LINK_STATUS_PENDING_PAYMENT, Item::LINK_STATUS_PAYMENT_REVIEW]]
            )->setOrder(
                'item_id',
                'desc'
            )->getFirstItem();

        if ($purchasedItem->getStatus() !== Item::LINK_STATUS_AVAILABLE) {
            return '';
        }

        $this->availableDownloadableItems++;

        return $this->storeManagerInterface->getStore($item['store_id'])
                ->getBaseUrl(UrlInterface::URL_TYPE_LINK)
            . 'downloadable/download/link/'
            . $purchasedItem->getLinkHash();
    }

    /**
     * @param $productId
     * @return ProductInterface
     */
    private function _getProductById($productId)
    {
        try {
            return $this->productRepositoryInterface->getById($productId);
        } catch (NoSuchEntityException $e) {
            $this->logger->error('Wesupply error: ' . $e->getMessage());
        }
    }

    /**
     * @param $productSku
     * @return ProductInterface
     */
    private function _getProductBySku($productSku)
    {
        try {
            return $this->productRepositoryInterface->get($productSku);
        } catch (NoSuchEntityException $e) {
            $this->logger->error('Wesupply error: ' . $e->getMessage());
        }
    }

    /**
     * @param $imageUrl
     * @param $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    private function convertToUnversionedFrontendUrl($imageUrl, $storeId)
    {
        $theme = $this->_helper->getCurrentTheme($storeId);
        $imageUrlArr = explode('/', $imageUrl);

        foreach ($imageUrlArr as $key => $urlPart) {
            if (strpos($urlPart, 'version') !== false) {
                unset($imageUrlArr[$key]);
            }
            if (strpos($urlPart, 'adminhtml') !== false || strpos($urlPart, 'webapi') !== false) {
                $imageUrlArr[$key] = 'frontend';
            }
            if (strpos($urlPart, 'view') !== false) {
                $imageUrlArr[$key] = trim($theme->getThemePath() ?? '', '/');
            }
            if (strpos($urlPart, 'backend') !== false) {
                $themePathArr = explode('/', $theme->getThemePath() ?? '');
                $imageUrlArr[$key] = end($themePathArr);
            }
        }

        return implode('/', $imageUrlArr);
    }

    /**
     * @param $productImage
     *
     * @return bool
     */
    private function isValidProductImage($productImage)
    {
        return (!empty($productImage) && strpos($productImage, 'no_selection') === false);
    }

    /**
     * @param $existingOrderData
     * @param $item
     * @param $generalData
     * @return mixed
     * @throws NoSuchEntityException
     */
    protected function _fetchInvariableData($existingOrderData, $item, &$generalData)
    {
        $existingItemKey = false;
        if ($existingOrderData) {// check if this is the first sync or it is an update
            if ($this->_isMultiProducts($existingOrderData['OrderItems']['Item'])) {
                $found = array_filter($existingOrderData['OrderItems']['Item'],
                    function ($existingItemData) use ($item) {
                        return $existingItemData['ItemID'] == $item['item_id'];
                    }
                );
                $existingItemKey = key($found);
            }

            $origItemData = false !== $existingItemKey ?
                $existingOrderData['OrderItems']['Item'][$existingItemKey] :
                $existingOrderData['OrderItems']['Item'];

            foreach (self::DO_NOT_UPDATE as $key) {
                $generalData[$key] = $origItemData[$key];
            }
        }

        foreach (self::DO_NOT_UPDATE as $key) {
            if (!isset($generalData[$key])) {
                switch ($key) {
                    case 'ItemImageUri':
                        $itemData = $this->_fetchProductImage($item);
                        break;
                    case 'ItemProductUri':
                        $itemData = $this->_fetchProductUrl($item);
                        break;
                    case 'OptionHidden':
                        $itemData = $this->_fetchProductAttributesToExport($item);
                        break;
                    case 'ItemWeight':
                    case 'ItemWidth':
                    case 'ItemHeight':
                    case 'ItemLength':
                        $attrCode = $this->_helper->getOrderExportSettings(
                            $this->_helper->fromCamelCase($key, '_') . '_attr'
                        );
                        $itemData = $attrCode ? $this->_fetchProductAttr($item, $attrCode) : '';
                        break;
                    case 'ItemWeightUnit':
                        $itemData = $this->_fetchWeightUnit();
                        break;
                    case 'ItemMeasureUnit':
                        $itemData = $this->_fetchMeasurementsUnit();
                        break;
                    default:
                        $itemData = '';
                        break;
                }
                $generalData[$key] = $itemData;
            }
        }

        return $generalData;
    }

    /**
     * Check image file and update mediaUrl
     *
     * @param $productImage
     * @return bool
     */
    private function checkRealMediaDir($productImage)
    {
        $mediaDir = $this->filesystem
            ->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();

        if (!file_exists($mediaDir . self::PRODUCT_IMAGE_SUBDIRECTORY . trim($productImage, '/'))) {
            // remove pub directory and recheck image file
            $replacement = '$1$3';
            $pattern = '/(^.*)(pub\/)(.*)/i';
            $mediaDir = preg_replace($pattern, $replacement, $mediaDir, 1);
            if (!file_exists($mediaDir . self::PRODUCT_IMAGE_SUBDIRECTORY . trim($productImage, '/'))) {
                return false;
            }
            // update mediaUrl
            $this->mediaUrl = preg_replace($pattern, $replacement, $this->mediaUrl);
        }

        return true;
    }

    /**
     * @param $arr
     * @return bool
     */
    private function _isMultiProducts($arr)
    {
        if ([] === $arr) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * @param $orderData
     * @return array
     */
    private function _fetchInventorySourcesByItems($orderData)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $orderData['entity_id'])->create();

        try {
            $shipments = $this->shipmentRepository->getList($searchCriteria);
            foreach ($shipments->getItems() as $shipment) {
                $shipmentDetails = $this->shipmentRepository->get($shipment->getEntityId());
                $extensionAttr = $shipmentDetails->getExtensionAttributes();
                foreach ($shipmentDetails->getItems() as $item) {
                    if (!method_exists($extensionAttr, 'getSourceCode')) {
                        $inventorySourcesByItemIds[$item->getParentId()] = $this->_helper->recursivelyGetArrayData(['store_id'], $orderData);
                        continue;
                    }

                    $inventorySourcesByItemIds[$item->getParentId()] = $extensionAttr->getSourceCode() != 'default' ?
                        $extensionAttr->getSourceCode() :
                        $this->_helper->recursivelyGetArrayData(['store_id'], $orderData);
                }
            }
        } catch (Exception $exception) {
            $this->logger->error('Error while fetching MSI ' . $exception->getMessage());
        }

        return $inventorySourcesByItemIds ?? [];
    }

    /**
     * @param $orderItems
     * @param $trackingNo
     * @param $currItemId
     * @param $currItemInfo
     * @return bool
     */
    private function groupItemsWithSameTracking(&$orderItems, $trackingNo, $currItemId, $currItemInfo)
    {
        $found = array_filter($orderItems, function ($orderedItem) use ($trackingNo, $currItemId) {
            return $orderedItem['ItemShippingTracking'] == $trackingNo && $orderedItem['ItemID'] == $currItemId;
        });

        if (!empty($found)) {
            $foundKey = key($found);
            $orderItems[$foundKey]['ItemQuantity'] += $currItemInfo['ItemQuantity'];
            $orderItems[$foundKey]['ItemTotal'] += $currItemInfo['ItemTotal'];
            $orderItems[$foundKey]['ItemTax'] += $currItemInfo['ItemTax'];
            $orderItems[$foundKey]['ItemDiscountDetailsTotal'] += $currItemInfo['ItemDiscountDetailsTotal'];
            $orderItems[$foundKey]['Item_CouponAmount'] += $currItemInfo['Item_CouponAmount'];

            return true;
        }

        return false;
    }

    /**
     * @param $item
     * @param $orderData
     * @return array
     */
    private function splitItemQty($item, &$orderData)
    {
        $qtySplit = [];
        $qtySplit['qty_ordered']    = floatval($this->_helper->recursivelyGetArrayData(['qty_ordered'], $item));
        $qtySplit['qty_canceled']   = floatval($this->_helper->recursivelyGetArrayData(['qty_canceled'], $item, 0));
        $qtySplit['qty_refunded']   = floatval($this->_helper->recursivelyGetArrayData(['qty_refunded'], $item, 0));
        $qtySplit['qty_shipped']    = !empty($orderData['shipmentTracking'][$item['item_id']]) ?
            array_reduce($orderData['shipmentTracking'][$item['item_id']], function($carry, $sItem) {
                $carry += floatval($sItem['qty']);
                return $carry;
            }) : 0;

        $qtyProcessing = $qtySplit['qty_ordered'] - $qtySplit['qty_canceled'] - $qtySplit['qty_refunded'] - $qtySplit['qty_shipped'];
        $qtySplit['qty_processing'] = $qtyProcessing > 0 ? $qtyProcessing : 0; // avoid negative values for qty processing

        $this->fixItemQtysForCanceledOrder($item, $orderData, $qtySplit);

        return $qtySplit;
    }

    /**
     * @param $item
     * @param $orderData
     * @param $qtySplit
     */
    private function fixItemQtysForCanceledOrder($item, &$orderData, &$qtySplit)
    {
        if (
            $orderData['status'] === 'canceled' &&
            $orderData['carrier_code'] === $this->weSupplyMappings->getInStorePickupLabel()
        ) {
            $qtySplit['qty_processing'] = $qtySplit['qty_shipped'] = 0;
            $qtySplit['qty_canceled'] = $qtySplit['qty_ordered'];

            if (!empty($orderData['shipmentTracking'][$item['item_id']])) {
                unset($orderData['shipmentTracking'][$item['item_id']]);
            }
        }
    }

    /**
     * @param $item
     * @return array
     */
    private function getItemTotals($item)
    {
        return [
            'row_total' => floatval($this->_helper->recursivelyGetArrayData(['row_total'], $item)),
            'tax_amount' => floatval($this->_helper->recursivelyGetArrayData(['tax_amount'], $item)),
            'discount_amount' => floatval($this->_helper->recursivelyGetArrayData(['discount_amount'], $item))
        ];
    }

    /**
     * @param $finalOrderData
     * @param $orderData
     * @throws LocalizedException
     */
    private function collectCustomerGeneralData(&$finalOrderData, $orderData)
    {
        $customerIsGuest = $this->_helper->recursivelyGetArrayData(['customer_is_guest'], $orderData);
        $customerId = $customerIsGuest ?
            intval(664616765 . '' . $orderData['entity_id']) :
            $this->_helper->recursivelyGetArrayData(['customer_id'], $orderData);

        $finalOrderData['OrderCustomer']['IsGuest'] = $customerIsGuest;
        $finalOrderData['OrderCustomer']['CustomerID'] = $customerId;

        try {
            $customer = $this->customer->getById($customerId);
            $finalOrderData['OrderCustomer']['CustomerCreateDate'] = $customer->getCreatedAt();
            $finalOrderData['OrderCustomer']['CustomerModifiedDate'] = $customer->getUpdatedAt();
        } catch (NoSuchEntityException $e) {
            $finalOrderData['OrderCustomer']['CustomerCreateDate'] = $finalOrderData['OrderDate'];
            $finalOrderData['OrderCustomer']['CustomerModifiedDate'] = $finalOrderData['LastModifiedDate'];
        }
    }

    /**
     * @param $finalOrderData
     * @param $orderData
     */
    private function collectCustomerBillingData(&$finalOrderData, $orderData)
    {
        $billingAddress = !empty($orderData['billingAddressInfo']) ? $orderData['billingAddressInfo'] : [];

        $finalOrderData['OrderCustomer']['CustomerFirstName'] = !empty($billingAddress['firstname']) ?  $billingAddress['firstname'] : '';
        $finalOrderData['OrderCustomer']['CustomerLastName'] = !empty($billingAddress['lastname']) ? $billingAddress['lastname'] : '';
        $finalOrderData['OrderCustomer']['CustomerName'] =
            $finalOrderData['OrderCustomer']['CustomerFirstName'] . ' ' . $finalOrderData['OrderCustomer']['CustomerLastName'];
        $finalOrderData['OrderCustomer']['CustomerEmail'] = !empty($billingAddress['email']) ? $billingAddress['email'] : '';
        $finalOrderData['OrderCustomer']['CustomerAddress1'] = !empty($billingAddress['street']) ? $billingAddress['street'] : '';
        $finalOrderData['OrderCustomer']['CustomerAddress2'] = ''; // not saved separately in magento
        $finalOrderData['OrderCustomer']['CustomerStateProvince'] = !empty($billingAddress['region']) ? $billingAddress['region'] : '';
        $finalOrderData['OrderCustomer']['CustomerCity'] = !empty($billingAddress['city']) ? $billingAddress['city'] : '';
        $finalOrderData['OrderCustomer']['CustomerPostalCode'] = !empty($billingAddress['postcode']) ? $billingAddress['postcode'] : '';
        $finalOrderData['OrderCustomer']['CustomerCountryCode'] = !empty($billingAddress['country_id']) ? $billingAddress['country_id'] : '';
        $finalOrderData['OrderCustomer']['CustomerCountry'] = !empty($billingAddress['country_id']) ? $this->getCountryName($billingAddress['country_id']) : '';
        $finalOrderData['OrderCustomer']['CustomerPhone'] = !empty($billingAddress['telephone']) ? $billingAddress['telephone'] : '';
    }

    /**
     * @param $finalOrderData
     * @param $orderData
     */
    private function collectCustomerShippingData(&$finalOrderData, $orderData)
    {
        $addressID = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'entity_id'], $orderData);
        $addressFirstName = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'firstname'], $orderData);
        $addressLastName = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'lastname'], $orderData);
        $addressContact = $addressFirstName . ' ' . $addressLastName;
        $address1 = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'street'], $orderData);
        $addressCity = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'city'], $orderData);
        $addressState = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'region'], $orderData);;
        $addressZip = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'postcode'], $orderData);
        $addressCountryCode = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'country_id'], $orderData);
        $addressCountry = $this->getCountryName($addressCountryCode);
        $addressPhone = $this->_helper->recursivelyGetArrayData(['shippingAddressInfo', 'telephone'], $orderData);

        $finalOrderData['OrderShippingAddress1'] = $address1;
        $finalOrderData['OrderShippingCity'] = $addressCity;
        $finalOrderData['OrderShippingStateProvince'] = $addressState;
        $finalOrderData['OrderShippingZip'] = $addressZip;
        $finalOrderData['OrderShippingCountryCode'] = $addressCountryCode;
        $finalOrderData['OrderShippingCountry'] = $addressCountry;
        $finalOrderData['OrderShippingPhone'] = $addressPhone;

        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressID'] = $addressID;
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressContact'] = $addressContact;
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressAddress1'] = $address1;
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressCity'] = $addressCity;
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressState'] = $addressState;
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressZip'] = $addressZip;
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressCountryCode'] = $addressCountryCode;
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressCountry'] = $addressCountry;
        $finalOrderData['OrderCustomer']['CustomerShippingAddresses']['CustomerShippingAddress']['AddressPhone'] = $addressPhone;
    }

    /**
     * @return array
     */
    private function prepareCurrentItemStatus()
    {
        $itemCurrentStatus = [
            'status' => $this->weSupplyMappings->getWsItemProcessingLabel(),
            'status_id' => $this->weSupplyMappings->getWsItemProcessingId()
        ];

        if (
            !array_key_exists($this->orderOrigStatusCode, $this->weSupplyMappings->getDefaultOrderStatuses()) &&
            !empty($this->availableOrderStatuses[$this->orderOrigStatusCode])
        ) {
            $currentStatus = $this->availableOrderStatuses[$this->orderOrigStatusCode];

            $itemCurrentStatus['status'] = $currentStatus->getLabel();
            $itemCurrentStatus['status_id'] = $currentStatus->getStatus();
        }

        return $itemCurrentStatus;
    }
}
