<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Magento\Framework\Exception\LocalizedException;

class InputSanitizer
{
    public function __construct(private Config $config) {}

    public function message(string $message, ?int $storeId = null): string
    {
        $message = trim(preg_replace('/\s+/u', ' ', strip_tags($message)) ?? '');
        if ($message === '') {
            throw new LocalizedException(__('Please enter a message.'));
        }
        $limit = $this->config->getMaxMessageLength($storeId);
        if (mb_strlen($message) > $limit) {
            throw new LocalizedException(__('Your message is too long. Maximum length is %1 characters.', $limit));
        }
        return $message;
    }

    /**
     * Accept only storefront state that is safe to come from the browser.
     * Customer identity, permissions and recent-product references are always server-owned.
     */
    public function context(?string $context): array
    {
        if ($context === null || trim($context) === '') {
            return [];
        }
        $decoded = json_decode($context, true);
        if (!is_array($decoded)) {
            return [];
        }
        $safe = [];
        foreach (['client_id' => 80, 'conversation_id' => 80, 'cart_id' => 128, 'page_url' => 1000, 'query_phrase' => 180] as $key => $limit) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                $safe[$key] = mb_substr(trim(strip_tags((string)$decoded[$key])), 0, $limit);
            }
        }
        if (is_array($decoded['filters'] ?? null)) {
            $safe['filters'] = [];
            foreach (array_slice($decoded['filters'], 0, 30) as $filter) {
                if (!is_array($filter)) continue;
                $attribute = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($filter['attribute'] ?? '')) ?: '';
                if ($attribute === '') continue;
                $condition = strtolower((string)($filter['condition'] ?? 'eq'));
                if (!in_array($condition, ['eq', 'in', 'nin', 'match', 'from', 'to', 'range'], true)) $condition = 'eq';
                $values = [];
                foreach (array_slice((array)($filter['values'] ?? []), 0, 30) as $value) {
                    if (is_scalar($value)) $values[] = mb_substr(trim(strip_tags((string)$value)), 0, 255);
                }
                if ($values === []) continue;
                $safe['filters'][] = [
                    'attribute' => $attribute,
                    'condition' => $condition,
                    'values' => $values,
                    'label' => mb_substr(trim(strip_tags((string)($filter['label'] ?? $attribute))), 0, 120),
                ];
            }
        }
        return $safe;
    }
}
