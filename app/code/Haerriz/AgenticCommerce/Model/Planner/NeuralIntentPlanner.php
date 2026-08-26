<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Planner;

use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\RizAi\NeuralModelRuntime;

/**
 * Converts high-confidence RizAI neural intent predictions into bounded read-only tool plans.
 * Learned neural weights can propose intent; they never grant mutation authority or bypass ToolPolicy.
 */
final class NeuralIntentPlanner
{
    public function __construct(
        private Config $config,
        private NeuralModelRuntime $model,
        private ToolPolicy $toolPolicy
    ) {}

    public function plan(string $message, array $context = []): ?array
    {
        $storeId = (int)($context['identity']['store_id'] ?? 0);
        if (!$this->config->isRizAiNeuralEnabled($storeId)) {
            return null;
        }
        $prediction = $this->model->predict($message);
        if (empty($prediction['available'])
            || (float)$prediction['confidence'] < $this->config->getRizAiNeuralMinConfidence($storeId)
            || (float)$prediction['margin'] < $this->config->getRizAiNeuralMinMargin($storeId)) {
            return null;
        }

        $intent = (string)$prediction['intent'];
        $recent = array_values((array)($context['recent_products'] ?? []));
        $tool = '';
        $arguments = [];

        switch ($intent) {
            case 'search_products':
                $tool = 'search_products';
                $arguments = ['phrase' => trim($message), 'filters' => [], 'sort' => [], 'page_size' => $this->config->getPageSize($storeId), 'current_page' => 1];
                break;
            case 'search_categories':
                $tool = 'search_categories';
                $arguments = ['query' => trim($message), 'limit' => 5];
                break;
            case 'get_catalog_navigation':
                $tool = 'get_catalog_navigation';
                $arguments = ['limit' => 20];
                break;
            case 'get_store_information':
                $tool = 'get_store_information';
                $arguments = ['topic' => trim($message)];
                break;
            case 'answer_store_question':
                $tool = 'answer_store_question';
                $arguments = ['query' => trim($message), 'limit' => 3];
                break;
            case 'search_pages':
                $tool = 'search_pages';
                $arguments = ['query' => trim($message), 'limit' => 5];
                break;
            case 'get_cart':
                $tool = 'get_cart';
                break;
            case 'get_wishlist':
                $tool = 'get_wishlist';
                break;
            case 'get_recent_orders':
                $tool = 'get_recent_orders';
                $arguments = ['limit' => min(5, $this->config->getMaxRecentOrders($storeId))];
                break;
            case 'get_checkout_state':
                $tool = 'get_checkout_state';
                break;
            case 'get_customer_profile':
                $tool = 'get_customer_profile';
                break;
            case 'get_newsletter_status':
                $tool = 'get_newsletter_status';
                break;
            case 'product_content':
                if ($recent !== []) {
                    $tool = 'get_product_content';
                    $arguments = ['index' => 1];
                }
                break;
            case 'compare_products':
                if (count($recent) >= 2) {
                    $tool = 'compare_recent_products';
                    $arguments = ['indexes' => [1, 2], 'focus' => [], 'goal' => trim($message)];
                }
                break;
            case 'inventory':
                if ($recent !== []) {
                    $tool = 'get_inventory';
                    $arguments = ['index' => 1, 'query' => trim($message)];
                }
                break;
            case 'price':
                if ($recent !== []) {
                    $tool = 'get_product_price';
                    $arguments = ['index' => 1];
                }
                break;
            case 'recommendations':
                if ($recent !== []) {
                    $tool = 'get_recommendations';
                    $arguments = ['index' => 1, 'type' => 'related', 'limit' => 6];
                }
                break;
            case 'smalltalk':
                return $this->assistantOnlyPlan(
                    'Hello! I can help you find, compare and understand products, check store information, and work with your shopping account where supported.',
                    $prediction
                );
            case 'out_of_scope':
                return $this->assistantOnlyPlan(
                    'I am focused on this store and its shopping experience. I can help with products, prices, availability, store policies, your cart, wishlist, checkout and orders where supported.',
                    $prediction
                );
            default:
                return null;
        }

        if ($tool === '') {
            return null;
        }
        $metadata = $this->toolPolicy->metadata($tool, $storeId);
        // Neural routing is intentionally read-only in 5.0 even if a future model learns mutation labels.
        if (empty($metadata['enabled']) || !empty($metadata['mutates_state']) || empty($metadata['planner_visible'])) {
            return null;
        }

        return [
            'assistant_message' => '',
            'tools' => [['name' => $tool, 'arguments' => $arguments]],
            'neural_model' => [
                'model_id' => (string)$prediction['model_id'],
                'intent' => $intent,
                'confidence' => (float)$prediction['confidence'],
                'margin' => (float)$prediction['margin'],
            ],
        ];
    }

    /** @param array<string,mixed> $prediction */
    private function assistantOnlyPlan(string $message, array $prediction): array
    {
        return [
            'assistant_message' => $message,
            'tools' => [],
            'neural_model' => [
                'model_id' => (string)$prediction['model_id'],
                'intent' => (string)$prediction['intent'],
                'confidence' => (float)$prediction['confidence'],
                'margin' => (float)$prediction['margin'],
            ],
        ];
    }
}
