<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const PREFIX = 'agentic_commerce/';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private EncryptorInterface $encryptor
    )
    {
    }

    private function value(string $path, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(self::PREFIX . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'general/enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isHomepageEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'general/homepage_enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAllPagesEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'general/all_pages_enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getTitle(?int $storeId = null): string
    {
        return trim((string)$this->value('general/title', $storeId));
    }

    public function getWelcomeMessage(?int $storeId = null): string
    {
        return trim((string)$this->value('general/welcome_message', $storeId));
    }

    public function getPlacementSelector(?int $storeId = null): string
    {
        return trim((string)$this->value('general/placement_selector', $storeId));
    }

    public function getPageSize(?int $storeId = null): int
    {
        return max(1, min(24, (int)$this->value('general/page_size', $storeId) ?: 8));
    }

    public function getMaxMessageLength(?int $storeId = null): int
    {
        return max(100, min(5000, (int)$this->value('general/max_message_length', $storeId) ?: 1200));
    }

    public function getRateLimit(?int $storeId = null): int
    {
        return max(1, min(300, (int)$this->value('general/rate_limit_per_minute', $storeId) ?: 30));
    }

    public function getFacetLimit(?int $storeId = null): int
    {
        return max(1, min(30, (int)$this->value('search/facet_limit', $storeId) ?: 10));
    }

    public function getCustomAttributeLimit(?int $storeId = null): int
    {
        return max(1, min(100, (int)$this->value('search/custom_attribute_limit', $storeId) ?: 20));
    }

    public function getExposedAttributes(?int $storeId = null): array
    {
        $raw = (string)$this->value('search/exposed_attributes', $storeId);
        if (trim($raw) === '') {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(
            static fn(string $code): string => trim($code),
            explode(',', $raw)
        ))));
    }

    public function getDisplayAttributes(?int $storeId = null): array
    {
        return $this->csv('search/display_attributes', $storeId);
    }

    public function getHiddenAttributes(?int $storeId = null): array
    {
        return $this->csv('search/hidden_attributes', $storeId);
    }

    public function isStrictSearchRelevanceEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'search/strict_relevance', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getGuestRetentionDays(?int $storeId = null): int
    {
        return max(1, min(3650, (int)$this->value('history/guest_retention_days', $storeId) ?: 30));
    }

    public function getCustomerRetentionDays(?int $storeId = null): int
    {
        return max(1, min(3650, (int)$this->value('history/customer_retention_days', $storeId) ?: 180));
    }

    public function getHistoryLimit(?int $storeId = null): int
    {
        return max(10, min(200, (int)$this->value('history/message_limit', $storeId) ?: 100));
    }

    public function getAccentColor(?int $storeId = null): string
    {
        $value = trim((string)$this->value('general/accent_color', $storeId));
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '';
    }

    public function getStoreLocale(?int $storeId = null): string
    {
        $locale = trim((string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId));
        return preg_match('/^[A-Za-z]{2,3}_[A-Za-z]{2,4}$/', $locale) ? $locale : 'en_US';
    }

    public function isAutoNavigationEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'general/auto_navigation', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAiProvider(?int $storeId = null): string
    {
        $value=trim((string)$this->value('ai/provider',$storeId));
        return $value!=='' && preg_match('/^[a-z0-9_\-]{1,64}$/',$value) ? $value : 'deterministic';
    }

    /** @return string[] */
    public function getAiFallbackProviders(?int $storeId = null): array
    {
        $raw = trim((string)$this->value('ai/fallback_providers', $storeId));
        if ($raw === '') {
            return [];
        }
        $primary = $this->getAiProvider($storeId);
        $items = array_values(array_unique(array_filter(array_map(
            static fn(string $provider): string => trim($provider),
            explode(',', $raw)
        ), static fn(string $provider): bool => (bool)preg_match('/^[a-z0-9_\-]{1,64}$/', $provider))));
        return array_values(array_filter($items, static fn(string $provider): bool => $provider !== $primary));
    }

    public function getDefaultReasoningMode(?int $storeId = null): string
    {
        $value=(string)$this->value('ai/default_reasoning_mode',$storeId);
        return in_array($value,['fast','balanced','deep'],true)?$value:'balanced';
    }

    public function getAiReasoningEffort(?int $storeId = null): string
    {
        $value=(string)$this->value('ai/reasoning_effort',$storeId);
        return in_array($value,['auto','minimal','low','medium','high'],true)?$value:'auto';
    }

    public function isRizAiNeuralEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'ai/neural_intent_enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRizAiNeuralMinConfidence(?int $storeId = null): float
    {
        $value = (float)$this->value('ai/neural_intent_min_confidence', $storeId);
        if ($value <= 0.0) {
            $value = 0.90;
        }
        return max(0.50, min(0.999, $value));
    }

    public function getRizAiNeuralMinMargin(?int $storeId = null): float
    {
        $value = (float)$this->value('ai/neural_intent_min_margin', $storeId);
        if ($value <= 0.0) {
            $value = 0.18;
        }
        return max(0.0, min(0.90, $value));
    }

    public function getAiMerchantInstructions(?int $storeId = null): string
    {
        return mb_substr(trim((string)$this->value('ai/merchant_instructions',$storeId)),0,8000);
    }

    public function isAiResponseSynthesisEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX.'ai/synthesize_responses',ScopeInterface::SCOPE_STORE,$storeId);
    }

    public function isInsecureAiEndpointAllowed(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'ai/allow_insecure_endpoints', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isPrivateAiEndpointAllowed(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'ai/allow_private_endpoints', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** @return string[] */
    public function getAiEndpointHostAllowlist(?int $storeId = null): array
    {
        return $this->csv('ai/endpoint_host_allowlist', $storeId);
    }

    public function getAiExternalDataScope(?int $storeId = null): string
    {
        $value=(string)$this->value('ai/external_data_scope',$storeId);
        return in_array($value,['catalog_only','commerce_without_pii','disabled'],true)?$value:'catalog_only';
    }

    public function getOpenAiEndpoint(?int $storeId = null): string
    {
        return trim((string)$this->value('ai/openai_endpoint',$storeId)) ?: 'https://api.openai.com/v1/responses';
    }
    public function getOpenAiModel(?int $storeId = null): string { return trim((string)$this->value('ai/openai_model',$storeId)); }
    public function getOpenAiApiKey(?int $storeId = null): string { return $this->encryptedValue('ai/openai_api_key',$storeId); }

    public function getGeminiEndpoint(?int $storeId = null): string
    {
        return trim((string)$this->value('ai/gemini_endpoint',$storeId)) ?: 'https://generativelanguage.googleapis.com/v1beta/models';
    }
    public function getGeminiModel(?int $storeId = null): string { return trim((string)$this->value('ai/gemini_model',$storeId)); }
    public function getGeminiApiKey(?int $storeId = null): string { return $this->encryptedValue('ai/gemini_api_key',$storeId); }

    public function getRizAiLlmEndpoint(?int $storeId = null): string
    {
        return trim((string)$this->value('ai/rizai_llm_endpoint', $storeId));
    }

    public function getRizAiLlmModel(?int $storeId = null): string
    {
        return trim((string)$this->value('ai/rizai_llm_model', $storeId));
    }

    public function getRizAiLlmApiKey(?int $storeId = null): string
    {
        return $this->encryptedValue('ai/rizai_llm_api_key', $storeId);
    }

    public function isAdaptiveLearningEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX.'learning/enabled',ScopeInterface::SCOPE_STORE,$storeId);
    }
    public function isAutoLearningReadOnlyEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX.'learning/auto_read_only',ScopeInterface::SCOPE_STORE,$storeId);
    }
    public function getLearningMinSuccesses(?int $storeId = null): int { return max(2,min(50,(int)$this->value('learning/min_successes',$storeId)?:5)); }
    public function getLearningConfidenceThreshold(?int $storeId = null): float { return max(0.5,min(1.0,(float)$this->value('learning/confidence_threshold',$storeId)?:0.9)); }
    public function getLearningRetentionDays(?int $storeId = null): int { return max(7,min(3650,(int)$this->value('learning/retention_days',$storeId)?:180)); }
    public function isFeedbackEnabled(?int $storeId = null): bool { return $this->scopeConfig->isSetFlag(self::PREFIX.'learning/feedback_enabled',ScopeInterface::SCOPE_STORE,$storeId); }

    public function getAiEndpoint(?int $storeId = null): string
    {
        return trim((string)$this->value('ai/endpoint', $storeId));
    }

    public function getAiModel(?int $storeId = null): string
    {
        return trim((string)$this->value('ai/model', $storeId));
    }

    public function getAiApiKey(?int $storeId = null): string
    {
        $value = trim((string)$this->value('ai/api_key', $storeId));
        if ($value === '') {
            return '';
        }
        try {
            return $this->encryptor->decrypt($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function getAiTimeout(?int $storeId = null): int
    {
        return max(2, min(60, (int)$this->value('ai/timeout', $storeId) ?: 15));
    }

    public function getAiMaxTokens(?int $storeId = null): int
    {
        return max(128, min(4096, (int)$this->value('ai/max_tokens', $storeId) ?: 900));
    }
    public function getMaxToolCalls(?int $storeId = null): int
    {
        return max(1, min(12, (int)$this->value('ai/max_tool_calls', $storeId) ?: 6));
    }

    public function getMaxContextTurns(?int $storeId = null): int
    {
        return max(1, min(20, (int)$this->value('ai/max_context_turns', $storeId) ?: 6));
    }

    public function isFeatureEnabled(string $feature, ?int $storeId = null): bool
    {
        if (!preg_match('/^[a-z0-9_\-]{1,64}$/', $feature)) return false;
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'features/' . $feature . '_enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isInventoryQuantityExposed(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'inventory/expose_quantity', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isInventoryOnCardsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'inventory/show_on_product_cards', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getInventoryLowStockThreshold(?int $storeId = null): float
    {
        return max(0.0, min(1000000.0, (float)$this->value('inventory/low_stock_threshold', $storeId) ?: 5.0));
    }

    public function getSearchProvider(?int $storeId = null): string
    {
        $provider=trim((string)$this->value('search/provider',$storeId));
        return $provider!=='' && preg_match('/^[a-z0-9_\-]{1,64}$/',$provider) ? $provider : 'native';
    }

    public function getLiveSearchEndpoint(?int $storeId = null): string
    {
        return trim((string)$this->value('search/live_search_endpoint', $storeId)) ?: 'https://catalog-service.adobe.io/graphql';
    }

    public function getLiveSearchApiKey(?int $storeId = null): string
    {
        $value = trim((string)$this->value('search/live_search_api_key', $storeId));
        if ($value === '') return '';
        try { return $this->encryptor->decrypt($value); } catch (\Throwable) { return $value; }
    }

    public function getLiveSearchEnvironmentId(?int $storeId = null): string
    {
        return trim((string)$this->value('search/live_search_environment_id', $storeId));
    }

    public function getAuditRetentionDays(?int $storeId = null): int
    {
        return max(7, min(3650, (int)$this->value('history/audit_retention_days', $storeId) ?: 90));
    }


    public function isProviderCircuitBreakerEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX.'ai/circuit_breaker_enabled',ScopeInterface::SCOPE_STORE,$storeId);
    }
    public function getProviderCircuitBreakerThreshold(?int $storeId = null): int
    {
        return max(1,min(20,(int)$this->value('ai/circuit_breaker_threshold',$storeId)?:3));
    }
    public function getProviderCircuitBreakerCooldown(?int $storeId = null): int
    {
        return max(10,min(3600,(int)$this->value('ai/circuit_breaker_cooldown',$storeId)?:60));
    }
    public function getProviderRequestsPerMinute(?int $storeId = null): int
    {
        $raw = $this->value('ai/provider_requests_per_minute', $storeId);
        if ($raw === null || $raw === '') return 60;
        return max(0, min(600, (int)$raw));
    }
    public function getIdempotencyTtl(?int $storeId = null): int
    {
        return max(30,min(3600,(int)$this->value('general/idempotency_ttl',$storeId)?:600));
    }

    public function getMaxComparisonProducts(?int $storeId = null): int { return max(2, min(8, (int)$this->value('limits/max_comparison_products',$storeId) ?: 4)); }
    public function getMaxProductMedia(?int $storeId = null): int { return max(1, min(48, (int)$this->value('limits/max_product_media',$storeId) ?: 24)); }
    public function getMaxProductSpecifications(?int $storeId = null): int { return max(5, min(80, (int)$this->value('limits/max_product_specifications',$storeId) ?: 40)); }
    public function getMaxQaEvidence(?int $storeId = null): int { return max(1, min(8, (int)$this->value('limits/max_qa_evidence',$storeId) ?: 4)); }
    public function getMaxSuggestions(?int $storeId = null): int { return max(1, min(8, (int)$this->value('limits/max_suggestions',$storeId) ?: 4)); }
    public function getMaxRecentOrders(?int $storeId = null): int { return max(1, min(50, (int)$this->value('limits/max_recent_orders',$storeId) ?: 10)); }
    public function getMaxRecommendations(?int $storeId = null): int { return max(1, min(24, (int)$this->value('limits/max_recommendations',$storeId) ?: 12)); }

    private function encryptedValue(string $path, ?int $storeId = null): string
    {
        $value=trim((string)$this->value($path,$storeId));
        if ($value==='') return '';
        try { return $this->encryptor->decrypt($value); } catch (\Throwable) { return $value; }
    }

    private function csv(string $path, ?int $storeId = null): array
    {
        $raw = trim((string)$this->value($path, $storeId));
        if ($raw === '') {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(
            static fn(string $code): string => trim($code),
            explode(',', $raw)
        ))));
    }

}
