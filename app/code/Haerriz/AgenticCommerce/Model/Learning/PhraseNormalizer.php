<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Learning;

/** Privacy-conscious normalizer for adaptive routing patterns. */
class PhraseNormalizer
{
    public function normalize(string $message): string
    {
        $value = mb_strtolower(trim($message));
        $value = preg_replace('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu', '<email>', $value) ?? $value;
        $value = preg_replace('#https?://\S+|www\.\S+#iu', '<url>', $value) ?? $value;
        $value = preg_replace('/(?<!\d)(?:\+?\d[\s().-]*){7,15}(?!\d)/u', '<phone>', $value) ?? $value;
        $value = preg_replace('/\b(?:order|increment|quote)\s*[#:]?\s*\d{5,}\b/u', '<order>', $value) ?? $value;
        $value = preg_replace('/\b\d+(?:\.\d+)?\b/u', '<n>', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9<>]+/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        return mb_substr($value, 0, 500);
    }
}
