<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Knowledge;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Knowledge\KnowledgeService;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class KnowledgeServiceTest extends TestCase
{
    public function testCmsStoreDirectiveIsResolvedWithoutExecutingCmsMarkup(): void
    {
        $analysis = $this->analyze(
            '<a href="{{store direct_url=tax-exemption}}"'
            . ' aria-label="Unrelated legacy label opens in new window">Tax Exemption'
            . '<span class="sr-only" aria-hidden="true">sdsad</span></a>'
            . '<span aria-hidden="true">Monday - Friday: 8AM - 6PM CST</span>'
            . '{{widget type="Untrusted\\Dynamic\\Widget"}}'
            . '<script>alert(1)</script>'
        );

        self::assertSame('Tax Exemption', $analysis['links'][0]['label']);
        self::assertSame('http://shop.example/tax-exemption', $analysis['links'][0]['url']);
        self::assertStringNotContainsString('sdsad', $analysis['plain']);
        self::assertStringContainsString('Monday - Friday: 8AM - 6PM CST', $analysis['plain']);
        self::assertStringNotContainsString('alert', $analysis['plain']);
        self::assertStringNotContainsString('Untrusted', $analysis['plain']);
    }

    public function testExecutableLinkSchemesAreRejected(): void
    {
        $analysis = $this->analyze('<a href="javascript:alert(1)">Tax Exemption</a>');

        self::assertSame([], $analysis['links']);
        self::assertContains('Tax Exemption', $analysis['labels']);
    }

    private function analyze(string $html): array
    {
        $subject = new KnowledgeService(
            $this->createMock(CollectionFactory::class),
            $this->createMock(BlockCollectionFactory::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Config::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(CacheInterface::class),
            new Json()
        );
        $method = new ReflectionMethod($subject, 'analyzeContent');
        $method->setAccessible(true);
        return $method->invoke($subject, $html, 'http://shop.example/');
    }
}
