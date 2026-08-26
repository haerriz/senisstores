<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Agent;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\ModuleMetadata;
use Haerriz\AgenticCommerce\Model\Tool\ToolRegistry;
use Haerriz\AgenticCommerce\Model\Ai\ProviderRegistry;
use Haerriz\AgenticCommerce\Model\Search\SearchAdapterRegistry;
use Haerriz\AgenticCommerce\Model\RizAi\NeuralModelRuntime;

class CapabilityService
{
    public function __construct(
        private Config $config,
        private ToolRegistry $toolRegistry,
        private ToolPolicy $toolPolicy,
        private ExtensionCapabilityRegistry $extensionCapabilities,
        private ProviderRegistry $providerRegistry,
        private SearchAdapterRegistry $searchRegistry,
        private NeuralModelRuntime $rizAiModel
    ) {
    }

    public function get(?int $storeId = null): array
    {
        $tools = [];
        foreach ($this->toolRegistry->getDefinitions() as $definition) {
            $name = (string)($definition['function']['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $meta = $this->toolPolicy->metadata($name, $storeId);
            if (!$meta['enabled']) {
                continue;
            }
            $tools[] = [
                'name' => $name,
                'description' => (string)($definition['function']['description'] ?? ''),
                'category' => $meta['category'],
                'risk_level' => $meta['risk_level'],
                'mutates_state' => $meta['mutates_state'],
                'requires_customer' => $meta['requires_customer'],
                'requires_confirmation' => $meta['requires_confirmation'],
                'idempotent' => $meta['idempotent'],
                'planner_visible' => $meta['planner_visible'],
            ];
        }

        $rizAi = $this->rizAiModel->metadata();

        return [
            'module'=>ModuleMetadata::MODULE,
            'module_version'=>ModuleMetadata::VERSION,
            'api_version'=>ModuleMetadata::API_VERSION,
            'enabled' => $this->config->isEnabled($storeId),
            'search_provider' => $this->config->getSearchProvider($storeId),
            'ai_provider' => $this->config->getAiProvider($storeId),
            'registered_ai_providers'=>$this->providerRegistry->getCodes(),
            'registered_search_providers'=>array_values(array_map(static fn(array $o):string=>(string)$o['value'],$this->searchRegistry->getOptions())),
            'ai_fallback_providers' => $this->config->getAiFallbackProviders($storeId),
            'external_data_scope' => $this->config->getAiExternalDataScope($storeId),
            'reasoning_mode' => $this->config->getDefaultReasoningMode($storeId),
            'rizai_model_id' => (string)($rizAi['model_id'] ?? ''),
            'rizai_model_type' => (string)($rizAi['model_type'] ?? ''),
            'rizai_neural_available' => !empty($rizAi['available']),
            'rizai_neural_checksum_verified' => !empty($rizAi['checksum_verified']),
            'rizai_neural_enabled' => $this->config->isRizAiNeuralEnabled($storeId),
            'adaptive_learning' => $this->config->isAdaptiveLearningEnabled($storeId),
            'response_synthesis' => $this->config->isAiResponseSynthesisEnabled($storeId),
            'channels' => ['luma', 'hyva', 'graphql', 'rest', 'headless', 'pwa', 'pwa_studio', 'venia'],
            'features' => array_values(array_filter([
                'catalog_search', 'custom_eav_filters', 'conversation_history', 'guest_cart', 'customer_cart',
                $this->config->isFeatureEnabled('coupons', $storeId) ? 'coupons' : null,
                $this->config->isFeatureEnabled('wishlist', $storeId) ? 'wishlist' : null,
                $this->config->isFeatureEnabled('orders', $storeId) ? 'orders' : null,
                $this->config->isFeatureEnabled('recommendations', $storeId) ? 'recommendations' : null,
                $this->config->isFeatureEnabled('knowledge', $storeId) ? 'cms_knowledge' : null,
                $this->config->isFeatureEnabled('audit', $storeId) ? 'tool_audit' : null,
                'grounded_store_profile', 'store_profile_rest', 'store_profile_graphql', 'store_profile_provider_extensions',
                'safe_navigation', 'tool_governance', 'planner_context_redaction', 'search_adapter', 'mcp_style_tool_registry',
                'commerce_context_graph', 'bounded_multi_step_reasoning', 'rizai_neural_intent_model', 'hybrid_neuro_symbolic_planning', 'adaptive_routing_memory', 'shopper_feedback', 'metadata_driven_tool_policy', 'enterprise_authorization_hooks', 'provider_registry', 'search_adapter_registry', 'planner_rule_extensions', 'provider_circuit_breaker', 'provider_request_budget', 'durable_idempotency', 'direct_action_idempotency', 'telemetry_hooks', 'telemetry_processors', 'extension_response_envelope', 'locale_context', 'openai_responses', 'google_gemini', 'external_response_synthesis', 'product_option_orchestration', 'product_experience_snapshot', 'product_description_intelligence', 'grounded_product_qa', 'rich_product_comparison', 'description_based_comparison', 'batch_inventory', 'inventory_fulfillment_rules', 'variant_availability', 'customer_group_pricing', 'secure_auth_navigation', 'checkout_state_machine', 'confirmation_gate', 'customer_addresses', 'newsletter', 'product_reviews', 'product_alerts', 'store_context', 'extension_tool_registry',
            ])),
            'tools' => $tools,
            'extensions' => $this->extensionCapabilities->get($storeId),
        ];
    }
}
