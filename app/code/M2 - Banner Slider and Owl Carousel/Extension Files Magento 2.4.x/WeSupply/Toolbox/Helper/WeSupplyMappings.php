<?php

namespace WeSupply\Toolbox\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Model\Order;

class WeSupplyMappings extends AbstractHelper
{
    /**
     * list of WeSupply order statuses
     */
    const WESUPPLY_ORDER_PROCESSING = 1;
    const WESUPPLY_ORDER_RECEIVED = 2;
    const WESUPPLY_ORDER_COMPLETE = 3;
    const WESUPPLY_ORDER_PENDING_SHIPPING = 4;
    const WESUPPLY_ORDER_CANCELLED = 5;
    const WESUPPLY_ORDER_ONHOLD = 6;
    const WESUPPLY_ORDER_PARTIALLY_COMPLETE = 7;
    const WESUPPLY_ORDER_PAYMENT_FAILURE = 8;
    const WESUPPLY_ORDER_RETURN = 9;

    const WESUPPLY_ITEM_CANCELLED = 1;
    const WESUPPLY_ITEM_RETURN = 2;
    const WESUPPLY_ITEM_SHIPPED = 3;
    const WESUPPLY_ITEM_PROCESSING = 4;
    const WESUPPLY_ITEM_PICKUP = 15;

    const WESUPPLY_ITEM_PROCESSING_LABEL = 'Processing';


    const INSTORE_PICKUP_LABEL = 'In Store Pickup';

    const MAPPED_CARRIER_CODES = [
        'ups'     => 'UPS',
        'usps'    => 'USPS',
        'fedex'   => 'FedEx',
        'dhl'     => 'DHL',
        'instore' => self::INSTORE_PICKUP_LABEL
    ];

    const UPS_XML_MAPPINGS = [
        '11' => 'STD',     //  UPS Standard
        '14' => '1DM',     //  UPS Next Day Air Early A.M.
        '54' => 'XPR',     //  UPS Worldwide Express Plus
        '59' => '2DM',     //  UPS Second Day Air A.M.
        '65' => 'WXS',     //  UPS Worldwide Saver
        '01' => '1DA',     //  UPS Next Day Air
        '02' => '2DA',     //  UPS Second Day Air
        '03' => 'GND',     //  UPS Ground
        '07' => 'XPR',     //  UPS Worldwide Express
        '08' => 'XPD',     //  UPS Worldwide Expedited
        '12' => '3DS',     //  UPS Three-Day Select
    ];

    const ORDER_FINAL_STATUS_IDS = [
        self::WESUPPLY_ORDER_COMPLETE,
        self::WESUPPLY_ORDER_CANCELLED,
        self::WESUPPLY_ORDER_RETURN
    ];

    /**
     * @return array
     * maps Magento2 order states with WeSupply order statuses
     */
    public function mapOrderStateToWeSupplyStatusId()
    {
        return [
            Order::STATE_NEW => self::WESUPPLY_ORDER_RECEIVED,
            Order::STATE_PENDING_PAYMENT => self::WESUPPLY_ORDER_ONHOLD,
            Order::STATE_PROCESSING => self::WESUPPLY_ORDER_PROCESSING,
            Order::STATE_COMPLETE => self::WESUPPLY_ORDER_COMPLETE,
            Order::STATE_CLOSED => self::WESUPPLY_ORDER_COMPLETE,
            Order::STATE_CANCELED => self::WESUPPLY_ORDER_CANCELLED,
            Order::STATE_HOLDED => self::WESUPPLY_ORDER_ONHOLD,
            Order::STATE_PAYMENT_REVIEW => self::WESUPPLY_ORDER_PARTIALLY_COMPLETE,
        ];
    }

    /**
     * @return array
     * maps Magento2 order states with WeSupply order statuses
     */
    public function mapOrderStateToWeSupplyStatus()
    {
        return [
            Order::STATE_NEW => 'Pending',
            Order::STATE_PENDING_PAYMENT => 'On Hold',
            Order::STATE_PROCESSING => 'Processing',
            Order::STATE_COMPLETE => 'Complete',
            Order::STATE_CLOSED => 'Closed',
            Order::STATE_CANCELED => 'Cancelled',
            Order::STATE_HOLDED => 'On Hold',
            Order::STATE_PAYMENT_REVIEW => 'Partial Complete',
        ];
    }

    /**
     * @return array
     */
    public function getDefaultOrderStatuses()
    {
        return [
            Order::STATE_PENDING_PAYMENT => 'Pending Payment',
            Order::STATE_PAYMENT_REVIEW => 'Pending Review',
            Order::STATE_HOLDED => 'On Hold',
            Order::STATE_COMPLETE => 'Complete',
            Order::STATE_CLOSED => 'Closed',
            Order::STATE_CANCELED => 'Canceled',
            'processing' => 'Processing', // custom ws status
            'pending' => 'Pending' // custom ws status
        ];

    }

    /**
     * @return array
     */
    public function getMappedCarrierCodes()
    {
        return self::MAPPED_CARRIER_CODES;
    }

    /**
     * @return int
     */
    public function getWsOrderCompleteId()
    {
        return self::WESUPPLY_ORDER_COMPLETE;
    }

    /**
     * @return int
     */
    public function getWsItemProcessingId()
    {
        return self::WESUPPLY_ITEM_PROCESSING;
    }

    /**
     * @return int
     */
    public function getWsItemProcessingLabel()
    {
        return self::WESUPPLY_ITEM_PROCESSING_LABEL;
    }

    /**
     * @return string
     */
    public function getInStorePickupLabel()
    {
        return self::INSTORE_PICKUP_LABEL;
    }

    /**
     * @param $status
     *
     * @return string
     */
    public function prepareOrderStatus($status, $availableStatuses)
    {
        if (array_key_exists($status, $availableStatuses)) {
            $currentStatus = $availableStatuses[$status];
            $statusLabel = $currentStatus->getLabel();

            return ucwords($this->cleanSpecialChar($statusLabel));
        }

        return ucwords($this->cleanSpecialChar($status));
    }

    /**
     * @param $string
     *
     * @return string
     */
    private function cleanSpecialChar($string)
    {
        $string = preg_replace('/[^A-Za-z0-9\-]/', ' ', $string);
        $string = preg_replace('/\s+/', ' ', $string);

        return trim($string);
    }
}
