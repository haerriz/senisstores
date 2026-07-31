<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttribute implements OptionSourceInterface
{
    private $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray()
    {
        $options = [
            ['value' => '', 'label' => __('-- Select a product value --')],
            ['value' => 'url', 'label' => __('Special: Product URL')],
            ['value' => 'final_price', 'label' => __('Special: Final Price')],
            ['value' => 'image', 'label' => __('Special: Main Image URL')],
            ['value' => 'availability', 'label' => __('Special: Availability')],
            ['value' => 'parent_fallback', 'label' => __('Special: Parent Fallback')],
        ];
        $attributes = $this->collectionFactory->create();
        $attributes->setOrder('frontend_label', 'ASC');
        foreach ($attributes as $attribute) {
            $code = (string)$attribute->getAttributeCode();
            $label = trim((string)$attribute->getFrontendLabel()) ?: $code;
            $options[] = [
                'value' => $code,
                'label' => sprintf(
                    '%s [%s, %s, %s]',
                    $label,
                    $code,
                    $attribute->getBackendType(),
                    $attribute->getIsGlobal() ? 'global' : 'store'
                ),
            ];
        }
        return $options;
    }
}
