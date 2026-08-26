<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Recommendation;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\Exception\LocalizedException;
class RecommendationService
{
    public function __construct(private RecommendationAdapterInterface $adapter, private Config $config) {}
    public function forSku(string $sku, string $type = 'related', int $limit = 6, int $storeId = 0): array
    {
        if (!$this->config->isFeatureEnabled('recommendations', $storeId)) { throw new LocalizedException(__('Recommendation assistant capabilities are disabled.')); }
        $type = in_array($type, ['related','upsell','crosssell'], true) ? $type : 'related';
        $items = $this->adapter->recommend($sku, $type, $limit, $storeId);
        if (!$items) throw new LocalizedException(__('No configured %1 recommendations are available for this product.', $type));
        return $items;
    }
}
