<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Prompt;

class PromptRedactor
{
    private const BLOCKED_KEYS = ['identity', 'customer_id', 'client_id', 'session_id', 'cart_id', 'token', 'authorization', 'email', 'telephone', 'phone'];

    public function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array(mb_strtolower($key), self::BLOCKED_KEYS, true)) {
            return '[redacted]';
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->redact($v, is_string($k) ? $k : null);
            }
            return $out;
        }
        if (!is_string($value)) {
            return $value;
        }
        $value = mb_substr($value, 0, 2000);
        $value = preg_replace('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu', '[redacted-email]', $value) ?? $value;
        $value = preg_replace('/(?<!\d)(?:\+?\d[\s().-]*){9,15}(?!\d)/u', '[redacted-phone]', $value) ?? $value;
        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*\b/iu', 'Bearer [redacted-token]', $value) ?? $value;
        return $value;
    }
}
