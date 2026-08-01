<?php
namespace Haerriz\GoogleShoppingFeed\Model\Url;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Catalog\Model\Product;

class UtmBuilder
{
    /**
     * Alias used by ProductValueResolver (requires product context for placeholders).
     */
    public function build(string $baseUrl, FeedProfileInterface $profile, ?Product $product = null): string
    {
        if ($product === null) {
            return $baseUrl;
        }
        return $this->buildUrl($baseUrl, $profile, $product);
    }

    /**
     * Build URL appending properly formatted UTM query attributes
     *
     * @param string $baseUrl
     * @param FeedProfileInterface $profile
     * @param Product $product
     * @return string
     */
    public function buildUrl($baseUrl, FeedProfileInterface $profile, Product $product)
    {
        if (!$profile->getUtmEnabled()) {
            return $baseUrl;
        }

        $params = [];
        $utmFields = [
            'getUtmSource' => 'utm_source',
            'getUtmMedium' => 'utm_medium',
            'getUtmCampaign' => 'utm_campaign',
            'getUtmTerm' => 'utm_term',
            'getUtmContent' => 'utm_content'
        ];

        foreach ($utmFields as $getter => $key) {
            $val = $profile->{$getter}();
            if (!empty($val)) {
                $val = $this->replacePlaceholders($val, $profile, $product);
                $params[$key] = urlencode($val);
            }
        }

        if (empty($params)) {
            return $baseUrl;
        }

        $urlParts = parse_url($baseUrl);
        $query = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $query);
            // Decode existing elements to avoid double encoding
            $query = array_map('urldecode', $query);
        }

        // Merge raw arrays and compile back
        foreach ($params as $k => $v) {
            $query[$k] = urldecode($v);
        }

        $urlParts['query'] = http_build_query($query);

        $newUrl = (isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '')
            . (isset($urlParts['host']) ? $urlParts['host'] : '')
            . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '')
            . (isset($urlParts['path']) ? $urlParts['path'] : '')
            . '?' . $urlParts['query']
            . (isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '');

        return $newUrl;
    }

    /**
     * Parse replacement variables
     *
     * @param string $val
     * @param FeedProfileInterface $profile
     * @param Product $product
     * @return string
     */
    protected function replacePlaceholders($val, FeedProfileInterface $profile, Product $product)
    {
        $category = '';
        $categoryIds = $product->getCategoryIds();
        if (!empty($categoryIds)) {
            $category = (string)$categoryIds[0];
        }

        $replacements = [
            '{platform}' => 'google',
            '{profile}' => (string)$profile->getName(),
            '{store}' => (string)$profile->getStoreId(),
            '{sku}' => (string)$product->getSku(),
            '{product_id}' => (string)$product->getId(),
            '{category}' => $category
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $val);
    }
}
