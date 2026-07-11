<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Model;

use Magento\Catalog\Model\Product;

class ProductIdResolver
{
    private const MAX_ID_LENGTH = 50;
    private const MAX_MPN_LENGTH = 70;

    /**
     * Google id [id] allows max 50 chars; Magento SKUs can exceed that.
     *
     * @param Product $product
     * @return string
     */
        public function resolveId(Product $product)
    {
        $sku = (string) $product->getSku();
        
        // Sanitize SKU for Google Merchant Center and GSC (no spaces, no brackets)
        $sku = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $sku);
        $sku = preg_replace('/-+/', '-', $sku);
        $sku = trim($sku, '-');

        if ($sku !== '' && strlen($sku) <= self::MAX_ID_LENGTH) {
            return $sku;
        }

        return (string) $product->getId();
    }

    /**
     * @param Product $product
     * @return string
     */
    public function resolveMpn(Product $product)
    {
        return $this->truncate((string) $product->getSku(), self::MAX_MPN_LENGTH);
    }

    /**
     * @param string $value
     * @param int $limit
     * @return string
     */
    private function truncate($value, $limit)
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit);
    }
}
