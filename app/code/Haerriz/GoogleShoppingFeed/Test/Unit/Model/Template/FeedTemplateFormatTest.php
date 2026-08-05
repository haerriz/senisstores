<?php
declare(strict_types=1);

namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Template;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;
use Haerriz\GoogleShoppingFeed\Model\Template\Amazon\CatalogV1 as AmazonCatalogV1;
use Haerriz\GoogleShoppingFeed\Model\Template\Ebay\InventoryV1 as EbayInventoryV1;
use Haerriz\GoogleShoppingFeed\Model\Template\Google\ShoppingV1 as GoogleShoppingV1;
use Haerriz\GoogleShoppingFeed\Model\Template\Instagram\CatalogV1 as InstagramCatalogV1;
use Haerriz\GoogleShoppingFeed\Model\Template\Meta\CatalogV1 as MetaCatalogV1;
use Haerriz\GoogleShoppingFeed\Model\Template\Microsoft\MerchantV1 as MicrosoftMerchantV1;
use Haerriz\GoogleShoppingFeed\Model\Template\OpenAi\CommerceV1 as OpenAiCommerceV1;
use Haerriz\GoogleShoppingFeed\Model\Template\Pinterest\CatalogV1 as PinterestCatalogV1;
use Haerriz\GoogleShoppingFeed\Model\Template\Rakuten\CatalogV1 as RakutenCatalogV1;
use Haerriz\GoogleShoppingFeed\Model\Template\Snapchat\CatalogV1 as SnapchatCatalogV1;
use Haerriz\GoogleShoppingFeed\Model\Template\TikTok\CatalogV1 as TikTokCatalogV1;
use PHPUnit\Framework\TestCase;

class FeedTemplateFormatTest extends TestCase
{
    /**
     * @dataProvider templateProvider
     */
    public function testTemplatesImplementTheirDeclaredFormat(
        FeedTemplateInterface $template,
        string $expectedFormat
    ): void {
        self::assertSame($expectedFormat, $template->getFormat());
    }

    /**
     * @return array<string, array{FeedTemplateInterface, string}>
     */
    public function templateProvider(): array
    {
        return [
            'amazon' => [new AmazonCatalogV1(), 'csv'],
            'ebay' => [new EbayInventoryV1(), 'csv'],
            'google' => [new GoogleShoppingV1(), 'xml'],
            'instagram' => [new InstagramCatalogV1(), 'csv'],
            'meta' => [new MetaCatalogV1(), 'csv'],
            'microsoft' => [new MicrosoftMerchantV1(), 'xml'],
            'openai' => [new OpenAiCommerceV1(), 'jsonl.gz'],
            'pinterest' => [new PinterestCatalogV1(), 'csv'],
            'rakuten' => [new RakutenCatalogV1(), 'csv'],
            'snapchat' => [new SnapchatCatalogV1(), 'csv'],
            'tiktok' => [new TikTokCatalogV1(), 'csv'],
        ];
    }
}
