<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Haerriz\AgenticCommerce\Api\AgentInterface;
use Haerriz\AgenticCommerce\Model\Conversation\ConversationRepository;
use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Audit\ToolAuditLogger;
use Haerriz\AgenticCommerce\Model\Learning\AdaptiveLearningService;
use Haerriz\AgenticCommerce\Model\Ai\ResponseSynthesisService;
use Haerriz\AgenticCommerce\Model\Identity\IdentityResolver;
use Haerriz\AgenticCommerce\Model\Planner\PlannerInterface;
use Haerriz\AgenticCommerce\Api\ToolIntentGuardInterface;
use Haerriz\AgenticCommerce\Model\Observability\TelemetryEmitter;
use Haerriz\AgenticCommerce\Model\Tool\ToolRegistry;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AgentService implements AgentInterface
{
    public function __construct(
        private Config $config,
        private StoreManagerInterface $storeManager,
        private InputSanitizer $sanitizer,
        private RateLimiter $rateLimiter,
        private PlannerInterface $planner,
        private ToolRegistry $toolRegistry,
        private IdentityResolver $identityResolver,
        private ConversationRepository $conversations,
        private ToolPolicy $toolPolicy,
        private ToolAuditLogger $toolAudit,
        private AdaptiveLearningService $learning,
        private ResponseSynthesisService $responseSynthesis,
        private SuggestionService $suggestionService,
        private TelemetryEmitter $telemetry,
        private EventManagerInterface $eventManager,
        private LoggerInterface $logger,
        private array $intentGuards = []
    ) {
    }

    /** REST entrypoint. sessionId is a legacy alias for the opaque anonymous client id. */
    public function chat(string $message, ?string $sessionId = null, ?string $context = null): array
    {
        $clientContext = $this->sanitizer->context($context);
        $clientContext['client_id'] = $clientContext['client_id'] ?? $sessionId;
        return $this->chatWithIdentity($message, $clientContext, null, 'rest');
    }

    public function chatWithIdentity(string $message, array $clientContext = [], ?int $trustedCustomerId = null, string $channel = 'storefront'): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $traceId=$this->telemetry->traceId();
        $turnStarted=microtime(true);
        $this->telemetry->emit('turn.started',['trace_id'=>$traceId,'store_id'=>$storeId,'channel'=>$channel]);
        if (!$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('The shopping assistant is disabled.'));
        }
        $message = $this->sanitizer->message($message, $storeId);
        $identity = $this->identityResolver->resolve($trustedCustomerId, $clientContext['client_id'] ?? null, $channel);
        $this->conversations->claimGuestConversations($identity);
        $rateKey = (int)$identity['customer_id'] > 0 ? 'customer-' . (int)$identity['customer_id'] : (string)$identity['client_id'];
        $this->rateLimiter->assertAllowed($rateKey, $storeId);

        $conversation = $this->conversations->getOrStart(
            isset($clientContext['conversation_id']) ? (string)$clientContext['conversation_id'] : null,
            $identity
        );
        $conversationId = (int)$conversation['conversation_id'];
        $context = is_array($conversation['context'] ?? null) ? $conversation['context'] : [];
        $context = $this->mergeSafeClientContext($context, $clientContext);
        $context['identity'] = $identity;
        $context['cart_id'] = isset($clientContext['cart_id']) ? trim((string)$clientContext['cart_id']) : ($context['cart_id'] ?? null);
        $context['conversation_public_id'] = (string)$conversation['public_id'];
        $context['locale'] = $this->config->getStoreLocale($storeId);
        $context['channel'] = $channel;

        $this->conversations->appendMessage($conversationId, 'user', $message);
        $this->conversations->updateTitleIfNew($conversationId, $message);

        $plan = $this->planner->plan($message, $context);
        $response = $this->emptyResponse((string)$conversation['public_id'], (string)$identity['client_id'], $context, $identity);
        $response['trace_id']=$traceId;
        $toolMessages = [];
        $executedTools = [];

        foreach (array_slice((array)($plan['tools'] ?? []), 0, $this->config->getMaxToolCalls($storeId)) as $toolCall) {
            if (!is_array($toolCall)) {
                continue;
            }
            $name = (string)($toolCall['name'] ?? '');
            $arguments = is_array($toolCall['arguments'] ?? null) ? $toolCall['arguments'] : [];
            if (!$this->isToolAllowedForMessage($name, $message, $arguments)) {
                $this->logger->warning('Blocked Agentic Commerce tool because it did not match explicit shopper intent.', ['tool' => $name]);
                $toolMessages[] = (string)__('I did not change your cart because the request did not explicitly ask for that cart action.');
                continue;
            }
            $startedAt = microtime(true);
            $eventContext = ['tool_name' => $name, 'conversation_id' => $conversationId, 'store_id' => $storeId, 'is_customer' => (bool)$identity['is_customer']];
            $this->eventManager->dispatch('haerriz_agentic_tool_before', $eventContext);
            try {
                $this->toolPolicy->assertAllowed($name, $identity);
                $result = $this->toolRegistry->execute($name, $arguments, $context);
                $result = $this->guardAutoNavigation($result, $message);
                $this->mergeToolResult($response, $result);
                if (!empty($result['assistant_message'])) {
                    $toolMessages[] = (string)$result['assistant_message'];
                }
                // Tools later in the same turn can use results from earlier tools.
                if (!empty($result['products']) && is_array($result['products'])) {
                    $context['recent_products'] = $this->recentProducts($result['products']);
                }
                if (!empty($result['cart']) && is_array($result['cart'])) {
                    $context['cart_items'] = (array)($result['cart']['items'] ?? []);
                }
                if (!empty($result['wishlist']) && is_array($result['wishlist'])) {
                    $context['wishlist_items'] = (array)($result['wishlist']['items'] ?? []);
                }
                $executedTools[] = $name;
                $this->learning->observe($message, $name, true, $identity);
                $this->eventManager->dispatch('haerriz_agentic_tool_after', $eventContext + ['result_keys' => array_keys($result)]);
                $this->toolAudit->log(
                    $conversationId,
                    $identity,
                    $name,
                    $arguments,
                    'success',
                    (int)round((microtime(true) - $startedAt) * 1000),
                    null,
                    $message
                );
            } catch (\Throwable $e) {
                $executedTools[] = $name;
                $this->learning->observe($message, $name, false, $identity);
                $this->eventManager->dispatch('haerriz_agentic_tool_failed', $eventContext + ['exception_class' => $e::class]);
                $this->toolAudit->log(
                    $conversationId,
                    $identity,
                    $name,
                    $arguments,
                    'error',
                    (int)round((microtime(true) - $startedAt) * 1000),
                    $e::class,
                    $message
                );
                $this->logger->error('Haerriz Agentic Commerce tool failed: ' . $name, ['exception_class' => $e::class]);
                $toolMessages[] = $e instanceof LocalizedException
                    ? $e->getMessage()
                    : (string)__('I could not complete one part of that request.');
            }
        }

        $plannedMessage = trim((string)($plan['assistant_message'] ?? ''));
        // Commerce tool output is authoritative. Never let model prose claim a mutation/read result that the server did not return.
        $authoritativeToolMessage = trim(implode(' ', array_unique(array_filter($toolMessages))));
        $response['message'] = $authoritativeToolMessage !== '' ? $authoritativeToolMessage : $plannedMessage;
        if ($response['message'] === '') {
            $response['message'] = (string)__('How can I help you shop?');
        }
        $response['message'] = $this->responseSynthesis->synthesize($message, $response, $response['message'], array_values(array_unique($executedTools)), $identity);
        $response['suggestions'] = $this->suggestionService->forResponse($response, $storeId);

        $context['filters'] = $response['filters'];
        $context['query_phrase'] = $response['query_phrase'];
        if ($response['products'] !== []) {
            $context['recent_products'] = $this->recentProducts($response['products']);
        }
        if (!empty($response['cart'])) {
            $context['cart_items'] = (array)($response['cart']['items'] ?? []);
        }
        if (!empty($clientContext['cart_id'])) {
            $context['cart_id'] = trim((string)$clientContext['cart_id']);
        }
        $context['recent_turns'] = $this->appendRecentTurns((array)($context['recent_turns'] ?? []), $message, $response['message'], $storeId);
        unset($context['identity']);
        $this->conversations->updateContext($conversationId, $context);

        $payload = [
            'products' => $response['products'],
            'actions' => $response['actions'],
            'filters' => $response['filters'],
            'facets' => $response['facets'],
            'cart' => $response['cart'],
            'total_count' => $response['total_count'],
            'query_phrase' => $response['query_phrase'],
            'page_info' => $response['page_info'],
            'wishlist' => $response['wishlist'],
            'orders' => $response['orders'],
            'knowledge' => $response['knowledge'],
            'suggestions' => $response['suggestions'],
            'extensions' => $response['extensions'],
            'checkout' => $response['checkout'], 'customer' => $response['customer'], 'addresses' => $response['addresses'], 'product_options' => $response['product_options'], 'reviews' => $response['reviews'], 'newsletter' => $response['newsletter'], 'store_context' => $response['store_context'], 'confirmation' => $response['confirmation'], 'shipping_methods' => $response['shipping_methods'], 'payment_methods' => $response['payment_methods'], 'inventory' => $response['inventory'], 'inventories' => $response['inventories'], 'price_insight' => $response['price_insight'], 'countries' => $response['countries'], 'country' => $response['country'], 'form' => $response['form'], 'product_experience' => $response['product_experience'], 'product_content' => $response['product_content'], 'product_answer' => $response['product_answer'], 'comparison' => $response['comparison'], 'variant_availability' => $response['variant_availability'],
        ];
        $this->conversations->appendMessage($conversationId,'assistant',$response['message'],$payload);
        $this->telemetry->emit('turn.completed',['trace_id'=>$traceId,'store_id'=>$storeId,'duration_ms'=>(int)round((microtime(true)-$turnStarted)*1000),'tool_count'=>count(array_unique($executedTools))]);
        return $response;
    }

    private function emptyResponse(string $conversationId, string $clientId, array $context, array $identity): array
    {
        return [
            'session_id' => $clientId,
            'conversation_id' => $conversationId,
            'client_id' => $clientId,
            'message' => '',
            'products' => [],
            'actions' => [],
            'filters' => is_array($context['filters'] ?? null) ? $context['filters'] : [],
            'facets' => [],
            'cart' => null,
            'wishlist' => null,
            'orders' => [],
            'knowledge' => [],
            'suggestions' => [],
            'extensions' => [],
            'total_count' => 0,
            'query_phrase' => (string)($context['query_phrase'] ?? ''),
            'page_info' => ['current_page' => 1, 'page_size' => 0, 'total_pages' => 0],
            'checkout' => null, 'customer' => null, 'addresses' => [], 'product_options' => null, 'reviews' => null, 'newsletter' => null, 'store_context' => null, 'store_profile' => null, 'confirmation' => null, 'shipping_methods' => [], 'payment_methods' => [], 'inventory' => null, 'inventories' => [], 'price_insight' => null, 'countries' => [], 'country' => null, 'form' => null, 'product_experience' => null, 'product_content' => null, 'product_answer' => null, 'comparison' => null, 'variant_availability' => null,
            'viewer' => [
                'is_customer' => (bool)$identity['is_customer'],
                'customer_id' => (int)$identity['customer_id'] > 0 ? (int)$identity['customer_id'] : null,
            ],
        ];
    }

    private function mergeToolResult(array &$response, array $result): void
    {
        foreach (['products', 'filters', 'facets', 'page_info', 'query_phrase', 'cart', 'wishlist', 'orders', 'knowledge', 'checkout', 'customer', 'addresses', 'product_options', 'reviews', 'newsletter', 'store_context', 'store_profile', 'confirmation', 'shipping_methods', 'payment_methods', 'inventory', 'inventories', 'price_insight', 'countries', 'country', 'form', 'product_experience', 'product_content', 'product_answer', 'comparison', 'variant_availability'] as $key) {
            if (array_key_exists($key, $result)) {
                $response[$key] = $result[$key];
            }
        }
        if (isset($result['total_count'])) {
            $response['total_count'] = (int)$result['total_count'];
        }
        if (!empty($result['actions']) && is_array($result['actions'])) {
            $response['actions'] = array_merge($response['actions'], $result['actions']);
        }
        $extensionData = is_array($result['extension_data'] ?? null) ? $result['extension_data'] : [];
        if ($extensionData !== []) {
            $response['extensions'] = array_merge($response['extensions'], $this->normalizeExtensionData($extensionData));
        }
    }

    /** @return array<int,array{namespace:string,json:string}> */
    private function normalizeExtensionData(array $extensionData): array
    {
        $out = [];
        foreach (array_slice($extensionData, 0, 20, true) as $namespace => $payload) {
            $namespace = mb_substr(trim((string)$namespace), 0, 64);
            if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $namespace)) continue;
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) continue;
            if (strlen($json) > 20000) {
                $json = json_encode(['truncated' => true], JSON_UNESCAPED_SLASHES) ?: '{"truncated":true}';
            }
            $out[] = ['namespace' => $namespace, 'json' => $json];
        }
        return $out;
    }

    private function mergeSafeClientContext(array $serverContext, array $clientContext): array
    {
        // Current UI filters may be supplied by a headless PLP, but recent product references are never trusted from the browser.
        if (is_array($clientContext['filters'] ?? null)) {
            $serverContext['filters'] = array_slice($clientContext['filters'], 0, 30);
        }
        if (isset($clientContext['query_phrase'])) {
            $serverContext['query_phrase'] = mb_substr(trim((string)$clientContext['query_phrase']), 0, 180);
        }
        if (isset($clientContext['page_url'])) {
            $serverContext['page_url'] = mb_substr(trim((string)$clientContext['page_url']), 0, 1000);
        }
        return $serverContext;
    }

    private function isToolAllowedForMessage(string $toolName,string $message,array $arguments=[]):bool
    {
        foreach($this->intentGuards as $guard){
            if($guard instanceof ToolIntentGuardInterface&&$guard->supports($toolName))return $guard->isAllowed($toolName,$message,$arguments);
        }
        // Safe default for extension tools: read-only may proceed to ToolPolicy; mutations require an explicit guard.
        return !$this->toolPolicy->mutatesState($toolName);
    }

    private function appendRecentTurns(array $turns, string $userMessage, string $assistantMessage, int $storeId): array
    {
        $turns[] = ['role' => 'user', 'content' => mb_substr($userMessage, 0, 1800)];
        $turns[] = ['role' => 'assistant', 'content' => mb_substr($assistantMessage, 0, 1800)];
        return array_slice($turns, -($this->config->getMaxContextTurns($storeId) * 2));
    }

    private function guardAutoNavigation(array $result, string $message): array
    {
        if (empty($result['actions']) || !is_array($result['actions'])) {
            return $result;
        }
        $explicitNavigation = (bool)preg_match(
            '/\b(?:open|visit|navigate)\b|\b(?:go|take\s+me)\s+to\b/u',
            mb_strtolower($message)
        );
        if ($explicitNavigation) {
            return $result;
        }
        foreach ($result['actions'] as &$action) {
            if (is_array($action) && !empty($action['auto_navigate'])) {
                $action['auto_navigate'] = false;
            }
        }
        unset($action);
        return $result;
    }

    private function recentProducts(array $products): array
    {
        $recent = [];
        foreach (array_slice($products, 0, 24) as $product) {
            if (!is_array($product) || empty($product['sku'])) {
                continue;
            }
            $recent[] = ['sku' => (string)$product['sku'], 'name' => (string)($product['name'] ?? '')];
        }
        return $recent;
    }
}
