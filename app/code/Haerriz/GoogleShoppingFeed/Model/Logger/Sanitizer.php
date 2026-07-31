<?php
namespace Haerriz\GoogleShoppingFeed\Model\Logger;

class Sanitizer
{
    /**
     * Redact sensitive details (passwords, private API keys, client configs) from messages
     *
     * @param string $message
     * @return string
     */
    public function sanitize($message)
    {
        if (empty($message)) {
            return $message;
        }

        $patterns = [
            '/-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----/s' => '[REDACTED PRIVATE KEY]',
            '/"private_key"\s*:\s*"[^"]+"/i' => '"private_key": "[REDACTED]"',
            '/\b(delivery_password|password|passphrase|private_key|access_token|refresh_token|client_secret|api_key)\s*=\s*[^\s&]+/i'
                => '$1=[REDACTED]',
            '/"(delivery_password|password|passphrase|private_key|access_token|refresh_token|client_secret|api_key)"\s*:\s*"[^"]*"/i'
                => '"$1": "[REDACTED]"',
            '/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i' => 'Bearer [REDACTED]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $message);
    }
}
