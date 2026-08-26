<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Planner;

use Haerriz\AgenticCommerce\Model\AttributeMetadataService;
use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Intent\KnowledgeIntentMatcher;

class DeterministicPlanner implements PlannerInterface
{
    public function __construct(
        private AttributeMetadataService $metadataService,
        private Config $config,
        private KnowledgeIntentMatcher $knowledgeIntentMatcher
    ) {
    }

    public function plan(string $message, array $context = []): array
    {
        $lower = $this->normalizeIntentEnglish(mb_strtolower(trim($message)));

        // Explicit negation must win over mutation keywords. This prevents phrases such as
        // "do not clear my cart" or "don't add that to my wishlist" from executing a write.
        if (preg_match('/\b(?:do not|don\'t|dont|never)\b.*\b(?:add|put|remove|delete|clear|empty|apply|subscribe|unsubscribe|update|change|place|submit|save)\b.*\b(?:cart|basket|wishlist|wish list|coupon|promo|newsletter|address|order|profile)\b/u', $lower)) {
            return ['assistant_message' => (string)__('Okay — I will not make that change.'), 'tools' => []];
        }

        // Connected requests execute the discovery/refinement clause first; MultiStepPlanEnhancer
        // appends only explicitly requested dependent actions after the search result exists.
        if (preg_match('/^(.+?)\s+(?:and\s+)?then\s+(.+)$/u', $lower, $compound)
            && preg_match('/\b(?:show|find|search|browse|list)\b/u', $compound[1])) {
            $firstPlan = $this->plan(trim($compound[1]), $context);
            if (($firstPlan['tools'][0]['name'] ?? '') === 'search_products') {
                return $firstPlan;
            }
        }

        // Checkout-readiness questions are reads even when they contain the words "place order".
        if (preg_match('/\b(?:can i place my order now|can i order now|what do i need before checkout|checkout progress|checkout readiness|is checkout complete)\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_checkout_state', 'arguments' => []]]];
        }

        // Consequential checkout actions are explicitly routed before any catalog fallback.
        if (preg_match('/\b(?:confirm|yes[, ]*place|confirm order|place it now)\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'confirm_pending_action', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:cancel|never mind|do not|don\'t)\b.*\b(?:pending|confirmation|place order)\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'cancel_pending_action', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:place|submit|complete|finish)\b.*\border\b|\bbuy now\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'prepare_place_order', 'arguments' => []]]];
        }

        if (preg_match('/\b(?:checkout status|checkout state|am i ready to (?:checkout|order)|ready to order|what is missing from checkout)\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_checkout_state', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:shipping|delivery)\s+(?:methods?|options?|choices?)\b|\bwhat (?:shipping|delivery) (?:is|are) available\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_shipping_methods', 'arguments' => []]]];
        }
        if (preg_match('/\bpayment\s+(?:methods?|options?|choices?)\b|\bhow can i pay\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_payment_methods', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:use|select|choose)\s+(?:my\s+)?(first|second|third|fourth|\d+(?:st|nd|rd|th)?)\s+(?:saved\s+)?address\s+(?:for|as)\s+(shipping|billing)\b/u', $lower, $m)) {
            $index = $this->ordinalToIndex($m[1]);
            return ['assistant_message' => '', 'tools' => [[
                'name' => $m[2] === 'shipping' ? 'use_saved_shipping_address' : 'use_saved_billing_address',
                'arguments' => ['index' => $index],
            ]]];
        }

        if (preg_match('/\b(?:show|what is|what\'s|what are|view)\s+(?:my\s+)?(?:customer\s+)?(?:profile|account details?)\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_customer_profile', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:show|list|view|what are)\s+(?:my\s+)?(?:saved\s+)?addresses|\bshow\s+(?:my\s+)?address book\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_customer_addresses', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:edit|change|update)\s+(?:my\s+)?(?:profile|name|account details?)\b/u', $lower)) {
            return ['assistant_message'=>'','tools'=>[['name'=>'request_customer_form','arguments'=>['kind'=>'profile']]]];
        }
        if (preg_match('/\b(?:add|create|new)\s+(?:a\s+)?(?:new\s+)?(?:saved\s+)?address\b/u', $lower)) {
            return ['assistant_message'=>'','tools'=>[['name'=>'request_customer_form','arguments'=>['kind'=>'address']]]];
        }
        if (preg_match('/\b(?:edit|change|update)\s+(?:my\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s+(?:saved\s+)?address\b/u',$lower,$m)) {
            $index=$m[1]==='last'?0:$this->ordinalToIndex((string)$m[1]);
            return ['assistant_message'=>'','tools'=>[['name'=>'request_customer_form','arguments'=>['kind'=>'address','index'=>$index]]]];
        }
        if (preg_match('/\b(?:delete|remove)\s+(?:my\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s+(?:saved\s+)?address\b/u',$lower,$m)) {
            $count=count((array)($context['customer_addresses']??[]));
            $index=$m[1]==='last'?0:$this->ordinalToIndex((string)$m[1]);
            return ['assistant_message'=>'','tools'=>[['name'=>'prepare_delete_saved_address','arguments'=>['index'=>$index]]]];
        }
        if (preg_match('/\b(?:newsletter status|am i subscribed to (?:the )?newsletter|tell me if i am subscribed to newsletter|check newsletter status)\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_newsletter_status', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:unsubscribe|stop)\b.*\bnewsletter\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'unsubscribe_newsletter', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:subscribe|join)\b.*\bnewsletter\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'subscribe_newsletter', 'arguments' => []]]];
        }
        if (preg_match('/\b(?:current|available|change|which|show)\s+(?:store|store view|currency|currencies|language)|\bwhat (?:currency|language)\b|\bavailable currencies\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_store_context', 'arguments' => []]]];
        }

        // Coupons outrank generic pricing intent because phrases such as "discount code" contain price-related words.
        if ($this->isCouponRemoveRequest($lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'remove_coupon', 'arguments' => []]]];
        }
        $earlyCoupon = $this->couponRequest($message);
        if ($earlyCoupon !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'apply_coupon', 'arguments' => ['code' => $earlyCoupon['code']]]]];
        }

        // Alerts outrank inventory/price reads because "back in stock" and "price drop" contain those words.
        if (preg_match('/\b(?:notify me|alert me)\b.*\bsku\s*[:#-]?\s*([A-Za-z0-9._-]{2,64})\b.*\b(back in stock|price drop|price drops|price changes?)\b/ui', $message, $alert)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'subscribe_product_alert', 'arguments' => ['sku' => (string)$alert[1], 'type' => str_contains(mb_strtolower((string)$alert[2]), 'stock') ? 'stock' : 'price']]]];
        }

        // Option/configuration reads outrank generic availability Q&A.
        if (preg_match('/\b(?:options?|variants?|configure|configuration|sizes?|colou?rs?|selections?|choices?)\b.*\bsku\s*[:#-]?\s*([A-Za-z0-9._-]{1,64})\b/ui', $message, $optionMatch)
            || preg_match('/\bsku\s*[:#-]?\s*([A-Za-z0-9._-]{1,64})\b.*\b(?:options?|variants?|configure|configuration|sizes?|colou?rs?|selections?|choices?|required)\b/ui', $message, $optionMatch)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_product_options', 'arguments' => ['sku' => (string)$optionMatch[1]]]]];
        }

        $inventoryCompare = $this->inventoryCompareRequest($lower, $context);
        if ($inventoryCompare !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'compare_inventory', 'arguments' => $inventoryCompare]]];
        }

        $contentRequest = $this->productContentRequest($message, $lower, $context);
        if ($contentRequest !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_product_content', 'arguments' => $contentRequest]]];
        }
        if ($this->isProductContentIntent($lower)) {
            return ['assistant_message' => (string)__('Which product do you want me to describe? Refer to a shown product by position/name or provide its exact SKU.'), 'tools' => []];
        }
        $experienceRequest = $this->productExperienceRequest($message, $lower, $context);
        if ($experienceRequest !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_product_experience', 'arguments' => $experienceRequest]]];
        }

        $inventoryRequest = $this->inventoryRequest($message, $lower, $context);
        if ($inventoryRequest !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_inventory', 'arguments' => $inventoryRequest]]];
        }
        if ($this->isUnresolvedSingleProductInventoryIntent($lower)) {
            return ['assistant_message' => (string)__('Which product do you want me to check? You can say first, second, last, the product name, or an exact SKU.'), 'tools' => []];
        }
        $priceRequest = $this->priceRequest($message, $lower, $context);
        if ($priceRequest !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_product_price', 'arguments' => $priceRequest]]];
        }
        if ($this->isUnresolvedSingleProductPriceIntent($lower)) {
            return ['assistant_message' => (string)__('Which product price do you want? You can refer to a shown product by position/name or provide its SKU.'), 'tools' => []];
        }

        $reviewRequest = $this->reviewRequest($message, $lower, $context);
        if ($reviewRequest !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_product_reviews', 'arguments' => $reviewRequest]]];
        }
        if ($this->isSingleProductReviewIntent($lower)) {
            return ['assistant_message' => (string)__('Which product reviews do you want? Refer to a shown product by position/name or provide its SKU.'), 'tools' => []];
        }

        // Generic grounded product Q&A comes after specialized inventory/price/review
        // intents so questions such as "can I buy 5?" or "is it in stock?" remain
        // authoritative Magento commerce checks rather than description-only Q&A.
        $questionRequest = $this->productQuestionRequest($message, $lower, $context);
        if ($questionRequest !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'answer_product_question', 'arguments' => $questionRequest]]];
        }

        if (preg_match('/\b(?:options?|variants?|configure|configuration)\b.*\bsku\s*[:#-]?\s*([A-Za-z0-9._-]{1,64})\b/ui', $message, $m)
            || preg_match('/\bsku\s*[:#-]?\s*([A-Za-z0-9._-]{1,64})\b.*\b(?:options?|variants?|configure|configuration)\b/ui', $message, $m)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_product_options', 'arguments' => ['sku' => (string)$m[1]]]]];
        }
        if (preg_match('/\b(?:reviews?|ratings?)\b.*\bsku\s*[:#-]?\s*([A-Za-z0-9._-]{1,64})\b/ui', $message, $m)
            || preg_match('/\bsku\s*[:#-]?\s*([A-Za-z0-9._-]{1,64})\b.*\b(?:reviews?|ratings?)\b/ui', $message, $m)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_product_reviews', 'arguments' => ['sku' => (string)$m[1], 'limit' => 5]]]];
        }
        if (preg_match('/\b(?:notify me|alert me)\b.*\b(?:when\s+)?(?:sku\s*[:#-]?\s*)?([A-Za-z0-9._-]{2,64})\b.*\b(back in stock|price drop|price changes?)\b/ui', $message, $m)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'subscribe_product_alert', 'arguments' => ['sku' => (string)$m[1], 'type' => str_contains(mb_strtolower((string)$m[2]), 'stock') ? 'stock' : 'price']]]];
        }

        $coupon = $this->couponRequest($message);
        if ($this->isCouponRemoveRequest($lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'remove_coupon', 'arguments' => []]]];
        }

        if ($coupon !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'apply_coupon', 'arguments' => ['code' => $coupon['code']]]]];
        }

        if ($this->isWishlistSummaryRequest($lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_wishlist', 'arguments' => []]]];
        }

        $wishlistRemove = $this->wishlistRemoveIndex($lower);
        if ($wishlistRemove >= 0) {
            return ['assistant_message' => '', 'tools' => [['name' => 'remove_wishlist_item', 'arguments' => ['index' => $wishlistRemove]]]];
        }

        $wishlistSku = $this->skuWishlistAddRequest($message);
        if ($wishlistSku !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'add_product_to_wishlist', 'arguments' => $wishlistSku]]];
        }

        $wishlistIndex = $this->recentWishlistAddIndex($lower, $context);
        if ($wishlistIndex > 0) {
            return ['assistant_message' => '', 'tools' => [['name' => 'add_recent_product_to_wishlist', 'arguments' => ['index' => $wishlistIndex]]]];
        }

        if (preg_match('/\b(?:save|add|put|wishlist)\b.*\b(?:wishlist|wish list|saved items?|for later)\b/u', $lower)) {
            $nameIndex = $this->recentProductNameIndex($lower, (array)($context['recent_products'] ?? []));
            if ($nameIndex > 0) return ['assistant_message'=>'','tools'=>[['name'=>'add_recent_product_to_wishlist','arguments'=>['index'=>$nameIndex]]]];
        }

        $orderNumber = $this->explicitOrderNumber($message);
        if ($orderNumber !== '') {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_order', 'arguments' => ['order_number' => $orderNumber]]]];
        }

        if ($this->isOrderHistoryRequest($lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_recent_orders', 'arguments' => ['limit' => 5]]]];
        }

        $recommendation = $this->recommendationRequest($lower, $context);
        if ($recommendation !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_recommendations', 'arguments' => $recommendation]]];
        }

        if ($this->isStoreInformationRequest($lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_store_information', 'arguments' => ['topic' => trim($message)]]]];
        }

        if ($this->isKnowledgeQuestion($lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'answer_store_question', 'arguments' => ['query' => trim($message), 'limit' => 3]]]];
        }

        $smallTalk = $this->smallTalkResponse($lower);
        if ($smallTalk !== null) {
            return ['assistant_message' => $smallTalk, 'tools' => []];
        }

        if ($this->isCartSummaryRequest($lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_cart', 'arguments' => []]]];
        }

        if ($this->isClearCartRequest($lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'clear_cart', 'arguments' => []]]];
        }

        $cartUpdate = $this->cartUpdateRequest($lower);
        if ($cartUpdate !== null) {
            return [
                'assistant_message' => '',
                'tools' => [['name' => 'update_cart_item', 'arguments' => $cartUpdate]],
            ];
        }

        $skuAdd = $this->skuAddRequest($message);
        if ($skuAdd !== null) {
            return [
                'assistant_message' => '',
                'tools' => [['name' => 'add_product_to_cart', 'arguments' => $skuAdd]],
            ];
        }

        $addIndex = $this->recentAddIndex($lower, $context);
        if ($addIndex <= 0) {
            $addIndex = $this->recentAddNameIndex($lower, $context);
        }
        if ($addIndex > 0) {
            return [
                'assistant_message' => '',
                'tools' => [['name' => 'add_recent_product_to_cart', 'arguments' => ['index' => $addIndex, 'quantity' => $this->quantity($lower)]]],
            ];
        }

        $removeIndex = $this->cartRemoveIndex($lower);
        if ($removeIndex >= 0) {
            return [
                'assistant_message' => '',
                'tools' => [['name' => 'remove_cart_item', 'arguments' => ['index' => $removeIndex]]],
            ];
        }

        // Credentials must never be collected by the assistant. Authentication intents route to Magento's native secure account flows.
        if (preg_match('/\b(?:forgot|reset)\s+(?:my\s+)?password\b|\bpassword\s+reset\b/u', $lower)) {
            return [
                'assistant_message' => (string)__('For security, passwords are never entered into this chat. I can take you to Magento’s password reset page.'),
                'tools' => [['name' => 'navigate', 'arguments' => ['target' => 'forgot_password']]],
            ];
        }
        if (preg_match('/\b(?:create\s+(?:an?\s+)?account|register|sign\s*up)\b/u', $lower)) {
            return [
                'assistant_message' => (string)__('I can take you to the secure account registration page.'),
                'tools' => [['name' => 'navigate', 'arguments' => ['target' => 'register']]],
            ];
        }
        if (preg_match('/\b(?:log\s*in|sign\s*in)\b/u', $lower) && !preg_match('/\b(?:am i|i am|already)\s+(?:logged|signed)\s+in\b/u', $lower)) {
            return [
                'assistant_message' => (string)__('I can take you to Magento’s secure sign-in page. Do not enter your password in chat.'),
                'tools' => [['name' => 'navigate', 'arguments' => ['target' => 'login']]],
            ];
        }

        if (preg_match('/^(?:go|open|take me|show me)\s+(?:to\s+)?(?:my\s+)?(cart|checkout|account|wishlist|orders?)\b/u', $lower, $m)) {
            $target = match ($m[1]) {
                'cart' => 'cart',
                'checkout' => 'checkout',
                'account' => 'account',
                'wishlist' => 'wishlist',
                default => 'orders',
            };
            return [
                'assistant_message' => (string)__('Sure — I can take you there.'),
                'tools' => [['name' => 'navigate', 'arguments' => ['target' => $target]]],
            ];
        }

        $skuComparison = $this->skuComparisonRequest($message, $lower);
        if ($skuComparison !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'compare_products', 'arguments' => $skuComparison]]];
        }
        $compareIndexes = $this->compareIndexes($lower, $context);
        if (count($compareIndexes) >= 2) {
            return [
                'assistant_message' => '',
                'tools' => [['name' => 'compare_recent_products', 'arguments' => ['indexes' => $compareIndexes, 'focus' => $this->comparisonFocus($lower), 'goal' => $this->comparisonGoal($message)]]],
            ];
        }
        if ($this->isComparisonRequest($lower)) {
            return [
                'assistant_message' => (string)__('Show me at least two products first, then say something like “compare the first two”.'),
                'tools' => [],
            ];
        }

        // Product-reference navigation requires an explicit ordinal reference such as "open the third product".
        // Bare counts in requests such as "show 1 premium course" are catalog result limits, never references.
        if (preg_match('/\b(?:open|view|go to)\s+(?:the\s+)?(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\s*(?:one|product|item)?\b/u', $lower, $m)) {
            $index = $m[1] === 'last' ? count((array)($context['recent_products'] ?? [])) : $this->ordinalToIndex($m[1]);
            if ($index > 0) {
                return ['assistant_message' => '', 'tools' => [['name' => 'open_recent_product', 'arguments' => ['index' => $index]]]];
            }
        }

        $cmsNavigationTarget = $this->knowledgeIntentMatcher->navigationTarget($lower);
        if ($cmsNavigationTarget !== null) {
            return ['assistant_message' => '', 'tools' => [['name' => 'search_pages', 'arguments' => ['query' => $cmsNavigationTarget, 'limit' => 5]]]];
        }

        if (preg_match('/\b(?:what(?:\s+are\s+the)?|which|show|list|browse)\s+(?:product\s+|store\s+|shopping\s+|catalog\s+)?categories\b|\bbrowse the catalog\b/u', $lower)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'get_catalog_navigation', 'arguments' => ['limit' => 20]]]];
        }

        if (preg_match('/^(?:find|show|open|go to)\s+(?:the\s+)?(?:category\s+)?(.+?)\s+(?:category|section)$/u', $lower, $m)) {
            return ['assistant_message' => '', 'tools' => [['name' => 'search_categories', 'arguments' => ['query' => trim($m[1]), 'limit' => 5]]]];
        }

        $filters = $this->contextFilters($context);
        $filters = $this->removeRequestedFilters($filters, $lower);
        if (preg_match('/\b(?:only\s+)?(?:in[ -]?stock|available now|currently available)\b/u', $lower)) {
            $filters['stock_status'] = ['attribute'=>'stock_status','condition'=>'eq','values'=>['1'],'label'=>(string)__('In stock')];
        }
        $extracted = $this->extractFilters($lower);
        foreach ($extracted as $filter) {
            $filters[$filter['attribute']] = $filter;
        }

        $sort = [];
        if (preg_match('/\b(?:best[ -]?sell(?:ing|er|ers)?|best sales?|most (?:popular|purchased|ordered))\b/u', $lower)) {
            $sort = ['attribute' => 'bestseller', 'direction' => 'DESC'];
        } elseif (preg_match('/\b(?:cheap(?:est|er)?|more affordable|lowest price|low price|price low to high|low to high)\b/u', $lower)) {
            $sort = ['attribute' => 'price', 'direction' => 'ASC'];
        } elseif (preg_match('/\b(?:costliest|expensive|most expensive|highest price|high price|price high to low|high to low)\b/u', $lower)) {
            $sort = ['attribute' => 'price', 'direction' => 'DESC'];
        } elseif (preg_match('/\b(?:latest|newest|most recent|recently added|new arrivals?)\b/u', $lower)) {
            $sort = ['attribute' => 'created_at', 'direction' => 'DESC'];
        } elseif (preg_match('/\b(?:name a to z|alphabetical)\b/u', $lower)) {
            $sort = ['attribute' => 'name', 'direction' => 'ASC'];
        }

        $pageSize = $this->requestedPageSize($lower) ?: $this->config->getPageSize();
        $phrase = $this->extractSearchPhrase($lower, $extracted);
        if ($this->isRefinementOnly($lower, $phrase, $extracted)
            && !$this->isFreshCatalogSortRequest($lower, $phrase, $sort)) {
            $phrase = trim((string)($context['query_phrase'] ?? $phrase));
        }

        if (!$this->shouldSearchCatalog($lower, $phrase, $extracted, $sort, $context)) {
            return [
                'assistant_message' => (string)__('Sorry, I can only help with questions about this storefront, its products, orders, cart, account, and policies.'),
                'tools' => [],
            ];
        }

        return [
            'assistant_message' => '',
            'tools' => [[
                'name' => 'search_products',
                'arguments' => [
                    'phrase' => $phrase,
                    'filters' => array_values($filters),
                    'sort' => $sort,
                    'page_size' => $pageSize,
                    'current_page' => 1,
                ],
            ]],
        ];
    }


    /**
     * Correct only a bounded vocabulary of shopping-intent words. Product names, SKUs,
     * quantities and arbitrary shopper text are deliberately not spell-corrected.
     */
    private function normalizeIntentEnglish(string $message): string
    {
        $corrections = [
            'shwo' => 'show', 'shew' => 'show',
            'serach' => 'search', 'seacrh' => 'search', 'saerch' => 'search',
            'fnd' => 'find', 'fint' => 'find',
            'wnat' => 'want', 'wnt' => 'want', 'luking' => 'looking',
            'prodct' => 'product', 'prodcts' => 'products', 'producs' => 'products',
            'corse' => 'course', 'corses' => 'courses',
            'cheep' => 'cheap', 'cheepest' => 'cheapest', 'cheapst' => 'cheapest',
            'costlist' => 'costliest', 'cosliest' => 'costliest',
            'expnsive' => 'expensive', 'expesive' => 'expensive',
            'latst' => 'latest', 'lates' => 'latest', 'newst' => 'newest',
            'remvoe' => 'remove', 'delet' => 'delete',
            'crt' => 'cart', 'wishlst' => 'wishlist',
            'compar' => 'compare', 'compair' => 'compare',
            'frst' => 'first', 'scnd' => 'second', 'thrd' => 'third',
            'stok' => 'stock', 'prce' => 'price', 'ordr' => 'order',
            'logn' => 'login', 'adress' => 'address', 'addres' => 'address',
            'shippng' => 'shipping', 'paymnt' => 'payment',
            'cntact' => 'contact', 'retrn' => 'return', 'polcy' => 'policy',
            'websit' => 'website', 'webiste' => 'website', 'owenr' => 'owner',
            'availble' => 'available', 'avlbl' => 'available',
        ];

        return preg_replace_callback(
            '/(?<![\p{L}\p{N}._-])\p{L}+(?![\p{L}\p{N}._-])/u',
            static fn(array $match): string => $corrections[$match[0]] ?? $match[0],
            $message
        ) ?? $message;
    }



    private function isUnresolvedSingleProductInventoryIntent(string $lower): bool
    {
        if (preg_match('/\b(?:only\s+show|filter|show|find|search)\b.*\b(?:in[ -]?stock|available now|currently available)\b/u',$lower)) return false;
        if (!preg_match('/\b(?:stock|inventory|availability|remaining|left|can i buy|can i order|have enough|in[ -]?stock|out[ -]?of[ -]?stock)\b/u', $lower)) return false;
        return !preg_match('/\b(?:show|find|search|browse|list)\b.*\b(?:products?|items?|courses?)\b/u', $lower)
            && !preg_match('/\b(?:compare|which|among|of these|all)\b/u', $lower);
    }

    private function isUnresolvedSingleProductPriceIntent(string $lower): bool
    {
        if ($this->isComparisonRequest($lower)) return false;
        if (preg_match('/^(?:remove|clear)\b/u',$lower)) return false;
        if (preg_match('/\b(?:products?|items?|courses?|cheapest|lowest price|costliest|highest price|low to high|high to low)\b/u',$lower)) return false;
        if (!preg_match('/\b(?:price|pricing|cost|how much|discount|special price|tier price|on sale)\b/u', $lower)) return false;
        return !preg_match('/\b(?:show|find|search|browse|list|cheapest|costliest|under|below|above)\b.*\b(?:products?|items?|courses?)?/u', $lower);
    }

    private function recentProductNameIndex(string $lower, array $recent): int
    {
        $haystack = $this->normalizeReferenceText($lower);
        $queryTokens = $this->referenceTokens($haystack);
        $best = 0;
        $bestScore = 0.0;
        $tied = false;

        foreach (array_slice($recent, 0, 24) as $i => $product) {
            $name = $this->normalizeReferenceText((string)($product['name'] ?? ''));
            if ($name === '' || mb_strlen($name) < 3) {
                continue;
            }

            // Prefer an exact normalized name contained in the request.
            if (str_contains($haystack, $name)) {
                $score = 1000.0 + mb_strlen($name);
            } else {
                $nameTokens = $this->referenceTokens($name);
                if ($queryTokens === [] || $nameTokens === []) {
                    continue;
                }
                $shared = array_values(array_intersect($queryTokens, $nameTokens));
                $sharedCount = count($shared);
                if ($sharedCount < 2) {
                    continue;
                }

                // Require the shopper's meaningful name fragment to be mostly explained by
                // the candidate. This resolves "CPR Manual" without loose one-word matches.
                $queryCoverage = $sharedCount / max(1, count($queryTokens));
                if ($queryCoverage < 0.60) {
                    continue;
                }
                $score = ($sharedCount * 10.0) + $queryCoverage;
            }

            if ($score > $bestScore + 0.0001) {
                $best = $i + 1;
                $bestScore = $score;
                $tied = false;
            } elseif (abs($score - $bestScore) < 0.0001) {
                $tied = true;
            }
        }

        // Ambiguous name fragments must never silently select a product.
        return $tied ? 0 : $best;
    }

    /** @return string[] */
    private function referenceTokens(string $value): array
    {
        $tokens = preg_split('/\s+/u', $this->normalizeReferenceText($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = [
            'a','an','the','this','that','it','product','item','one','please','me','my','is','are','was','were',
            'what','how','many','much','do','does','you','have','of','for','to','in','on','at','and','or','left',
            'remaining','stock','inventory','available','availability','price','pricing','cost','about','tell','everything','show',
        ];
        $out = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2 || in_array($token, $stop, true)) {
                continue;
            }
            $out[$token] = true;
        }
        return array_keys($out);
    }

    private function normalizeReferenceText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function inventoryCompareRequest(string $lower, array $context): ?array
    {
        // Mixed comparisons such as "compare stock, price and description" belong to the rich
        // comparison service; this specialized tool is only for inventory-centric comparisons.
        if (preg_match('/\b(?:description|specs?|specifications?|attributes?|features?|price|cost|discount|reviews?|ratings?|options?|variants?|categories?)\b/u', $lower)) {
            return null;
        }
        if (!preg_match('/\b(?:compare|which|among|of these|all)\b.*\b(?:stock|inventory|availability|available|remaining|left)\b|\b(?:which|what)\b.*\b(?:in[ -]?stock|out[ -]?of[ -]?stock)\b/u', $lower)) {
            return null;
        }
        $recent = (array)($context['recent_products'] ?? []);
        if (count($recent) < 2) return null;
        $indexes = $this->compareIndexes($lower, $context);
        if (count($indexes) < 2) {
            $count = min(12, count($recent));
            if (preg_match('/\b(?:first|top)\s+(\d{1,2})\b/u', $lower, $m)) $count = min($count, max(2, (int)$m[1]));
            $indexes = range(1, $count);
        }
        return ['indexes'=>$indexes, 'requested_qty'=>$this->requestedInventoryQty($lower)];
    }

    private function isProductContentIntent(string $lower): bool
    {
        if ($this->isComparisonRequest($lower)) return false;
        // Yes/no or evidence questions such as "is it machine washable according to the description?"
        // belong to grounded Product Q&A, not the content-display clarification branch.
        if (preg_match('/^(?:is|are|does|do|can|could|will|would|has|have)\b/u', $lower)) return false;
        return (bool)preg_match('/\b(?:description|describe|short description|features?|specifications?|specs|additional information|images?|photos?|gallery)\b/u', $lower);
    }

    private function skuComparisonRequest(string $message, string $lower): ?array
    {
        if (!$this->isComparisonRequest($lower)) return null;
        preg_match_all('/\bsku\s*[:#-]?\s*([A-Za-z0-9._\/-]{1,64})\b/ui', $message, $matches);
        $skus=array_values(array_unique(array_filter(array_map('strval',(array)($matches[1]??[])))));
        if(count($skus)<2)return null;
        return ['skus'=>array_slice($skus,0,4),'focus'=>$this->comparisonFocus($lower),'goal'=>$this->comparisonGoal($message)];
    }

    private function productContentRequest(string $message, string $lower, array $context): ?array
    {
        if ($this->isComparisonRequest($lower)) return null;
        if (preg_match('/^(?:is|are|does|do|can|could|will|would|has|have)\b/u', $lower)) return null;
        if (!preg_match('/\b(?:description|describe|short description|features?|specifications?|specs|additional information|images?|photos?|gallery|what does .* say)\b/u', $lower)) return null;
        $reference = $this->singleProductReference($message, $lower, $context);
        return $reference;
    }

    private function productQuestionRequest(string $message, string $lower, array $context): ?array
    {
        if ($this->isComparisonRequest($lower)) return null;
        if (preg_match('/\b(?:stock|inventory|availability|remaining|left|price|cost|discount|review|rating|options?|variants?)\b/u', $lower)) return null;
        if (!preg_match('/\b(?:does|do|is|are|can|could|will|would|has|have)\b/u', $lower)) return null;
        $reference = $this->singleProductReference($message, $lower, $context);
        if ($reference === null) return null;
        $reference['question'] = mb_substr($message, 0, 1000);
        return $reference;
    }

    private function singleProductReference(string $message, string $lower, array $context): ?array
    {
        if (preg_match('/\bsku\s*[:#-]?\s*([A-Za-z0-9._\/-]{1,64})\b/ui', $message, $m)) return ['sku'=>(string)$m[1]];
        if (preg_match('/\b(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\b(?:\s+(?:one|product|item))?/u', $lower, $m)) {
            $count=count((array)($context['recent_products']??[]));
            $index=$m[1]==='last'?$count:$this->ordinalToIndex((string)$m[1]);
            if($index>0&&$index<=$count)return ['index'=>$index];
        }
        $recent=(array)($context['recent_products']??[]);
        if(count($recent)===1 && preg_match('/\b(?:this|that|it|the product|the item|this product|that product)\b/u',$lower)) return ['index'=>1];
        $nameIndex=$this->recentProductNameIndex($lower,$recent);
        if($nameIndex>0)return ['index'=>$nameIndex];
        return null;
    }

    private function comparisonGoal(string $message): string
    {
        $message = trim($message);
        if ($message === '') return '';
        $patterns = [
            '/\b(?:which\s+(?:one\s+)?is\s+)?(?:better|best|more suitable|most suitable|suited|recommended)\s+for\s+(.+)$/iu',
            '/\bcompare\b.+?\bfor\s+(.+)$/iu',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $message, $m)) continue;
            $goal = trim((string)($m[1] ?? ''), " \t\n\r\0\x0B?.!,;");
            if ($goal === '') continue;
            $normalized = mb_strtolower($goal);
            // A bare comparison dimension is a focus selector, not a shopper use-case.
            if (preg_match('/^(?:description|descriptions|price|cost|stock|inventory|reviews?|ratings?|specs?|specifications?|attributes?|features?|options?|categories?)(?:\s*(?:,|and|&|\/|\+)\s*(?:description|descriptions|price|cost|stock|inventory|reviews?|ratings?|specs?|specifications?|attributes?|features?|options?|categories?))*$/u', $normalized)) {
                return '';
            }
            return mb_substr($goal, 0, 500);
        }
        return '';
    }

    private function comparisonFocus(string $message): array
    {
        $focus=[];
        if (preg_match('/\b(?:description|descriptions|content|about them)\b/u',$message)) $focus[]='description';
        if (preg_match('/\b(?:specs?|specifications?|attributes?|features?)\b/u',$message)) $focus[]='attributes';
        if (preg_match('/\b(?:price|cost|cheaper|expensive|discount)\b/u',$message)) $focus[]='price';
        if (preg_match('/\b(?:stock|inventory|availability|available|remaining)\b/u',$message)) $focus[]='inventory';
        if (preg_match('/\b(?:review|reviews|rating|ratings|feedback)\b/u',$message)) $focus[]='reviews';
        if (preg_match('/\b(?:option|options|variant|variants|configuration)\b/u',$message)) $focus[]='options';
        if (preg_match('/\b(?:category|categories)\b/u',$message)) $focus[]='categories';
        return array_values(array_unique($focus));
    }

    private function productExperienceRequest(string $message, string $lower, array $context): ?array
    {
        if (preg_match('/\b(?:price|pricing|cost|stock|inventory|availability|reviews?|ratings?)\b/u', $lower)) return null;
        if (!preg_match('/\b(?:details?|everything|full info(?:rmation)?|tell me (?:everything )?about|product info|overview)\b/u', $lower)) return null;
        if (preg_match('/\bsku\s*[:#-]?\s*([A-Za-z0-9._\/-]{1,64})\b/ui', $message, $m)) {
            return ['sku'=>(string)$m[1]];
        }
        if (preg_match('/\b(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\b(?:\s+(?:one|product|item))?/u', $lower, $m)) {
            $count=count((array)($context['recent_products']??[]));
            $index=$m[1]==='last'?$count:$this->ordinalToIndex((string)$m[1]);
            if($index>0&&$index<=$count)return ['index'=>$index];
        }
        $recent=(array)($context['recent_products']??[]);
        if(count($recent)===1 && preg_match('/\b(?:this|that|it|the product|the item)\b/u',$lower)) return ['index'=>1];
        $nameIndex=$this->recentProductNameIndex($lower,$recent);
        if($nameIndex>0)return ['index'=>$nameIndex];
        return null;
    }

    private function inventoryRequest(string $message, string $lower, array $context): ?array
    {
        if ($this->isComparisonRequest($lower)) return null;
        if (preg_match('/\b(?:options?|variants?|configuration|configure|sizes?|colou?rs?|selections?)\b/u', $lower)) return null;
        $hasInventoryIntent = (bool)preg_match('/\b(?:stock|in[ -]?stock|out[ -]?of[ -]?stock|available|availability|inventory|remaining|left|can i buy|can i order|do you have|have enough|enough stock)\b/u', $lower)
            && (bool)preg_match('/\b(?:how many|how much stock|stock|inventory|remaining|left|available|availability|in[ -]?stock|out[ -]?of[ -]?stock|can i buy|can i order|do you have|is there)\b/u', $lower);
        if (!$hasInventoryIntent) return null;
        // Broad shopping requests like "show in-stock shoes" remain catalog searches with a stock filter.
        // Merely saying "this product" must not trip this guard.
        if (preg_match('/\b(?:show|find|search|browse|list)\b.*\b(?:products?|items?|courses?)\b/u', $lower)
            && !preg_match('/\b(?:first|second|third|fourth|fifth|sixth|seventh|eighth|last|sku\b)\b/u', $lower)) {
            return null;
        }
        if (preg_match('/\bsku\s*[:#-]?\s*([A-Za-z0-9._\/-]{1,64})\b/ui', $message, $m)) {
            return ['sku'=>(string)$m[1], 'requested_qty'=>$this->requestedInventoryQty($lower), 'query'=>mb_substr($message,0,500)];
        }
        if (preg_match('/\b(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\b(?:\s+(?:one|product|item))?/u', $lower, $m)) {
            $count = count((array)($context['recent_products'] ?? []));
            $index = $m[1] === 'last' ? $count : $this->ordinalToIndex((string)$m[1]);
            if ($index > 0 && $index <= $count) return ['index'=>$index, 'requested_qty'=>$this->requestedInventoryQty($lower), 'query'=>mb_substr($message,0,500)];
        }
        $recent = (array)($context['recent_products'] ?? []);
        if (count($recent) === 1 && (
            preg_match('/\b(?:this|that|it|the product|the item|this product|that product)\b/u', $lower)
            || preg_match('/^(?:how many(?: are)? left|how much stock(?: is)? left|what(?: is|\'s) left|remaining stock)\??$/u', trim($lower))
        )) {
            return ['index'=>1, 'requested_qty'=>$this->requestedInventoryQty($lower), 'query'=>mb_substr($message,0,500)];
        }
        $nameIndex = $this->recentProductNameIndex($lower, $recent);
        if ($nameIndex > 0) return ['index'=>$nameIndex, 'requested_qty'=>$this->requestedInventoryQty($lower), 'query'=>mb_substr($message,0,500)];
        return null;
    }

    private function requestedInventoryQty(string $message): float
    {
        if (preg_match('/\b(?:buy|need|want|order|for)\s+(\d+(?:\.\d+)?)\b/u', $message, $m)) {
            return max(0.0001, min(10000.0, (float)$m[1]));
        }
        return 1.0;
    }

    private function priceRequest(string $message, string $lower, array $context): ?array
    {
        if ($this->isComparisonRequest($lower)) return null;
        if (!preg_match('/\b(?:price|pricing|cost|how much|discount|deal|special price|tier price|on sale)\b/u', $lower)) return null;
        if (preg_match('/\bsku\s*[:#-]?\s*([A-Za-z0-9._\/-]{1,64})\b/ui', $message, $m)) return ['sku'=>(string)$m[1]];
        $recent=(array)($context['recent_products']??[]);
        $nameIndex=$this->recentProductNameIndex($lower,$recent);
        if($nameIndex>0)return ['index'=>$nameIndex];
        // Sorting/budget catalog requests are not single-product price lookups. A recent product
        // name is resolved above so "show pricing for Classic Red Shirt" remains a price read.
        if (preg_match('/\b(?:cheapest|costliest|most expensive|under|below|above|products?|items?|courses?|show|find|search)\b/u', $lower)
            && !preg_match('/\b(?:first|second|third|fourth|fifth|sixth|seventh|eighth|last|sku\b)\b/u', $lower)) return null;
        if (preg_match('/\b(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\b(?:\s+(?:one|product|item))?/u', $lower, $m)) {
            $count=count((array)($context['recent_products']??[]));
            $index=$m[1]==='last'?$count:$this->ordinalToIndex((string)$m[1]);
            if($index>0&&$index<=$count)return ['index'=>$index];
        }
        if(count($recent)===1 && preg_match('/\b(?:this|that|it|the product|the item)\b/u',$lower)) return ['index'=>1];
        return null;
    }

    private function reviewRequest(string $message, string $lower, array $context): ?array
    {
        if ($this->isComparisonRequest($lower)) return null;
        if (!preg_match('/\b(?:review|reviews|rating|ratings|feedback)\b/u', $lower)) return null;
        $reference=$this->singleProductReference($message,$lower,$context);
        if($reference===null)return null;
        $reference['limit']=5;
        return $reference;
    }

    private function isSingleProductReviewIntent(string $lower): bool
    {
        if ($this->isComparisonRequest($lower)) return false;
        if (!preg_match('/\b(?:review|reviews|rating|ratings|feedback)\b/u', $lower)) return false;
        return !preg_match('/\b(?:show|find|search|browse|list)\b.*\b(?:products?|items?|courses?)\b/u', $lower);
    }

    private function couponRequest(string $message): ?array
    {
        if (!preg_match('/\b(?:apply|use|add)\s+(?:coupon|promo(?:\s+code)?|discount\s+code|voucher)\s+["\']?([A-Za-z0-9_-]{2,64})["\']?/ui', $message, $m)
            && !preg_match('/\b(?:apply|use|add)\s+["\']?([A-Za-z0-9_-]{2,64})["\']?\s+(?:coupon|promo(?:\s+code)?|discount\s+code|voucher)\b/ui', $message, $m)) {
            return null;
        }
        return ['code' => (string)$m[1]];
    }

    private function isCouponRemoveRequest(string $message): bool
    {
        return (bool)preg_match('/\b(?:remove|delete|clear|take off|stop using)\b.*\b(?:coupon|promo|discount code|voucher)\b/u', $message);
    }

    private function isWishlistSummaryRequest(string $message): bool
    {
        if (preg_match('/\bwhat products did i save\b/u',$message)) return true;
        return (bool)preg_match('/\b(?:show|list|view|display|what(?:\'s| is) in|what products did i save)\b.*\b(?:my\s+)?(?:wishlist|wish list|saved items?|saved products|favorites?)\b|\b(?:wishlist summary|show favorites)\b/u', $message);
    }

    private function skuWishlistAddRequest(string $message): ?array
    {
        if (!preg_match('/\b(?:save|add)\s+(?:product\s+)?(?:with\s+)?sku\s+[\["\']?([a-zA-Z0-9_.\/-]{1,64})[\]"\']?\s+(?:to|in|into)\s+(?:my\s+)?(?:wishlist|wish list|saved items?)\b/ui', $message, $m)) {
            return null;
        }
        return ['sku' => (string)$m[1]];
    }

    private function recentWishlistAddIndex(string $message, array $context): int
    {
        // Cart and wishlist ordinals deliberately overlap ("add the first product ...").
        // Require explicit wishlist/saved intent so a cart request can never be captured here.
        if (!preg_match('/\b(?:wishlist|wish list|saved items?|for later)\b/u', $message)
            && !preg_match('/^\s*save\b/u', $message)) {
            return 0;
        }
        if (!preg_match('/\b(?:save|add|put|wishlist)\s+(?:the\s+)?(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\s*(?:shown\s+)?(?:one|product|item)?(?:\s+(?:to|in|into)\s+(?:my\s+)?(?:wishlist|wish list|saved items?)|\s+for\s+later)?\b/u', $message, $m)) {
            return 0;
        }
        return $m[1] === 'last' ? count((array)($context['recent_products'] ?? [])) : $this->ordinalToIndex($m[1]);
    }

    private function wishlistRemoveIndex(string $message): int
    {
        if (!preg_match('/\b(?:remove|delete)\s+(?:the\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s*(?:wishlist\s+)?(?:item|product|one)?\s+from\s+(?:my\s+)?(?:wishlist|wish list|saved items?)\b/u', $message, $m)) {
            return -1;
        }
        return $m[1] === 'last' ? 0 : $this->ordinalToIndex($m[1]);
    }

    private function isOrderHistoryRequest(string $message): bool
    {
        if (preg_match('/\b(?:what did i order recently|what orders have i placed)\b/u',$message)) return true;
        return (bool)preg_match('/\b(?:show|list|view|display|what are|where are|what did i order|what orders have i placed)\b.*\b(?:my\s+)?(?:recent\s+)?orders?\b|\b(?:order history|past orders|previous orders|purchase history)\b/u', $message);
    }

    private function explicitOrderNumber(string $message): string
    {
        if (!preg_match('/\b(?:order|order number|order #|track order)\s*#?\s*([A-Za-z0-9_-]{3,40})\b/ui', $message, $m)) {
            return '';
        }
        $candidate = (string)$m[1];
        if (in_array(mb_strtolower($candidate), ['status', 'history', 'tracking', 'details', 'list', 'recently'], true)) {
            return '';
        }
        return $candidate;
    }

    private function recommendationRequest(string $message, array $context): ?array
    {
        if (!preg_match('/\b(?:recommend|recommendations?|similar|alternatives?|goes with|pair with|upsell|cross[- ]?sell)\b/u', $message)) {
            return null;
        }
        $type = 'related';
        if (preg_match('/\b(?:goes with|pair with|cross[- ]?sell)\b/u', $message)) {
            $type = 'crosssell';
        } elseif (preg_match('/\b(?:upgrade|premium alternative|upsell)\b/u', $message)) {
            $type = 'upsell';
        }
        $index = 0;
        if (preg_match('/\b(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\b/u', $message, $m)) {
            $index = $m[1] === 'last' ? count((array)($context['recent_products'] ?? [])) : $this->ordinalToIndex($m[1]);
        }
        $args = ['type' => $type, 'limit' => 6];
        if ($index > 0) {
            $args['index'] = $index;
        }
        return $args;
    }

    private function isStoreInformationRequest(string $message): bool
    {
        if (preg_match('/^(?:go|open|take me|navigate|visit)\b/u', $message)) {
            return false;
        }
        if (preg_match('/\b(?:page|link)\b/u', $message)) return false;
        return (bool)preg_match(
            '/\b(?:who\s+are\s+you|what\s+(?:website|site|store)\s+is\s+this|which\s+(?:website|site|store)|who\s+(?:is\s+(?:the\s+)?owner\s+of|owns|runs|operates)\s+(?:this|the)\s+(?:website|site|store)|(?:website|site|store)\s+owner|what\s+can\s+you\s+do|your\s+capabilities|how\s+can\s+you\s+help|contact(?:\s+details?)?|contact\s+number|phone\s+number|telephone(?:\s+number)?|customer\s+(?:care(?:\s+number)?|service\s+email)|support\s+(?:number|email)|email\s+address|contact\s+email|(?:store|business|office)\s+address|address\s+of\s+(?:the\s+)?(?:shop|store|site|website)|what(?:\'s|s|\s+is)\s+(?:the\s+|your\s+)?address|opening\s+hours?|store\s+hours?|working\s+hours|when\s+are\s+you\s+open|timings?|where\s+(?:are\s+you|is\s+your\s+(?:store|office))|how\s+can\s+i\s+(?:call|email)\s+you)\b/u',
            $message
        );
    }

    private function isKnowledgeQuestion(string $message): bool
    {
        return $this->knowledgeIntentMatcher->matches($message);
    }

    private function smallTalkResponse(string $message): ?string
    {
        if (preg_match('/^(?:hi|hello|hey|good\s+(?:morning|afternoon|evening))\b[!. ]*$/u', $message)) {
            return (string)__('Hi! Tell me what you are shopping for, or ask me about your cart, wishlist, orders, contact details, or store policies.');
        }
        if (preg_match('/^(?:thanks|thank you|thankyou|ok|okay|cool|great)[!. ]*$/u', $message)) {
            return (string)__('You’re welcome. Tell me what you want to do next.');
        }
        if (preg_match('/^(?:help me shop|i am just browsing|i\'m just browsing|never mind)[?!. ]*$/u', $message)) {
            return (string)__('No problem. I can help whenever you want to search, compare, or manage your shopping.');
        }
        if (preg_match('/^how are you[?!. ]*$/u', $message)) {
            return (string)__('I’m your shopping assistant. I can help you discover products and complete supported store actions.');
        }
        return null;
    }

    private function isComparisonRequest(string $message): bool
    {
        return (bool)preg_match('/\bcompare\b|\bdifferences?\b.*\b(?:first|second|products?|items?)\b|\bsimilarities\b.*\b(?:first|second|products?|items?)\b|\bversus\b|\bvs\.?\b|\bwhich\s+(?:one\s+)?is\s+(?:better|best|more suitable|most suitable|recommended)\b|\bwhich\s+of\s+(?:these|them|both|the\s+first)\b.*\b(?:better|best|more suitable|recommended)\b/u', $message);
    }

    private function compareIndexes(string $message, array $context): array
    {
        if (!$this->isComparisonRequest($message)) {
            return [];
        }
        $recentCount = count((array)($context['recent_products'] ?? []));

        if ($recentCount === 2 && preg_match('/\bwhich\s+(?:one\s+)?is\s+(?:better|best|more suitable|most suitable|recommended)\b/u', $message)) {
            return [1, 2];
        }

        if (preg_match('/\b(?:the\s+)?(?:first|top)\s+(two|2|three|3|four|4)\b/u', $message, $m)) {
            $count = match ($m[1]) { 'two', '2' => 2, 'three', '3' => 3, default => 4 };
            $count = min($count, $recentCount);
            return $count >= 2 ? range(1, $count) : [];
        }
        if (preg_match('/\b(?:these\s+two|both(?:\s+products?)?)\b/u', $message)) {
            return $recentCount >= 2 ? [1, 2] : [];
        }

        preg_match_all('/\b(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\b/u', $message, $matches);
        $indexes = [];
        foreach (($matches[1] ?? []) as $ordinal) {
            $index = $ordinal === 'last' ? $recentCount : $this->ordinalToIndex((string)$ordinal);
            if ($index > 0 && ($recentCount === 0 || $index <= $recentCount)) {
                $indexes[] = $index;
            }
        }
        return array_slice(array_values(array_unique($indexes)), 0, 4);
    }

    private function isCartSummaryRequest(string $message): bool
    {
        return (bool)preg_match('/\b(?:what(?:\'s| is) in my (?:cart|basket)|what items are in my basket|show (?:me )?(?:my )?(?:cart|basket)(?: items)?|view my (?:cart|basket)|display my cart|check my basket|cart summary|cart subtotal|cart total|how many items (?:are )?in my cart)\b/u', $message);
    }

    private function isClearCartRequest(string $message): bool
    {
        return (bool)preg_match('/\b(?:clear|empty)\s+(?:all\s+)?(?:items\s+from\s+)?(?:(?:my|the)\s+)?(?:cart|basket)\b|\bremove\s+(?:all|everything)\s+(?:items|products)?\s*from\s+(?:my\s+)?(?:cart|basket)\b/u', $message);
    }

    private function cartUpdateRequest(string $message): ?array
    {
        $patterns = [
            '/\b(?:set|change|update|make)\s+(?:the\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s*(?:(?:cart\s+)?(?:item|product|one)?\s+)?(?:quantity|qty)\s*(?:to|=)?\s*(\d+(?:\.\d+)?)\b/u',
            '/\b(?:set|change|update)\s+(?:the\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s*(?:(?:cart\s+)?(?:item|product|one)?\s+)?to\s+(\d+(?:\.\d+)?)\b/u',
            '/\b(?:set|change|update)\s+(?:the\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s+(?:cart\s+)?item\s+to\s+(?:quantity\s+)?(\d+(?:\.\d+)?)\b/u',
            '/\b(?:set|change|update)\s+(?:the\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s+item\s+in\s+cart\s+to\s+(\d+(?:\.\d+)?)\b/u',
            '/\b(?:set|change|update)\s+(?:the\s+)?quantity\s+of\s+(?:the\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s+(?:cart\s+)?item\s+to\s+(\d+(?:\.\d+)?)\b/u',
            '/\bmake\s+(?:the\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s+(?:cart\s+)?item\s+(\d+(?:\.\d+)?)\b/u',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $message, $m)) {
                continue;
            }
            $index = $this->ordinalToIndex($m[1]);
            if ($index < 0) {
                return null;
            }
            return [
                'index' => $index,
                'quantity' => max(0.0, min(100.0, (float)$m[2])),
            ];
        }
        return null;
    }


    private function skuAddRequest(string $message): ?array
    {
        if (!preg_match('/\badd\s+(?:product\s+)?(?:with\s+)?sku\s+[\[\"\']?([a-zA-Z0-9_.\/-]{1,64})[\]\"\']?\s+(?:to|into)\s+(?:my\s+)?(?:cart|basket)\b/ui', $message, $m)) {
            return null;
        }
        return [
            'sku' => (string)$m[1],
            'quantity' => $this->quantity(mb_strtolower($message)),
        ];
    }

    private function recentAddIndex(string $message, array $context): int
    {
        if (!preg_match('/\b(?:add|put)\s+(?:(\d+(?:\.\d+)?)\s+of\s+)?(?:the\s+)?(first|second|third|fourth|fifth|sixth|seventh|eighth|last|\d+(?:st|nd|rd|th))\s*(?:shown\s+)?(?:one|product|item)?\s+(?:to|into|in)\s+(?:my\s+)?(?:cart|basket)\b/u', $message, $m)) {
            return 0;
        }
        $ordinal = (string)($m[2] ?? $m[1] ?? '');
        if ($ordinal === 'last') {
            return count((array)($context['recent_products'] ?? []));
        }
        return $this->ordinalToIndex($ordinal);
    }

    private function recentAddNameIndex(string $message, array $context): int
    {
        if (!preg_match('/\badd\b.*\b(?:cart|basket)\b/u', $message)) {
            return 0;
        }
        $normalizedMessage = $this->normalizeName($message);
        $matches = [];
        foreach ((array)($context['recent_products'] ?? []) as $index => $product) {
            $name = $this->normalizeName((string)($product['name'] ?? ''));
            if (mb_strlen($name) < 8) {
                continue;
            }
            if (str_contains($normalizedMessage, $name)) {
                $matches[] = $index + 1;
                continue;
            }
            // Legacy UI builds sometimes sent a truncated product name. Require a substantial unique prefix.
            $prefix = mb_substr($name, 0, min(32, mb_strlen($name)));
            if (mb_strlen($prefix) >= 12 && str_contains($normalizedMessage, $prefix)) {
                $matches[] = $index + 1;
            }
        }
        $matches = array_values(array_unique($matches));
        return count($matches) === 1 ? (int)$matches[0] : 0;
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function cartRemoveIndex(string $message): int
    {
        if (!preg_match('/\b(?:remove|delete|take)\s+(?:the\s+)?(first|second|third|fourth|fifth|last|\d+(?:st|nd|rd|th))\s*(?:cart\s+)?(?:item|product|one)?(?:(?:\s+out)?\s+(?:from|of)\s+(?:my\s+)?(?:cart|basket))?\b/u', $message, $m)) {
            return -1;
        }
        return $m[1] === 'last' ? 0 : $this->ordinalToIndex($m[1]);
    }

    private function quantity(string $message): float
    {
        if (preg_match('/\b(?:qty|quantity)\s*(\d+(?:\.\d+)?)\b/u', $message, $m) || preg_match('/\b(?:add|put)\s+(\d+(?:\.\d+)?)\s+of\b/u', $message, $m)) {
            return max(1.0, min(100.0, (float)$m[1]));
        }
        return 1.0;
    }

    private function requestedPageSize(string $message): int
    {
        if (preg_match('/^(?:please\s+)?(?:show|find|give|list)\s+(?:me\s+)?(?:the\s+)?(\d{1,2})\b/u', trim($message), $m)) {
            return max(1, min(24, (int)$m[1]));
        }
        if (preg_match('/\b(?:top|best)\s+(\d{1,2})\b/u', $message, $m)) {
            return max(1, min(24, (int)$m[1]));
        }
        return 0;
    }

    private function extractFilters(string $message): array
    {
        $filters = [];
        if (preg_match('/\bbetween\s*(?:₹|rs\.?|inr|\$)?\s*([\d,.]+)\s*(?:and|to|-)\s*(?:₹|rs\.?|inr|\$)?\s*([\d,.]+)/u', $message, $m)) {
            $filters['price'] = ['attribute' => 'price', 'condition' => 'range', 'values' => [$this->number($m[1]), $this->number($m[2])], 'label' => 'Price'];
        } elseif (preg_match('/\b(?:under|below|less than|up to|max(?:imum)?(?: of)?)\s*(?:₹|rs\.?|inr|\$)?\s*([\d,.]+)/u', $message, $m)) {
            $filters['price'] = ['attribute' => 'price', 'condition' => 'to', 'values' => [$this->number($m[1])], 'label' => 'Price'];
        } elseif (preg_match('/\b(?:above|over|more than|at least|min(?:imum)?(?: of)?)\s*(?:₹|rs\.?|inr|\$)?\s*([\d,.]+)/u', $message, $m)) {
            $filters['price'] = ['attribute' => 'price', 'condition' => 'from', 'values' => [$this->number($m[1])], 'label' => 'Price'];
        }

        foreach ($this->metadataService->getMetadata() as $meta) {
            $code = (string)$meta['code'];
            if (($meta['options'] ?? []) !== []) {
                $matches = [];
                $excludes = [];
                foreach ($meta['options'] as $option) {
                    $label = mb_strtolower(trim((string)$option['label']));
                    if ($label === '') {
                        continue;
                    }
                    $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($label, '/') . '(?![\p{L}\p{N}])/u';
                    if (!preg_match($pattern, $message)) {
                        continue;
                    }
                    if (preg_match('/\bremove\s+(?:the\s+)?' . preg_quote($label, '/') . '\b/u', $message)) {
                        continue;
                    }
                    if (preg_match('/\b(?:no|not|without|exclude)\s+(?:the\s+)?' . preg_quote($label, '/') . '\b/u', $message)) {
                        $excludes[] = (string)$option['value'];
                    } else {
                        $matches[] = (string)$option['value'];
                    }
                }
                if ($matches !== []) {
                    $filters[$code] = [
                        'attribute' => $code,
                        'condition' => count($matches) > 1 ? 'in' : 'eq',
                        'values' => array_values(array_unique($matches)),
                        'label' => (string)$meta['label'],
                    ];
                } elseif ($excludes !== []) {
                    $filters[$code] = [
                        'attribute' => $code,
                        'condition' => 'nin',
                        'values' => array_values(array_unique($excludes)),
                        'label' => (string)$meta['label'],
                    ];
                }
                continue;
            }
            if (($meta['frontend_input'] ?? '') === 'boolean') {
                $label = mb_strtolower((string)$meta['label']);
                if ($label !== '' && str_contains($message, $label)) {
                    $filters[$code] = [
                        'attribute' => $code,
                        'condition' => 'eq',
                        'values' => [str_contains($message, 'not ' . $label) ? '0' : '1'],
                        'label' => (string)$meta['label'],
                    ];
                }
            }
        }
        return $filters;
    }

    private function contextFilters(array $context): array
    {
        $result = [];
        foreach (($context['filters'] ?? []) as $filter) {
            if (is_array($filter) && !empty($filter['attribute'])) {
                $result[(string)$filter['attribute']] = $filter;
            }
        }
        return $result;
    }

    private function removeRequestedFilters(array $filters, string $message): array
    {
        if (!preg_match('/\b(?:remove|clear)\b/u', $message)) {
            return $filters;
        }
        if (preg_match('/\b(?:remove|clear)\s+(?:all\s+)?filters?\b/u', $message)) {
            return [];
        }
        foreach ($filters as $code => &$filter) {
            $label = mb_strtolower((string)($filter['label'] ?? $code));
            $humanCode = mb_strtolower(str_replace('_', ' ', (string)$code));
            if (str_contains($message, $label) || str_contains($message, $humanCode)) {
                unset($filters[$code]);
                continue;
            }
            $values = array_values(array_map('strval', (array)($filter['values'] ?? [])));
            $remaining = [];
            $removedAny = false;
            foreach ($values as $value) {
                if (str_contains($message, mb_strtolower($value))) {
                    $removedAny = true;
                    continue;
                }
                $remaining[] = $value;
            }
            if ($removedAny) {
                if ($remaining === []) {
                    unset($filters[$code]);
                } else {
                    $filter['values'] = $remaining;
                    $filter['condition'] = count($remaining) > 1 ? 'in' : 'eq';
                }
            }
        }
        unset($filter);
        return $filters;
    }

    private function extractSearchPhrase(string $message, array $extractedFilters = []): string
    {
        $phrase = mb_strtolower($message);
        $phrase = preg_replace('/\b(?:under|below|less than|up to|max(?:imum)?(?: of)?|above|over|more than|at least|min(?:imum)?(?: of)?)\s*(?:₹|rs\.?|inr|\$)?\s*[\d,.]+/iu', ' ', $phrase) ?? $phrase;
        $phrase = preg_replace('/\bbetween\s*(?:₹|rs\.?|inr|\$)?\s*[\d,.]+\s*(?:and|to|-)\s*(?:₹|rs\.?|inr|\$)?\s*[\d,.]+/iu', ' ', $phrase) ?? $phrase;
        $phrase = preg_replace('/^(?:please\s+)?(?:show|find|give|list)\s+(?:me\s+)?(?:the\s+)?\d{1,2}\b/iu', ' ', $phrase) ?? $phrase;
        $phrase = preg_replace('/\b(?:top|best)\s+\d{1,2}\b/iu', ' ', $phrase) ?? $phrase;
        $phrase = preg_replace('/\b(?:best[ -]?sell(?:ing|er|ers)?|best sales?|most (?:popular|purchased|ordered)|cheap(?:est|er)?|more affordable|low(?:est)? price|costliest|expensive|most expensive|high(?:est)? price|latest|newest|most recent)\s+first\b/iu', ' ', $phrase) ?? $phrase;
        $phrase = preg_replace('/\b(?:best[ -]?sell(?:ing|er|ers)?|best sales?|most (?:popular|purchased|ordered)|cheap(?:est|er)?|more affordable|low(?:est)? price|costliest|expensive|most expensive|high(?:est)? price|price low to high|price high to low|low to high|high to low|latest|newest|most recent|recently added|new arrivals?|sort by|show me|find me|find|show|list|give me|the|a|an|only|please|no|not|without|exclude|remove|clear|filter|but|products?|options?|need|want|looking|for|what(?:\'s|s)?|which|do|does|is|are|you|have|available)\b/iu', ' ', $phrase) ?? $phrase;
        $codes = array_fill_keys(array_keys($extractedFilters), true);
        foreach ($this->metadataService->getMetadata() as $meta) {
            if (!isset($codes[$meta['code']])) {
                continue;
            }
            $label = trim((string)$meta['label']);
            if ($label !== '') {
                $phrase = preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($label, '/') . '(?![\p{L}\p{N}])/iu', ' ', $phrase) ?? $phrase;
            }
            foreach (($meta['options'] ?? []) as $option) {
                $optionLabel = trim((string)$option['label']);
                if ($optionLabel !== '') {
                    $phrase = preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($optionLabel, '/') . '(?![\p{L}\p{N}])/iu', ' ', $phrase) ?? $phrase;
                }
            }
        }
        $phrase = preg_replace('/[^\p{L}\p{N}._-]+/u', ' ', $phrase) ?? $phrase;
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $phrase) ?? $phrase), 0, 180);
    }

    private function isRefinementOnly(string $message, string $phrase, array $extracted): bool
    {
        if ($phrase === '') {
            return true;
        }
        if (preg_match('/^\s*(?:remove|clear)\b/u', $message)) {
            return true;
        }
        if (preg_match('/\b(?:cheap(?:est|er)?|more affordable|low(?:est)? price|costliest|expensive|most expensive|high(?:est)? price|low to high|high to low|alphabetical)\b/u', $message)
            && $extracted === [] && $this->requestedPageSize($message) === 0) {
            return true;
        }
        return false;
    }

    private function isFreshCatalogSortRequest(string $message, string $phrase, array $sort): bool
    {
        if ($phrase !== '' || $sort === []) {
            return false;
        }

        // An explicit catalog noun means "sort the catalog". A relative request such as
        // "show cheaper options" intentionally keeps the current query and filters.
        return (bool)preg_match('/\b(?:products?|items?|catalog|courses?|new arrivals?)\b/u', $message);
    }

    private function shouldSearchCatalog(string $message, string $phrase, array $extractedFilters, array $sort, array $context): bool
    {
        if (preg_match('/^\s*(?:(?:what(?:\'s|s|\s+is)|calculate|solve)\s+)?[-+*\/().\d\s]+[?!.]*\s*$/u', $message)) {
            return false;
        }
        if ($extractedFilters !== [] || $sort !== [] || preg_match('/\b(?:in[ -]?stock|available now|currently available)\b/u',$message) || preg_match('/^\s*(?:remove|clear)\b.*\b(?:filters?|price|brand|color|colour|size|limit)\b/u', $message)) {
            return true;
        }
        if (preg_match('/^(?:who|why|when|where|how|what|can|could|would|do|does|is|are)\b/u', $message)
            && !preg_match('/\b(?:product|products|item|items|course|courses|price|prices|buy|shop|find|show|search|recommend|available|stock|size|color|colour|brand)\b/u', $message)) {
            return false;
        }
        if ($phrase === '') {
            return (bool)preg_match('/\b(?:products?|items?|catalog|shop|browse|latest|newest|arrivals?)\b/u', $message);
        }
        if (trim((string)($context['query_phrase'] ?? '')) !== ''
            && preg_match('/\b(?:same|those|these|ones?|results?|cheaper|costlier|expensive|affordable|sort|filter|only|without|exclude|remove|clear)\b/u', $message)) {
            return true;
        }
        return true;
    }

    private function ordinalToIndex(string $ordinal): int
    {
        $map = ['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'sixth' => 6, 'seventh' => 7, 'eighth' => 8];
        if (isset($map[$ordinal])) {
            return $map[$ordinal];
        }
        return (int)preg_replace('/\D+/', '', $ordinal);
    }

    private function number(string $value): string
    {
        return (string)(float)str_replace([',', ' '], '', $value);
    }
}
