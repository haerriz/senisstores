<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Conversation;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Identity\IdentityResolver;

class ConversationService
{
    public function __construct(
        private IdentityResolver $identityResolver,
        private ConversationRepository $repository,
        private Config $config
    ) {
    }

    public function start(?string $clientId = null, ?int $trustedCustomerId = null, string $channel = 'storefront'): array
    {
        $identity = $this->identityResolver->resolve($trustedCustomerId, $clientId, $channel);
        $this->repository->claimGuestConversations($identity);
        $conversation = $this->repository->start($identity);
        return $this->envelope($conversation, $identity, []);
    }

    public function list(?string $clientId = null, ?int $trustedCustomerId = null, int $limit = 20, int $page = 1, string $channel = 'storefront'): array
    {
        $identity = $this->identityResolver->resolve($trustedCustomerId, $clientId, $channel);
        $this->repository->claimGuestConversations($identity);
        return [
            'client_id' => $identity['client_id'],
            'viewer' => $this->viewer($identity),
            'items' => $this->repository->list($identity, $limit, $page),
        ];
    }

    public function get(string $conversationId, ?string $clientId = null, ?int $trustedCustomerId = null, string $channel = 'storefront'): array
    {
        $identity = $this->identityResolver->resolve($trustedCustomerId, $clientId, $channel);
        $this->repository->claimGuestConversations($identity);
        $conversation = $this->repository->get($conversationId, $identity);
        $messages = $this->repository->messages($conversationId, $identity, $this->config->getHistoryLimit((int)$identity['store_id']));
        foreach ($messages as &$message) {
            $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
            foreach (['products', 'actions', 'filters', 'facets', 'cart', 'wishlist', 'orders', 'knowledge', 'suggestions', 'total_count', 'query_phrase', 'page_info', 'checkout', 'customer', 'addresses', 'product_options', 'reviews', 'newsletter', 'store_context', 'confirmation', 'shipping_methods', 'payment_methods', 'inventory', 'inventories', 'price_insight', 'countries', 'country', 'form', 'product_experience', 'product_content', 'product_answer', 'comparison', 'extensions'] as $key) {
                $message[$key] = $payload[$key] ?? ((in_array($key, ['cart','wishlist','page_info','checkout','customer','product_options','reviews','newsletter','store_context','confirmation','inventory','price_insight','country','form','product_experience','product_content','product_answer','comparison'], true)) ? null : (($key === 'total_count') ? 0 : (($key === 'query_phrase') ? '' : [])));
            }
            unset($message['payload']);
        }
        unset($message);
        return $this->envelope($conversation, $identity, $messages);
    }

    public function close(string $conversationId, ?string $clientId = null, ?int $trustedCustomerId = null, string $channel = 'storefront'): array
    {
        $identity = $this->identityResolver->resolve($trustedCustomerId, $clientId, $channel);
        $this->repository->claimGuestConversations($identity);
        return [
            'success' => $this->repository->close($conversationId, $identity),
            'client_id' => $identity['client_id'],
            'viewer' => $this->viewer($identity),
        ];
    }

    private function envelope(array $conversation, array $identity, array $messages): array
    {
        return [
            'id' => (string)$conversation['public_id'],
            'title' => (string)$conversation['title'],
            'status' => (string)$conversation['status'],
            'created_at' => (string)$conversation['created_at'],
            'updated_at' => (string)$conversation['updated_at'],
            'last_message_at' => (string)$conversation['last_message_at'],
            'client_id' => (string)$identity['client_id'],
            'viewer' => $this->viewer($identity),
            'messages' => $messages,
        ];
    }

    private function viewer(array $identity): array
    {
        return [
            'is_customer' => (bool)$identity['is_customer'],
            'customer_id' => (int)$identity['customer_id'] > 0 ? (int)$identity['customer_id'] : null,
        ];
    }
}
