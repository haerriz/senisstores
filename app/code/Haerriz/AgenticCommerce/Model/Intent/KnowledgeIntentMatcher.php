<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Intent;

use Haerriz\AgenticCommerce\Model\Knowledge\KnowledgeService;

/**
 * One shared grammar for read-only storefront/CMS knowledge questions.
 *
 * Planning and the defense-in-depth intent guard must use the same decision. Keeping separate
 * regular expressions caused valid CMS questions to be planned and then rejected at execution.
 */
class KnowledgeIntentMatcher
{
    public function __construct(private KnowledgeService $knowledgeService) {}

    public function matches(string $message): bool
    {
        $message = mb_strtolower(trim($message));
        if (preg_match('/^(?:go|open|take me|navigate|visit)\b/u', $message)) {
            return false;
        }
        if (preg_match('/^\s*(?:(?:what(?:\'s|s|\s+is)|calculate|solve)\s+)?[-+*\/().\d\s]+[?!.]*\s*$/u', $message)) {
            return false;
        }

        $topic = (bool)preg_match(
            '/\b(?:what\s+is\s+this\s+(?:website|site|store)\s+about|(?:website|site|store)\s+purpose|what\s+do\s+you\s+(?:sell|offer)|returns?|refunds?|shipping|ship|delivery|warranty|privacy|terms|size guide|sizing|exchanges?|replacement|damaged|cancel(?:lation)?|payment|support|donat(?:e|ion|ing)?|ways?\s+to\s+give|charitable\s+giv(?:e|ing))\b/u',
            $message
        );
        $question = (bool)preg_match(
            '/\b(?:what|how|when|where|do|does|can|could|is|are|policy|tell me|explain|happens|days)\b/u',
            $message
        ) || (bool)preg_match(
            '/\b(?:policy|returns?|refunds?|shipping|delivery|warranty|privacy|terms|exchanges?|replacement|cancellation|donat(?:e|ion|ing)?|ways?\s+to\s+give)\b/u',
            $message
        );
        if ($topic && $question) {
            return true;
        }

        $looksInformational = (bool)preg_match(
            '/^(?:what|where|when|why|how|can|could|would|tell|explain|define)\b|\b(?:link|page|policy|information|info|details?|about)\b/u',
            $message
        );
        if ($looksInformational && $this->knowledgeService->hasRelevantContent($message)) {
            return true;
        }

        // A bare phrase that exactly names an active CMS entity (for example a footer/page label)
        // is store knowledge. This is data-driven and does not require a growing PHP topic list.
        if (count(preg_split('/\s+/u', $message) ?: []) <= 8
            && $this->knowledgeService->hasExactContent($message)) {
            return true;
        }

        if (!preg_match('/^(?:what(?:\'s|s|\s+is)|define|explain|tell\s+me\s+about)\b/u', $message)) {
            return false;
        }
        return !preg_match('/\b(?:cart|basket|wishlist|order|checkout|coupon|price|stock|inventory|sku|product|shipping\s+method|payment\s+method|my\s+account|my\s+profile)\b/u', $message);
    }

    public function navigationTarget(string $message): ?string
    {
        $message = mb_strtolower(trim($message));
        if (!preg_match('/^(?:go|open|take me|navigate|visit)\s+(?:to\s+)?(?:the\s+)?(.+?)(?:\s+page)?$/u', $message, $match)) {
            return null;
        }
        $target = trim((string)$match[1]);
        return $target !== '' && $this->knowledgeService->hasExactContent($target) ? $target : null;
    }
}
