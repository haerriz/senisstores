<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product\Type;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class Configurable implements TypeStrategyInterface
{
    public function resolveProducts(Product $product): array
    {
        $typeInstance = $product->getTypeInstance();
        if (!method_exists($typeInstance, 'getUsedProducts')) {
            return [$product];
        }

        $children = [];
        foreach ($typeInstance->getUsedProducts($product) as $child) {
            if (!$child instanceof Product) {
                continue;
            }
            if ((int)$child->getStatus() !== Status::STATUS_ENABLED) {
                continue;
            }

            $child->setData('parent_sku', $product->getSku());
            $child->setData('item_group_id', $product->getSku());

            // Inherit parent name/image when the simple child is missing them.
            if (!trim((string)$child->getName())) {
                $child->setName($product->getName());
            }
            $childImage = (string)$child->getImage();
            if ($childImage === '' || $childImage === 'no_selection') {
                $parentImage = (string)$product->getImage();
                if ($parentImage !== '' && $parentImage !== 'no_selection') {
                    $child->setImage($parentImage);
                    if (!$child->getSmallImage() || $child->getSmallImage() === 'no_selection') {
                        $child->setSmallImage($product->getSmallImage() ?: $parentImage);
                    }
                    if (!$child->getThumbnail() || $child->getThumbnail() === 'no_selection') {
                        $child->setThumbnail($product->getThumbnail() ?: $parentImage);
                    }
                }
            }

            $this->applyConfigurableAttributes($product, $child, $typeInstance);
            $children[] = $child;
        }

        // Fall back to parent if no enabled children are available.
        return $children ?: [$product];
    }

    public function prepareData(Product $product): array
    {
        $skus = array_map(
            static fn(Product $child) => (string)$child->getSku(),
            $this->resolveProducts($product)
        );

        return [
            'sku' => (string)$product->getSku(),
            'type' => 'configurable',
            'variant_skus' => $skus,
            'item_group_id' => (string)$product->getSku(),
        ];
    }

    /**
     * Copy color/size (and similarly named) super-attribute values onto the child.
     */
    private function applyConfigurableAttributes(Product $parent, Product $child, $typeInstance): void
    {
        if (!method_exists($typeInstance, 'getConfigurableAttributes')) {
            return;
        }

        try {
            $attributes = $typeInstance->getConfigurableAttributes($parent);
        } catch (\Throwable $e) {
            return;
        }

        foreach ($attributes as $attribute) {
            $productAttribute = method_exists($attribute, 'getProductAttribute')
                ? $attribute->getProductAttribute()
                : null;
            if (!$productAttribute) {
                continue;
            }

            $code = (string)$productAttribute->getAttributeCode();
            if ($code === '') {
                continue;
            }

            $label = '';
            try {
                $label = (string)$child->getAttributeText($code);
            } catch (\Throwable $e) {
                $label = '';
            }
            if ($label === '') {
                $raw = $child->getData($code);
                $label = is_array($raw) ? implode(', ', $raw) : (string)$raw;
            }
            if ($label === '') {
                continue;
            }

            $child->setData($code, $label);

            $lower = strtolower($code);
            if ($lower === 'color' || str_contains($lower, 'color') || str_contains($lower, 'colour')) {
                $child->setData('color', $label);
            }
            if ($lower === 'size' || str_contains($lower, 'size')) {
                $child->setData('size', $label);
            }
        }
    }
}
