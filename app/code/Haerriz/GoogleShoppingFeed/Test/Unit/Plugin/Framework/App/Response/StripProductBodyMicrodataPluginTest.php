<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Test\Unit\Plugin\Framework\App\Response;

use Haerriz\GoogleShoppingFeed\Plugin\Framework\App\Response\StripProductBodyMicrodataPlugin;
use Magento\Framework\App\Response\Http;
use PHPUnit\Framework\TestCase;

class StripProductBodyMicrodataPluginTest extends TestCase
{
    public function testRemovesNativeProductOfferAndAggregateRatingScopes(): void
    {
        $html = '<html><body itemtype="http://schema.org/Product" itemscope="itemscope" '
            . 'class="catalog-product-view"><span itemprop="offers" itemscope '
            . 'itemtype="http://schema.org/Offer">122</span><div itemprop="aggregateRating" '
            . 'itemscope itemtype="http://schema.org/AggregateRating"><meta itemprop="ratingValue" '
            . 'content="91"></div><nav itemscope itemtype="https://schema.org/BreadcrumbList">'
            . '</nav></body></html>';

        $response = $this->createMock(Http::class);
        $response->method('getBody')->willReturn($html);
        $response->expects($this->once())->method('setBody')->with($this->callback(
            static function (string $cleanHtml): bool {
                return stripos($cleanHtml, 'schema.org/Product') === false
                    && stripos($cleanHtml, 'schema.org/Offer') === false
                    && stripos($cleanHtml, 'schema.org/AggregateRating') === false
                    && stripos($cleanHtml, 'itemprop="offers"') === false
                    && stripos($cleanHtml, 'itemprop="aggregateRating"') === false
                    && stripos($cleanHtml, 'schema.org/BreadcrumbList') !== false;
            }
        ));

        (new StripProductBodyMicrodataPlugin())->beforeSendResponse($response);
    }

    public function testLeavesNonProductPagesUnchanged(): void
    {
        $html = '<html><body itemscope itemtype="https://schema.org/WebPage" '
            . 'class="cms-index-index"></body></html>';
        $response = $this->createMock(Http::class);
        $response->method('getBody')->willReturn($html);
        $response->expects($this->never())->method('setBody');

        (new StripProductBodyMicrodataPlugin())->beforeSendResponse($response);
    }
}
