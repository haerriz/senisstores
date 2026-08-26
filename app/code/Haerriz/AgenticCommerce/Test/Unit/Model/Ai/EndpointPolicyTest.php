<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Ai;

use Haerriz\AgenticCommerce\Model\Ai\EndpointPolicy;
use PHPUnit\Framework\TestCase;

class EndpointPolicyTest extends TestCase
{
    public function testAllowsHttpsPublicEndpoint(): void
    {
        (new EndpointPolicy())->assertAllowed('https://api.example.com/v1');
        self::assertTrue(true);
    }

    public function testRejectsHttpByDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EndpointPolicy())->assertAllowed('http://example.com/v1');
    }

    public function testPrivateDevelopmentEndpointRequiresBothExplicitOptIns(): void
    {
        (new EndpointPolicy())->assertAllowed('http://127.0.0.1:1234/v1', true, true);
        self::assertTrue(true);
    }

    public function testRejectsPrivateNetworkWhenOnlyHttpOptInIsEnabled(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EndpointPolicy())->assertAllowed('http://127.0.0.1:1234/v1', true, false);
    }

    public function testRejectsIpv6LoopbackByDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EndpointPolicy())->assertAllowed('https://[::1]/v1');
    }

    public function testHostAllowlistRejectsUnlistedEndpoint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EndpointPolicy())->assertAllowed('https://api.example.com/v1', false, false, ['api.allowed.example']);
    }

    public function testRejectsUrlEmbeddedCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EndpointPolicy())->assertAllowed('https://user:pass@example.com/v1');
    }
}
