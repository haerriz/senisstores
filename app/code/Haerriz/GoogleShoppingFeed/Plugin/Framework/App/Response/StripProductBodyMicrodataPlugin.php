<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Plugin\Framework\App\Response;

use Magento\Framework\App\Response\Http;

class StripProductBodyMicrodataPlugin
{
    /**
     * Remove Magento's incomplete Product microdata so the complete JSON-LD is authoritative.
     */
    public function beforeSendResponse(Http $subject): void
    {
        $body = $subject->getBody();
        if (!is_string($body) || stripos($body, 'catalog-product-view') === false) {
            return;
        }

        $cleanBody = preg_replace_callback(
            '/<[a-z][^>]*>/i',
            function (array $matches): string {
                $tag = $matches[0];
                if (preg_match(
                    '/\sitemtype\s*=\s*(["\'])https?:\/\/schema\.org\/(?:Product|Offer|AggregateRating)\/?\1/i',
                    $tag
                ) !== 1) {
                    return $tag;
                }

                $tag = (string) preg_replace(
                    '/\sitemtype\s*=\s*(["\'])https?:\/\/schema\.org\/(?:Product|Offer|AggregateRating)\/?\1/i',
                    '',
                    $tag
                );
                $tag = (string) preg_replace(
                    '/\sitemprop\s*=\s*(["\'])(?:offers|aggregateRating)\1/i',
                    '',
                    $tag
                );

                return (string) preg_replace(
                    '/\sitemscope(?:\s*=\s*(["\'])[^"\']*\1)?/i',
                    '',
                    $tag
                );
            },
            $body
        );

        if (is_string($cleanBody) && $cleanBody !== $body) {
            $subject->setBody($cleanBody);
        }
    }
}
