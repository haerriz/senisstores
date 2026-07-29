<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Haerriz\GoogleShoppingFeed\Model\Api\MerchantClientV1;
use Google\Shopping\Merchant\Products\V1beta\InsertProductInputRequest;
use Google\Shopping\Merchant\Products\V1beta\DeleteProductInputRequest;
use Google\Shopping\Merchant\Products\V1beta\ProductInput;
use Google\Shopping\Merchant\Products\V1beta\Attributes;
use Google\Shopping\Merchant\Products\V1beta\Price;
use Google\Protobuf\FieldMask;

class ProductSynchronizer
{
    /**
     * @var MerchantClientV1
     */
    protected $clientV1;

    /**
     * @param MerchantClientV1 $clientV1
     */
    public function __construct(MerchantClientV1 $clientV1)
    {
        $this->clientV1 = $clientV1;
    }

    /**
     * Synchronize product input (insert or patch mode)
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $dataSourceName
     * @param array $attributesMapping
     * @param string $mode 'full' or 'patch'
     * @return \Google\Shopping\Merchant\Products\V1beta\ProductInput
     */
    public function syncProduct($product, $dataSourceName, $attributesMapping = [], $mode = 'full')
    {
        $client = $this->clientV1->getProductsClient();
        $parent = 'accounts/' . $this->clientV1->getMerchantId();

        $productInput = new ProductInput();
        $productInput->setChannel(1); // Online channel enum
        $productInput->setOfferId($product->getSku());
        $productInput->setContentLanguage('en');
        $productInput->setFeedLabel('US');

        $attributes = new Attributes();
        $attributes->setTitle($product->getName());
        
        $price = new Price();
        $price->setAmountMicros((int)($product->getPrice() * 1000000));
        $price->setCurrencyCode('USD');
        $attributes->setPrice($price);

        // Map stock availability
        $attributes->setAvailability($product->isSalable() ? 'in stock' : 'out of stock');

        $productInput->setAttributes($attributes);

        $request = new InsertProductInputRequest();
        $request->setParent($parent);
        $request->setProductInput($productInput);
        $request->setDataSource($dataSourceName);

        if ($mode === 'patch') {
            // Apply field mask path constraints for price and availability updates only
            $mask = new FieldMask();
            $mask->setPaths(['attributes.price', 'attributes.availability']);
            $request->setUpdateMask($mask);
        }

        return $client->insertProductInput($request);
    }

    /**
     * Delete product from Merchant Center datasource
     *
     * @param string $sku
     * @param string $dataSourceName
     * @return bool
     */
    public function deleteProduct($sku, $dataSourceName)
    {
        try {
            $client = $this->clientV1->getProductsClient();
            $name = sprintf(
                'accounts/%s/productInputs/%s~en~US~%s',
                $this->clientV1->getMerchantId(),
                'online',
                $sku
            );

            $request = new DeleteProductInputRequest();
            $request->setName($name);
            $request->setDataSource($dataSourceName);

            $client->deleteProductInput($request);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
