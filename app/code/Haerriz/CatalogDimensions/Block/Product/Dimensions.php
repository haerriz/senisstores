<?php
namespace Haerriz\CatalogDimensions\Block\Product;

class Dimensions extends \Magento\Framework\View\Element\Template
{
    private $registry;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    public function getProduct()
    {
        return $this->registry->registry('current_product');
    }

    public function getShippingDimensions()
    {
        $product = $this->getProduct();
        if (!$product) {
            return null;
        }

        $length = $product->getData('shipping_length');
        $width = $product->getData('shipping_width');
        $height = $product->getData('shipping_height');

        if ($length || $width || $height) {
            return [
                'length' => (float)$length ?: 10.0,
                'width' => (float)$width ?: 10.0,
                'height' => (float)$height ?: 10.0
            ];
        }

        return null;
    }
}
