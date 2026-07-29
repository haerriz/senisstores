<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Haerriz\GoogleShoppingFeed\Model\Api\MerchantClientV1;
use Google\Shopping\Merchant\Products\V1\InsertProductInputRequest;
use Google\Shopping\Merchant\Products\V1\DeleteProductInputRequest;
use Google\Shopping\Merchant\Products\V1\ProductInput;
use Google\Shopping\Merchant\Products\V1\ProductAttributes;
use Google\Shopping\Type\Price;

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
     * @return \Google\Shopping\Merchant\Products\V1\ProductInput
     */
    public function syncProduct($product, $dataSourceName, $attributesMapping = [], $mode = 'full')
    {
        $client = $this->clientV1->getProductsClient();
        $parent = 'accounts/' . $this->clientV1->getMerchantId();

        $productInput = new ProductInput();
        $productInput->setOfferId($product->getSku());
        $productInput->setContentLanguage('en');
        $productInput->setFeedLabel('IN');

        $attributes = new ProductAttributes();
        $attributes->setTitle($product->getName());
        
        $price = new Price();
        $price->setAmountMicros((int)($product->getPrice() * 1000000));
        $price->setCurrencyCode('INR');
        $attributes->setPrice($price);

        // Map stock availability
        $attributes->setAvailability($product->isSalable() ? 'in_stock' : 'out_of_stock');

        $productInput->setProductAttributes($attributes);

        $request = new InsertProductInputRequest();
        $request->setParent($parent);
        $request->setProductInput($productInput);
        $request->setDataSource($dataSourceName);


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
                'accounts/%s/productInputs/en~IN~%s',
                $this->clientV1->getMerchantId(),
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
