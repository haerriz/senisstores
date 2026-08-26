<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Knowledge;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Cms\Model\Block as CmsBlock;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds a safe, store-scoped search index from active Magento CMS pages and blocks.
 *
 * CMS directives are deliberately not executed here. Executing arbitrary widgets/blocks while
 * classifying a chat message can invoke dynamic or customer-aware PHP. The index reads public CMS
 * text and resolves only safe {{store ...}} URLs. Magento cache tags invalidate it when CMS data,
 * store configuration, or store relationships change.
 */
class KnowledgeService
{
    private const CACHE_VERSION = 'v6';
    private const CACHE_LIFETIME = 3600;
    private const STOP_WORDS = [
        'what', 'whats', 'your', 'you', 'are', 'the', 'and', 'for', 'with', 'from', 'this', 'that',
        'how', 'can', 'could', 'would', 'does', 'have', 'about', 'tell', 'please', 'where', 'when',
        'why', 'is', 'of', 'link', 'page', 'information', 'info', 'details', 'detail', 'find', 'show',
        'open', 'visit', 'navigate',
    ];

    /** @var array<int,array<int,array<string,mixed>>> */
    private array $documentCache = [];

    public function __construct(
        private CollectionFactory $pageCollectionFactory,
        private BlockCollectionFactory $blockCollectionFactory,
        private StoreManagerInterface $storeManager,
        private Config $config,
        private ScopeConfigInterface $scopeConfig,
        private CacheInterface $cache,
        private Json $serializer
    ) {
    }

