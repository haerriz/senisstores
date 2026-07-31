<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Logger;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Logger\Sanitizer;

class SanitizerTest extends TestCase
{
    protected $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new Sanitizer();
    }

    public function testSanitizeRedactsPassword()
    {
        $message = "Connecting with delivery_password=SecretPassword123 & host=localhost";
        $sanitized = $this->sanitizer->sanitize($message);
        $this->assertStringNotContainsString('SecretPassword123', $sanitized);
        $this->assertStringContainsString('delivery_password=[REDACTED]', $sanitized);
    }

    public function testSanitizeRedactsPrivateKey()
    {
        $message = 'Credentials: {"type": "service_account", "private_key": "-----BEGIN PRIVATE KEY-----abc123xyz-----END KEY-----"}';
        $sanitized = $this->sanitizer->sanitize($message);
        $this->assertStringNotContainsString('abc123xyz', $sanitized);
        $this->assertStringContainsString('"private_key": "[REDACTED]"', $sanitized);
    }

    public function testSanitizeRedactsTokensAndPassphrases()
    {
        $message = 'Authorization: Bearer abc.def.ghi passphrase=topsecret '
            . '{"refresh_token":"refresh-me","client_secret":"client-secret"}';
        $sanitized = $this->sanitizer->sanitize($message);

        $this->assertStringNotContainsString('abc.def.ghi', $sanitized);
        $this->assertStringNotContainsString('topsecret', $sanitized);
        $this->assertStringNotContainsString('refresh-me', $sanitized);
        $this->assertStringNotContainsString('client-secret', $sanitized);
        $this->assertStringContainsString('Bearer [REDACTED]', $sanitized);
    }

    public function testSanitizeRedactsMultilinePem()
    {
        $message = "key=-----BEGIN RSA PRIVATE KEY-----\nsecret\n-----END RSA PRIVATE KEY-----";
        $sanitized = $this->sanitizer->sanitize($message);

        $this->assertStringNotContainsString('secret', $sanitized);
        $this->assertStringContainsString('[REDACTED PRIVATE KEY]', $sanitized);
    }
}
