<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Recommendation;

use Haerriz\AgenticCommerce\Model\ProductPresenter;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;

class CoreRecommendationAdapter implements RecommendationAdapterInterface
{
    public function __construct(private ProductRepositoryInterface $products, private ProductPresenter $presenter, private Config $config) {}
    public function recommend(string $sku, string $type = 'related', int $limit = 6, int $storeId = 0): array
    {
        $product = $this->products->get($sku, false, $storeId, true);
        $links = match ($type) {
            'upsell' => $product->getUpSellProducts(),
            'crosssell' => $product->getCrossSellProducts(),
            default => $product->getRelatedProducts(),
        };
        $items=[];
        foreach ($links as $candidate) {
            if (!$candidate->isSalable()) continue;
            $items[]=$this->presenter->present($candidate);
            if (count($items) >= max(1,min($this->config->getMaxRecommendations($storeId),$limit))) break;
        }
        return $items;
    }
}
