<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Plugin\Framework\App\Response;

use Magento\Framework\App\Response\Http;

class StripProductBodyMicrodataPlugin
{
    /**
     * Remove legacy microdata from product pages so Google uses JSON-LD only.
     *
     * @param Http $subject
     * @return void
     */
    public function beforeSendResponse(Http $subject): void
    {
        $body = $subject->getBody();

        if (!is_string($body) || strpos($body, 'catalog-product-view') === false) {
            return;
        }

        $subject->setBody($this->stripMicrodata($body));
    }

    /**
     * @param string $html
     * @return string
     */
    private function stripMicrodata(string $html): string
    {
        $patterns = [
            '/\sitemprop=(["'])[^"']*\1/i',
            '/\sitemscope(?:=(["'])[^"']*\1)?/i',
            '/\sitemtype=(["'])[^"']*\1/i',
        ];

        foreach ($patterns as $pattern) {
            $html = (string) preg_replace($pattern, '', $html);
        }

        return $html;
    }
}
