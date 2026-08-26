<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Haerriz\AgenticCommerce\Model\Search\SearchAdapterInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ProductSearchService implements SearchAdapterInterface
{
    private const SEARCH_STOP_WORDS = [
        'a', 'an', 'and', 'the', 'for', 'with', 'from', 'to', 'of', 'in', 'on',
        'show', 'find', 'give', 'list', 'me', 'please', 'product', 'products', 'item', 'items',
        'only', 'some', 'any', 'best', 'good', 'what', 'which', 'do', 'does', 'you', 'have', 'need', 'want', 'looking', 'available',
    ];

    public function __construct(
        private CollectionFactory $collectionFactory,
        private Visibility $visibility,
        private StoreManagerInterface $storeManager,
        private FilterNormalizer $filterNormalizer,
        private AttributeMetadataService $metadataService,
        private ProductPresenter $productPresenter,
        private Config $config,
        private LoggerInterface $logger,
        private StockHelper $stockHelper
    ) {
    }

    public function search(array $arguments): array
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $phrase = trim((string)($arguments['phrase'] ?? ''));
        $pageSize = max(1, min(24, (int)($arguments['page_size'] ?? $this->config->getPageSize($storeId))));
        $currentPage = max(1, min(100, (int)($arguments['current_page'] ?? 1)));
        $filters = $this->filterNormalizer->normalize(is_array($arguments['filters'] ?? null) ? $arguments['filters'] : []);

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $customerGroupId = max(0, (int)($arguments['_customer_group_id'] ?? 0));
        if (method_exists($collection, 'setCustomerGroupId')) {
            $collection->setCustomerGroupId($customerGroupId);
        }
        $collection->setVisibility($this->visibility->getVisibleInSearchIds());
        $selectAttributes = array_values(array_unique(array_merge(
            ['name', 'sku', 'price', 'small_image', 'image'],
            $this->metadataService->getDisplayAttributeCodes($storeId)
        )));
        $collection->addAttributeToSelect($selectAttributes);
        $collection->addMinimalPrice()->addFinalPrice()->addTaxPercents()->addUrlRewrite();

        if ($phrase !== '') {
            // Use Magento's configured search engine first (OpenSearch/Elasticsearch/DB depending on installation).
            $collection->addSearchFilter($phrase);
            // Then enforce a conservative literal relevance guard. This prevents a malformed/over-broad
            // search request from silently returning the entire catalog for unrelated text such as
            // "contact number" or "compare the first two".
            if ($this->config->isStrictSearchRelevanceEnabled($storeId)) {
                $this->applyStrictPhraseGuard($collection, $phrase, $storeId);
            }
        }

        foreach ($filters as $filter) {
            $this->applyFilter($collection, $filter);
        }

        $sort = is_array($arguments['sort'] ?? null) ? $arguments['sort'] : [];
        $sortAttribute = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($sort['attribute'] ?? '')) ?: '';
        $direction = strtoupper((string)($sort['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        if ($sortAttribute === 'bestseller') {
            $this->applyBestsellerSort($collection, $storeId, $direction);
        } elseif (in_array($sortAttribute, ['price', 'name', 'created_at'], true)) {
            $collection->addAttributeToSort($sortAttribute, $direction);
        }

        $collection->setPageSize($pageSize)->setCurPage($currentPage);
        $total = (int)$collection->getSize();
        $displayFilters = $this->displayFilters($filters, $storeId);
        $products = [];
        foreach ($collection as $product) {
            $presented = $this->productPresenter->present($product);
            $presented['match_reasons'] = $this->matchReasons($presented, $phrase, $displayFilters);
            $products[] = $presented;
        }

        return [
            'products' => $products,
            'total_count' => $total,
            'filters' => $displayFilters,
            'facets' => $this->buildFacets($collection, $storeId),
            'query_phrase' => $phrase,
            'page_info' => [
                'current_page' => $currentPage,
                'page_size' => $pageSize,
                'total_pages' => $pageSize > 0 ? (int)ceil($total / $pageSize) : 0,
            ],
        ];
    }

    private function applyStrictPhraseGuard($collection, string $phrase, int $storeId): void
    {
        $terms = $this->meaningfulTerms($phrase);
        if ($terms === []) {
            return;
        }
        $attributes = $this->metadataService->getSearchTextAttributeCodes($storeId);
        // This is a broad-result safety net, not a replacement search engine. Require at least one
        // strong literal signal in shopper-searchable text so unrelated prompts cannot degrade to the
        // whole catalog, while still allowing Magento/OpenSearch to perform stemming/synonym ranking.
        usort($terms, static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $conditions = [];
        foreach (array_slice($terms, 0, 2) as $term) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            foreach ($attributes as $attribute) {
                $conditions[] = ['attribute' => $attribute, 'like' => $like];
            }
        }
        if ($conditions !== []) {
            // Conditions in the same attribute-filter call are OR-ed. One strong term is sufficient;
            // the Magento fulltext result remains authoritative for final relevance/order.
            $collection->addAttributeToFilter($conditions);
        }
    }

    private function applyBestsellerSort($collection, int $storeId, string $direction): void
    {
        $table = $collection->getTable('sales_bestsellers_aggregated_yearly');
        $select = $collection->getSelect();
        $aggregate = $collection->getConnection()->select()
            ->from(
                ['agentic_bestseller_source' => $table],
                ['product_id', 'qty_ordered' => new \Zend_Db_Expr('SUM(qty_ordered)')]
            )
            ->where('store_id IN (?)', [0, $storeId])
            ->group('product_id');

        $select->joinLeft(
            ['agentic_bestseller' => $aggregate],
            'e.entity_id = agentic_bestseller.product_id',
            []
        );
        $select->order(new \Zend_Db_Expr(
            'COALESCE(agentic_bestseller.qty_ordered, 0) ' . ($direction === 'ASC' ? 'ASC' : 'DESC')
        ));
        $select->order('e.entity_id DESC');
    }

    private function meaningfulTerms(string $phrase): array
    {
        $normalized = mb_strtolower($phrase);
        $normalized = preg_replace('/[^\p{L}\p{N}._-]+/u', ' ', $normalized) ?? $normalized;
        $terms = preg_split('/\s+/u', trim($normalized)) ?: [];
        $terms = array_values(array_filter($terms, static function (string $term): bool {
            return mb_strlen($term) >= 2 && !in_array($term, self::SEARCH_STOP_WORDS, true);
        }));
        return array_slice(array_values(array_unique($terms)), 0, 6);
    }

    private function applyFilter($collection, array $filter): void
    {
        $attribute = $filter['attribute'];
        $values = $filter['values'];
        $condition = $filter['condition'];
        if ($attribute === 'category_id') {
            $categoryIds = array_values(array_filter(array_map('intval', $values), static fn(int $id): bool => $id > 0));
            if ($categoryIds !== []) {
                $collection->addCategoriesFilter(['in' => $categoryIds]);
            }
            return;
        }
        if ($attribute === 'stock_status') {
            $wanted = mb_strtolower((string)($values[0] ?? '1'));
            if (in_array($wanted, ['1', 'true', 'yes', 'in_stock', 'instock'], true)) {
                $this->stockHelper->addInStockFilterToCollection($collection);
            }
            // Out-of-stock-only filtering is intentionally not emulated against legacy stock tables because
            // that can disagree with MSI salability. The agent can still inspect an exact SKU's stock state.
            return;
        }
        if ($attribute === 'category_uid') {
            $categoryIds = [];
            foreach ($values as $uid) {
                $decoded = base64_decode((string)$uid, true);
                if ($decoded !== false && ctype_digit($decoded) && (int)$decoded > 0) {
                    $categoryIds[] = (int)$decoded;
                }
            }
            $categoryIds = array_values(array_unique($categoryIds));
            if ($categoryIds !== []) {
                $collection->addCategoriesFilter(['in' => $categoryIds]);
            }
            return;
        }
        if ($condition === 'range' && count($values) >= 2) {
            $collection->addAttributeToFilter($attribute, ['from' => $values[0], 'to' => $values[1]]);
            return;
        }
        $conditionMap = [
            'eq' => 'eq', 'in' => 'in', 'nin' => 'nin', 'match' => 'like', 'from' => 'gteq', 'to' => 'lteq',
        ];
        $mapped = $conditionMap[$condition] ?? 'eq';
        $value = in_array($mapped, ['in', 'nin'], true) ? $values : ($values[0] ?? '');
        if ($mapped === 'like') {
            $value = '%' . addcslashes((string)$value, '%_\\') . '%';
        }
        $collection->addAttributeToFilter($attribute, [$mapped => $value]);
    }

    private function displayFilters(array $filters, int $storeId): array
    {
        foreach ($filters as &$filter) {
            $meta = $this->metadataService->getByCode((string)$filter['attribute'], $storeId);
            if ($meta === null || empty($meta['options'])) {
                continue;
            }
            $labels = [];
            foreach ($meta['options'] as $option) {
                $labels[(string)$option['value']] = (string)$option['label'];
            }
            $filter['values'] = array_map(
                static fn(string $value): string => $labels[$value] ?? $value,
                array_map('strval', $filter['values'])
            );
        }
        unset($filter);
        return $filters;
    }

    private function buildFacets($collection, int $storeId): array
    {
        $facets = [];
        $limit = $this->config->getFacetLimit($storeId);
        foreach ($this->metadataService->getMetadata(null, $storeId) as $meta) {
            if (!($meta['is_filterable'] || $meta['is_filterable_in_search'])) {
                continue;
            }
            try {
                $data = $collection->getFacetedData($meta['code']);
            } catch (\Throwable $e) {
                $this->logger->debug('Agentic facet unavailable for ' . $meta['code'], ['exception' => $e]);
                continue;
            }
            if (!is_array($data) || $data === []) {
                continue;
            }
            $optionLabels = [];
            foreach ($meta['options'] as $option) {
                $optionLabels[(string)$option['value']] = (string)$option['label'];
            }
            $options = [];
            foreach (array_slice($data, 0, 30, true) as $value => $row) {
                $count = (int)($row['count'] ?? 0);
                if ($count <= 0) {
                    continue;
                }
                $value = (string)$value;
                $options[] = ['value' => $value, 'label' => $optionLabels[$value] ?? $value, 'count' => $count];
            }
            if ($options !== []) {
                $facets[] = ['attribute' => $meta['code'], 'label' => $meta['label'], 'options' => $options];
            }
            if (count($facets) >= $limit) {
                break;
            }
        }
        return $facets;
    }

    private function matchReasons(array $product, string $phrase, array $filters): array
    {
        $reasons = [];
        if ($phrase !== '') {
            $reasons[] = (string)__('Relevant to “%1”', mb_substr($phrase, 0, 80));
        }
        foreach (array_slice($filters, 0, 3) as $filter) {
            $label = (string)($filter['label'] ?? $filter['attribute'] ?? 'Filter');
            $values = implode(', ', array_map('strval', (array)($filter['values'] ?? [])));
            if ($values !== '') {
                $reasons[] = (string)__('Matches %1: %2', $label, $values);
            }
        }
        if (!empty($product['is_salable'])) {
            $reasons[] = (string)__('Currently available');
        }
        return array_slice(array_values(array_unique($reasons)), 0, 4);
    }
}
