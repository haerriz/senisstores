<?php
namespace WeSupply\Toolbox\Model\Config\Source;

class CapturePaymentType implements \Magento\Framework\Option\ArrayInterface
{

    const PAYMENT_ONLINE = 'online';
    const PAYMENT_OFFLINE ='offline';
    const PAYMENT_NO_CAPTURE = 'not_capture';

    public function toOptionArray()
    {
        return [
            ['value' => self::PAYMENT_ONLINE, 'label' => __('Capture Online')],
            ['value' => self::PAYMENT_OFFLINE, 'label' => __('Capture Offline')],
            ['value' => self::PAYMENT_NO_CAPTURE, 'label' => __('Not Capture')]
        ];
    }
}
