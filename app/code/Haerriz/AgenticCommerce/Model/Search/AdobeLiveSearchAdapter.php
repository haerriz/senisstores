<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Search;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

class AdobeLiveSearchAdapter implements SearchAdapterInterface
{
    public function __construct(
        private Config $config,
        private Curl $curl,
        private StoreManagerInterface $storeManager,
        private PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function search(array $arguments): array
    {
        $store = $this->storeManager->getStore();
        $apiKey = $this->config->getLiveSearchApiKey((int)$store->getId());
        $environmentId = $this->config->getLiveSearchEnvironmentId((int)$store->getId());
        if ($apiKey === '' || $environmentId === '') {
            throw new LocalizedException(__('Adobe Live Search credentials are not configured.'));
        }
        $pageSize = max(1, min(24, (int)($arguments['page_size'] ?? 8)));
        $currentPage = max(1, (int)($arguments['current_page'] ?? 1));
        $phrase = trim((string)($arguments['phrase'] ?? ''));
        $sort = [];
        if (!empty($arguments['sort']['attribute'])) {
            $sort[] = ['attribute' => (string)$arguments['sort']['attribute'], 'direction' => strtoupper((string)($arguments['sort']['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC'];
        }
        $filters = $this->toSearchClauses((array)($arguments['filters'] ?? []));
        $query = <<<'GQL'
query AgenticProductSearch($phrase: String!, $pageSize: Int!, $currentPage: Int!, $sort: [ProductSearchSortInput!], $filter: [SearchClauseInput!]) {
  productSearch(phrase: $phrase, page_size: $pageSize, current_page: $currentPage, sort: $sort, filter: $filter) {
    total_count
    page_info { current_page page_size total_pages }
    facets { title attribute buckets { title __typename ... on ScalarBucket { id count } } }
    items {
      productView {
        id name sku url urlKey addToCartAllowed images(roles:["small_image","thumbnail"]) { url roles label }
        attributes(roles:["show_on_plp","show_in_search"]) { name label value }
        ... on SimpleProductView { price { final { amount { value currency } } regular { amount { value currency } } } }
        ... on ComplexProductView { priceRange { minimum { final { amount { value currency } } regular { amount { value currency } } } } }
      }
    }
  }
}
GQL;
        $website = $store->getWebsite();
        $this->curl->setTimeout(10);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('X-Api-Key', $apiKey);
        $this->curl->addHeader('Magento-Environment-Id', $environmentId);
        $this->curl->addHeader('Magento-Website-Code', (string)$website->getCode());
        $this->curl->addHeader('Magento-Store-Code', (string)$store->getGroup()->getCode());
        $this->curl->addHeader('Magento-Store-View-Code', (string)$store->getCode());
        $this->curl->post($this->config->getLiveSearchEndpoint((int)$store->getId()), json_encode(['query'=>$query,'variables'=>['phrase'=>$phrase,'pageSize'=>$pageSize,'currentPage'=>$currentPage,'sort'=>$sort ?: null,'filter'=>$filters ?: null]], JSON_UNESCAPED_SLASHES));
        if ($this->curl->getStatus() < 200 || $this->curl->getStatus() >= 300) {
            throw new LocalizedException(__('Adobe Live Search returned HTTP %1.', $this->curl->getStatus()));
        }
        $data = json_decode($this->curl->getBody(), true);
        if (!empty($data['errors']) || !is_array($data['data']['productSearch'] ?? null)) {
            throw new LocalizedException(__('Adobe Live Search returned an invalid response.'));
        }
        $search = $data['data']['productSearch'];
        $products = [];
        foreach ((array)($search['items'] ?? []) as $item) {
            $p = (array)($item['productView'] ?? []);
            if (empty($p['sku'])) continue;
            $amount = $p['price']['final']['amount']['value'] ?? $p['priceRange']['minimum']['final']['amount']['value'] ?? 0;
            $regular = $p['price']['regular']['amount']['value'] ?? $p['priceRange']['minimum']['regular']['amount']['value'] ?? $amount;
            $image = null;
            foreach ((array)($p['images'] ?? []) as $img) { if (!empty($img['url'])) { $image = (string)$img['url']; break; } }
            $attrs = [];
            foreach ((array)($p['attributes'] ?? []) as $attr) {
                $value = $attr['value'] ?? '';
                if (is_array($value)) $value = implode(', ', array_map('strval', $value));
                $attrs[] = ['code'=>(string)($attr['name']??''),'label'=>(string)($attr['label']??$attr['name']??''),'value'=>(string)$value];
            }
            $products[] = ['id'=>0,'sku'=>(string)$p['sku'],'name'=>(string)($p['name']??$p['sku']),'url'=>(string)($p['url']??''),'image'=>$image,'price'=>(float)$amount,'regular_price'=>(float)$regular,'formatted_price'=>$this->priceCurrency->format((float)$amount,false),'formatted_regular_price'=>$this->priceCurrency->format((float)$regular,false),'is_salable'=>(bool)($p['addToCartAllowed'] ?? true),'match_reasons'=>array_values(array_filter([$phrase !== '' ? 'Matches “'.$phrase.'”' : ''])),'custom_attributes'=>$attrs];
        }
        $facets = [];
        foreach ((array)($search['facets'] ?? []) as $facet) {
            $options=[]; foreach ((array)($facet['buckets']??[]) as $bucket) { if(isset($bucket['id'])) $options[]=['value'=>(string)$bucket['id'],'label'=>(string)($bucket['title']??$bucket['id']),'count'=>(int)($bucket['count']??0)]; }
            if($options) $facets[]=['attribute'=>(string)($facet['attribute']??''),'label'=>(string)($facet['title']??''),'options'=>$options];
        }
        return ['products'=>$products,'total_count'=>(int)($search['total_count']??0),'filters'=>(array)($arguments['filters']??[]),'facets'=>$facets,'query_phrase'=>$phrase,'page_info'=>(array)($search['page_info']??['current_page'=>$currentPage,'page_size'=>$pageSize,'total_pages'=>0]),'search_provider'=>'adobe_live_search'];
    }

    private function toSearchClauses(array $filters): array
    {
        $clauses=[];
        foreach ($filters as $filter) {
            if (!is_array($filter) || empty($filter['attribute'])) continue;
            $attribute=(string)$filter['attribute']; $values=array_values(array_map('strval',(array)($filter['values']??[]))); $condition=(string)($filter['condition']??'eq');
            if (!$values) continue;
            if ($condition === 'to') $clauses[]=['attribute'=>$attribute,'range'=>['to'=>(float)$values[0]]];
            elseif ($condition === 'from') $clauses[]=['attribute'=>$attribute,'range'=>['from'=>(float)$values[0]]];
            elseif ($condition === 'range' && count($values)>=2) $clauses[]=['attribute'=>$attribute,'range'=>['from'=>(float)$values[0],'to'=>(float)$values[1]]];
            elseif ($condition === 'nin') { continue; }
            else $clauses[]=['attribute'=>$attribute,'in'=>$values];
        }
        return $clauses;
    }
}
