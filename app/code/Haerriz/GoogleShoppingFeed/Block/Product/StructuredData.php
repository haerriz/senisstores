<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Block\Product;

use Haerriz\GoogleShoppingFeed\Model\StructuredData\ProductSchemaBuilder;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Psr\Log\LoggerInterface;

class StructuredData extends Template
{
    private Registry $registry;

    private ProductSchemaBuilder $schemaBuilder;

    private LoggerInterface $logger;

    public function __construct(
        Template\Context $context,
        Registry $registry,
        ProductSchemaBuilder $schemaBuilder,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->schemaBuilder = $schemaBuilder;
        $this->logger = $logger;
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof Product ? $product : null;
    }

    public function getJsonLd(): string
    {
        $product = $this->getProduct();
        if ($product === null) {
            return '';
        }

        try {
            $schema = $this->buildForRequest($product);
            if ($schema === []) {
                return '';
            }

            $json = json_encode(
                $schema,
                JSON_HEX_TAG
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_HEX_AMP
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );

            return is_string($json) ? $json : '';
        } catch (\Throwable $exception) {
            $this->logger->warning(
                'Unable to render Google product structured data.',
                ['product_id' => $product->getId(), 'exception' => $exception]
            );

            return '';
        }
    }

    private function buildForRequest(Product $product): array
    {
        foreach (['product.info.options.swatches', 'product.info.options.configurable'] as $name) {
            $optionsBlock = $this->getLayout()->getBlock($name);
            if (!$optionsBlock instanceof Configurable) {
                continue;
            }

            $selection = $optionsBlock->getData('haerriz_selected_variant');
            if (!is_array($selection)) {
                continue;
            }

            foreach ($optionsBlock->getAllowProducts() as $child) {
                if ((int) $child->getId() === $selection['child_id']) {
                    return $this->schemaBuilder->buildSelectedVariant($product, $child, $selection);
                }
            }
        }

        return $this->schemaBuilder->build($product);
    }
}