    public function search(string $query, int $limit = 3): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isFeatureEnabled('knowledge', $storeId)) {
            throw new LocalizedException(__('Store knowledge assistant capabilities are disabled.'));
        }

        $query = trim($query);
        if ($query === '') {
            throw new LocalizedException(__('A store-information question is required.'));
        }
        $terms = $this->terms($query);
        if ($terms === []) {
            throw new LocalizedException(__('Please include a store topic to search for.'));
        }

        $ranked = [];
        $isOverview = (bool)preg_match(
            '/\b(?:website|site|store)\b.*\b(?:about|purpose)\b|\bwhat\s+do\s+you\s+(?:sell|offer)\b/iu',
            $query
        );
        $homeIdentifier = trim((string)$this->scopeConfig->getValue(
            'web/default/cms_home_page',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        foreach ($this->documents($storeId) as $position => $document) {
            $plain = (string)$document['plain'];
            $score = $this->score(
                $query,
                $terms,
                (string)$document['title'],
                (string)$document['identifier'],
                $plain
            );
            $matchedLink = $this->matchingLink((array)$document['links'], $query, $terms);
            if ($matchedLink !== null) {
                $score += (int)$matchedLink['score'];
            }
            if ($isOverview && $document['type'] === 'page'
                && $homeIdentifier !== '' && $document['identifier'] === $homeIdentifier) {
                $score += 30;
            }
            if ($score <= 0) {
                continue;
            }

            // A CMS page is the canonical explanatory source. A store-code-specific block wins
            // only where no current-store page exists, such as links rendered solely in a footer.
            $sourcePriority = $document['type'] === 'page' ? 30 : 0;
            $ranked[] = [
                'score' => $score + (int)$document['scope_weight'] + $sourcePriority,
                'source_priority' => $sourcePriority,
                'position' => $position,
                'title' => $matchedLink['label'] ?? (string)$document['title'],
                'identifier' => (string)$document['result_identifier'],
                'snippet' => $this->snippet($plain, $terms),
                'url' => $matchedLink['url'] ?? (string)$document['url'],
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            return ($right['score'] <=> $left['score'])
                ?: ($right['source_priority'] <=> $left['source_priority'])
                ?: ($left['position'] <=> $right['position']);
        });

        $items = [];
        $seenUrls = [];
        $seenLabels = [];
        foreach ($ranked as $item) {
            $urlKey = mb_strtolower(rtrim((string)$item['url'], '/'));
            $labelKey = $this->normalizeLabel((string)$item['title']);
            if (($urlKey !== '' && isset($seenUrls[$urlKey]))
                || ($labelKey !== '' && isset($seenLabels[$labelKey]))) {
                continue;
            }
            if ($urlKey !== '') {
                $seenUrls[$urlKey] = true;
            }
            if ($labelKey !== '') {
                $seenLabels[$labelKey] = true;
            }
            unset($item['score'], $item['source_priority'], $item['position']);
            $items[] = $item;
            if (count($items) >= max(1, min(5, $limit))) {
                break;
            }
        }
        return $items;
    }

    /** Extract a public fact from active CMS content most relevant to the current store. */
    public function publicFact(string $kind): string
    {
        foreach ($this->contentSources($kind) as $plain) {
            $pattern = match ($kind) {
                'address' => '/\b\d{2,6}\s+[\p{L}\d.\'’ -]{2,70}?\s+(?:Avenue|Ave|Street|St|Road|Rd|Boulevard|Blvd|Lane|Ln|Drive|Dr|Way)\.?\s*,?\s*[\p{L}.\'’ -]{2,40},?\s*[A-Z]{2}\s+\d{5}(?:-\d{4})?\b/iu',
                'phone' => '/(?<!\d)(?:\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]\d{3}[-.\s]\d{4}(?!\d)/u',
                'email' => '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu',
                'hours' => '/\b(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)(?:\s*(?:-|to|through)\s*(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday))?\s*[:,.]?\s*\d{1,2}(?::\d{2})?\s*(?:AM|PM)\s*(?:-|to)\s*\d{1,2}(?::\d{2})?\s*(?:AM|PM)(?:\s*(?:(?:Central|Eastern|Mountain|Pacific)\s+Time|[A-Z]{2,5})\b)?/iu',
                default => null,
            };
            if ($pattern !== null && preg_match($pattern, $plain, $match)) {
                return trim((string)$match[0]);
            }
        }
        return '';
    }

    public function hasRelevantContent(string $query): bool
    {
        try {
            return $this->search($query, 1) !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    /** Match entity titles/identifiers plus visible CMS headings and link labels. */
    public function hasExactContent(string $query): bool
    {
        $needle = $this->normalizeLabel($query);
        if ($needle === '') {
            return false;
        }
        $storeId = (int)$this->storeManager->getStore()->getId();
        foreach ($this->documents($storeId) as $document) {
            foreach ((array)$document['labels'] as $label) {
                if ($needle === $this->normalizeLabel((string)$label)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** @return string[] */
    private function contentSources(string $kind): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $documents = $this->documents($storeId);
        usort($documents, function (array $left, array $right) use ($kind): int {
            return $this->factSourceWeight($right, $kind) <=> $this->factSourceWeight($left, $kind);
        });
        $sources = [];
        foreach ($documents as $document) {
            $plain = trim((string)$document['plain']);
            if ($plain !== '' && !isset($sources[$plain])) {
                $sources[$plain] = $plain;
            }
        }
        return array_values($sources);
    }

    private function factSourceWeight(array $document, string $kind): int
    {
        $label = $this->normalizeLabel(
            (string)$document['identifier'] . ' ' . (string)$document['title']
        );
        $weight = (int)$document['scope_weight'];
        if ($document['type'] === 'block') {
            $weight += 25;
        }
        if (str_contains($label, 'footer')) {
            $weight += 60;
        }
        if (str_contains($label, 'contact') || str_contains($label, 'store information')) {
            $weight += 45;
        }
        $kindTerms = match ($kind) {
            'address' => ['address', 'location'],
            'phone' => ['phone', 'telephone', 'support'],
            'email' => ['email', 'support'],
            'hours' => ['hours', 'opening', 'contact'],
            default => [],
        };
        foreach ($kindTerms as $term) {
            if (str_contains($label, $term)) {
                $weight += 25;
            }
        }
        return $weight;
    }

    /** @return array<int,array<string,mixed>> */
    private function documents(int $storeId): array
    {
        if (isset($this->documentCache[$storeId])) {
            return $this->documentCache[$storeId];
        }
        $cacheId = 'HAERRIZ_AGENTIC_CMS_KNOWLEDGE_' . self::CACHE_VERSION . '_' . $storeId;
        try {
            $cached = $this->cache->load($cacheId);
            if (is_string($cached) && $cached !== '') {
                $decoded = $this->serializer->unserialize($cached);
                if (is_array($decoded)) {
                    return $this->documentCache[$storeId] = $decoded;
                }
            }
        } catch (\Throwable) {
            // Cache availability must never disable CMS answers.
        }

        $store = $this->storeManager->getStore($storeId);
        $baseUrl = rtrim($store->getBaseUrl(), '/') . '/';
        $homeIdentifier = trim((string)$this->scopeConfig->getValue(
            'web/default/cms_home_page',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $documents = [];

        $pages = $this->pageCollectionFactory->create();
        $pages->addFieldToFilter('is_active', 1)->addStoreFilter($storeId);
        foreach ($pages as $page) {
            $identifier = trim((string)$page->getIdentifier(), '/');
            $analysis = $this->analyzeContent((string)$page->getContent(), $baseUrl);
            $meta = $this->normalizeText((string)$page->getMetaDescription());
            $plain = trim($meta !== '' ? $meta . ' ' . $analysis['plain'] : $analysis['plain']);
            $title = trim((string)$page->getTitle());
            $documents[] = [
                'type' => 'page',
                'title' => $title,
                'identifier' => $identifier,
                'result_identifier' => $identifier,
                'plain' => $plain,
                'url' => $identifier === '' || $identifier === $homeIdentifier
                    ? $baseUrl
                    : $baseUrl . ltrim($identifier, '/'),
                'links' => $analysis['links'],
                'labels' => array_values(array_unique(array_filter(array_merge(
                    [$title, $identifier],
                    $analysis['labels']
                )))),
                'scope_weight' => $this->scopeWeight($identifier, (array)$page->getData('store_id'), $storeId),
            ];
        }

        $blocks = $this->blockCollectionFactory->create();
        $blocks->addFieldToFilter('is_active', 1)->addStoreFilter($storeId);
        foreach ($blocks as $block) {
            $identifier = trim((string)$block->getIdentifier());
            $analysis = $this->analyzeContent((string)$block->getContent(), $baseUrl);
            $title = trim((string)$block->getTitle());
            $documents[] = [
                'type' => 'block',
                'title' => $title,
                'identifier' => $identifier,
                'result_identifier' => 'block:' . $identifier,
                'plain' => $analysis['plain'],
                'url' => $baseUrl,
                'links' => $analysis['links'],
                'labels' => array_values(array_unique(array_filter(array_merge(
                    [$title, $identifier],
                    $analysis['labels']
                )))),
                'scope_weight' => $this->scopeWeight($identifier, (array)$block->getData('store_id'), $storeId),
            ];
        }

        $this->documentCache[$storeId] = $documents;
        try {
            $this->cache->save(
                (string)$this->serializer->serialize($documents),
                $cacheId,
                [CmsPage::CACHE_TAG, CmsBlock::CACHE_TAG, ConfigCache::CACHE_TAG, Store::CACHE_TAG],
                self::CACHE_LIFETIME
            );
        } catch (\Throwable) {
            // The freshly built in-memory index remains usable when the cache backend is unavailable.
        }
        return $documents;
    }

    /**
     * Parse public CMS HTML without executing widgets, PHP blocks, or other directives.
     *
     * @return array{plain:string,links:array<int,array{label:string,url:string}>,labels:string[]}
     */
    private function analyzeContent(string $content, string $baseUrl): array
    {
        if (trim($content) === '') {
            return ['plain' => '', 'links' => [], 'labels' => []];
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapperId = 'agentic-cms-index-root';
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><!doctype html><html><body><div id="'
                . $wrapperId . '">' . $content . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $wrapper = $loaded ? $document->getElementById($wrapperId) : null;
        if (!$wrapper instanceof DOMElement) {
            return ['plain' => $this->normalizeText(strip_tags($content)), 'links' => [], 'labels' => []];
        }

        $xpath = new DOMXPath($document);
        $hidden = $xpath->query(
            './/script|.//style|.//noscript|.//template|.//*[@hidden]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " sr-only ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " visually-hidden ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " d-none ")]'
            . '|.//*[contains(translate(@style, " ", ""), "display:none")]'
            . '|.//*[contains(translate(@style, " ", ""), "visibility:hidden")]',
            $wrapper
        );
        if ($hidden !== false) {
            foreach (iterator_to_array($hidden) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $links = [];
        $labels = [];
        $anchorNodes = $xpath->query('.//a[@href]', $wrapper);
        if ($anchorNodes !== false) {
            foreach ($anchorNodes as $anchor) {
                if (!$anchor instanceof DOMElement) {
                    continue;
                }
                // Match what the shopper can see. aria-label remains a fallback for icon-only
                // links, but must not replace visible text with suffixes such as “opens in new
                // window” or an unrelated legacy label.
                $label = $this->normalizeText($anchor->textContent);
                if ($label === '') {
                    $label = $this->normalizeText($anchor->getAttribute('aria-label'));
                }
                $url = $this->resolveSafeCmsUrl($anchor->getAttribute('href'), $baseUrl);
                if ($label !== '') {
                    $labels[] = $label;
                }
                if ($label !== '' && $url !== null) {
                    $links[] = ['label' => $label, 'url' => $url];
                }
            }
        }
        $headingNodes = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $wrapper);
        if ($headingNodes !== false) {
            foreach ($headingNodes as $heading) {
                $label = $this->normalizeText($heading->textContent);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        return [
            'plain' => $this->normalizeText($wrapper->textContent),
            'links' => $this->uniqueLinks($links),
            'labels' => array_values(array_unique($labels)),
        ];
    }

    private function resolveSafeCmsUrl(string $href, string $baseUrl): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || strlen($href) > 2048) {
            return null;
        }

        $href = preg_replace_callback(
            '/\{\{\s*store\s+(?:direct_url|url)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s}]+))\s*\}\}/iu',
            function (array $match) use ($baseUrl): string {
                $path = (string)($match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : ($match[3] ?? '')));
                return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
            },
            $href
        ) ?? $href;
        if (str_contains($href, '{{') || str_contains($href, '}}')) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $scheme = (string)(parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https');
            $href = $scheme . ':' . $href;
        } elseif (str_starts_with($href, '#')) {
            $href = rtrim($baseUrl, '/') . '/' . $href;
        } elseif (str_starts_with($href, '/')) {
            $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
        } elseif (parse_url($href, PHP_URL_SCHEME) === null) {
            $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
        }

        $scheme = mb_strtolower((string)parse_url($href, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)
            || parse_url($href, PHP_URL_USER) !== null
            || parse_url($href, PHP_URL_PASS) !== null) {
            return null;
        }
        return $href;
    }

    /** @param array<int,array{label:string,url:string}> $links */
    private function uniqueLinks(array $links): array
    {
        $unique = [];
        foreach ($links as $link) {
            $key = mb_strtolower(rtrim($link['url'], '/')) . '|' . $this->normalizeLabel($link['label']);
            $unique[$key] = $link;
        }
        return array_values($unique);
    }

    private function scopeWeight(string $identifier, array $storeIds, int $storeId): int
    {
        $storeIds = array_map('intval', $storeIds);
        $weight = in_array($storeId, $storeIds, true) ? 18 : (in_array(0, $storeIds, true) ? 2 : 0);
        $identifierLower = mb_strtolower($identifier);
        $currentCode = mb_strtolower((string)$this->storeManager->getStore($storeId)->getCode());
        if ($currentCode !== '' && str_starts_with($identifierLower, $currentCode . '_')) {
            return $weight + 30;
        }
        foreach ($this->storeManager->getStores(false) as $candidateStore) {
            $candidateCode = mb_strtolower((string)$candidateStore->getCode());
            if ($candidateCode !== '' && $candidateCode !== $currentCode
                && str_starts_with($identifierLower, $candidateCode . '_')) {
                return $weight - 30;
            }
        }
        return $weight;
    }

    private function terms(string $query): array
    {
        $normalized = mb_strtolower($query);
        $normalized = preg_replace('/[^\p{L}\p{N}\-]+/u', ' ', $normalized) ?? $normalized;
        $terms = preg_split('/\s+/u', trim($normalized)) ?: [];
        $terms = array_values(array_filter($terms, static function (string $term): bool {
            return mb_strlen($term) >= 3 && !in_array($term, self::STOP_WORDS, true);
        }));
        return array_slice(array_unique($terms), 0, 8);
    }

    private function score(string $query, array $terms, string $title, string $identifier, string $content): int
    {
        $titleLower = mb_strtolower($title);
        $identifierLower = mb_strtolower(str_replace(['-', '_'], ' ', $identifier));
        $contentLower = mb_strtolower($content);
        $score = 0;
        foreach ($terms as $term) {
            if (str_contains($titleLower, $term)) {
                $score += 8;
            }
            if (str_contains($identifierLower, $term)) {
                $score += 6;
            }
            if (str_contains($contentLower, $term)) {
                $score += 2;
            }
        }
        $queryLower = mb_strtolower(trim($query));
        if ($queryLower !== '' && str_contains($titleLower, $queryLower)) {
            $score += 10;
        }
        $topicLabel = $this->queryTopicLabel($query);
        if ($topicLabel !== '' && $this->normalizeLabel($title) === $topicLabel) {
            $score += 100;
        } elseif ($topicLabel !== '' && $this->normalizeLabel($identifier) === $topicLabel) {
            $score += 80;
        }
        return $score;
    }

    /** @param array<int,array{label:string,url:string}> $links */
    private function matchingLink(array $links, string $query, array $terms): ?array
    {
        $best = null;
        $bestScore = 0;
        $topicLabel = $this->queryTopicLabel($query);
        foreach ($links as $link) {
            $labelLower = mb_strtolower($link['label']);
            $score = 0;
            $matchedTerms = 0;
            foreach ($terms as $term) {
                if (str_contains($labelLower, $term)) {
                    $score += 12;
                    $matchedTerms++;
                }
            }
            if ($matchedTerms === count($terms) && $matchedTerms > 0) {
                $score += 20;
            }
            if ($topicLabel !== '' && $this->normalizeLabel($link['label']) === $topicLabel) {
                $score += 120;
            }
            if ($score > $bestScore) {
                $best = ['label' => $link['label'], 'url' => $link['url'], 'score' => $score];
                $bestScore = $score;
            }
        }
        return $best;
    }

    private function queryTopicLabel(string $query): string
    {
        $tokens = preg_split('/\s+/u', $this->normalizeLabel($query)) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            static fn(string $token): bool => !in_array($token, self::STOP_WORDS, true)
        ));
        return implode(' ', $tokens);
    }

    private function snippet(string $plain, array $terms): string
    {
        if ($plain === '') {
            return '';
        }
        $lower = mb_strtolower($plain);
        $position = null;
        foreach ($terms as $term) {
            $candidate = mb_strpos($lower, $term);
            if ($candidate !== false && ($position === null || $candidate < $position)) {
                $position = $candidate;
            }
        }
        $start = $position === null ? 0 : max(0, $position - 120);
        $snippet = mb_substr($plain, $start, 520);
        if ($start > 0) {
            $snippet = '…' . $snippet;
        }
        if (($start + mb_strlen($snippet)) < mb_strlen($plain)) {
            $snippet .= '…';
        }
        return $snippet;
    }

    private function normalizeText(string $value): string
    {
        $value = preg_replace('/\{\{.*?\}\}|\{\%.*?\%\}/su', ' ', $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
