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
            '/"private_key"\s*:\s*"[^"]+"/' => '"private_key": "[REDACTED]"',
            '/delivery_password\s*=\s*[^\s&]+/' => 'delivery_password=[REDACTED]',
            '/"delivery_password"\s*:\s*"[^"]+"/' => '"delivery_password": "[REDACTED]"',
            '/password\s*=\s*[^\s&]+/' => 'password=[REDACTED]',
            '/"password"\s*:\s*"[^"]+"/' => '"password": "[REDACTED]"'
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $message);
    }
}
