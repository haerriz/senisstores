<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Ai;

use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Ai\ExternalFactPolicy;
use Haerriz\AgenticCommerce\Model\Ai\ResponseProviderInterface;
use Haerriz\AgenticCommerce\Model\Ai\ResponseSynthesisService;
use Haerriz\AgenticCommerce\Model\Config;
use PHPUnit\Framework\TestCase;

class ResponseSynthesisServiceTest extends TestCase
{
    /** @dataProvider cmsTools */
    public function testCmsFactsKeepAuthoritativeMagentoWording(string $tool): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isAiResponseSynthesisEnabled')->willReturn(true);
        $config->method('getAiProvider')->willReturn('openai');
        $provider = $this->createMock(ResponseProviderInterface::class);
        $provider->expects(self::never())->method('synthesize');
        $policy = $this->createMock(ToolPolicy::class);
        $policy->method('mutatesState')->willReturn(false);
        $subject = new ResponseSynthesisService(
            $config,
            $provider,
            $this->createMock(ExternalFactPolicy::class),
            $policy
        );

        self::assertSame(
            'I found the Tax Exemption link.',
            $subject->synthesize(
                'link of Tax Exemption',
                ['knowledge' => [['title' => 'Tax Exemption']]],
                'I found the Tax Exemption link.',
                [$tool],
                ['store_id' => 1]
            )
        );
    }

    public function cmsTools(): array
    {
        return [
            'knowledge answer' => ['answer_store_question'],
            'CMS navigation' => ['search_pages'],
        ];
    }
}
