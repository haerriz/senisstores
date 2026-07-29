<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DeliveryType implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'local', 'label' => __('Local Storage Only')],
            ['value' => 'ftp', 'label' => __('FTP Server')],
            ['value' => 'sftp', 'label' => __('SFTP Server')]
        ];
    }
}
