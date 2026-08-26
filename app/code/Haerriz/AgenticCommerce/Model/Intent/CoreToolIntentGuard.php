<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Intent;

use Haerriz\AgenticCommerce\Api\ToolIntentGuardInterface;

/** English core intent grammar. Replace/precede with DI-registered locale/vertical guards as needed. */
class CoreToolIntentGuard implements ToolIntentGuardInterface
{
    public function __construct(private KnowledgeIntentMatcher $knowledgeIntentMatcher) {}

    public function supports(string $toolName):bool{return true;}
    public function isAllowed(string $toolName,string $message,array $arguments,array $context=[]):bool
    {
        $lower=mb_strtolower($message);
        return match($toolName){
            'search_products'=>$this->isCatalogSearchIntent($lower),
            'compare_recent_products','compare_products'=>(bool)preg_match('/\bcompare\b/u',$lower),
            'get_product_content'=>(bool)preg_match('/\b(?:description|describe|short description|features?|specifications?|specs|additional information|images?|photos?|gallery)\b/u',$lower),
            'answer_product_question'=>(bool)preg_match('/\b(?:does|do|is|are|can|could|will|would|has|have)\b/u',$lower),
            'get_store_information'=>$this->isStoreInformationIntent($lower),
            'answer_store_question'=>$this->isKnowledgeIntent($lower),
            'get_recommendations'=>(bool)preg_match('/\b(?:recommend|recommendations?|similar|alternatives?|goes with|pair with|upsell|cross[- ]?sell)\b/u',$lower),
            'add_recent_product_to_cart'=>(bool)preg_match('/\b(?:add|put)\b.*\b(?:cart|basket)\b/u',$lower),
            'add_product_to_cart'=>$this->explicitSku($lower,(string)($arguments['sku']??''),'/\b(?:add|put)\b.*\b(?:cart|basket)\b/u'),
            'remove_cart_item'=>(bool)preg_match('/\b(?:remove|delete)\b.*\b(?:cart|basket)\b/u',$lower),
            'update_cart_item'=>(bool)preg_match('/\b(?:set|change|update|make)\b.*\b(?:qty|quantity|cart|basket)\b/u',$lower),
            'clear_cart'=>(bool)preg_match('/\b(?:clear|empty)\b.*\b(?:cart|basket)\b|\bremove\s+all\b.*\b(?:cart|basket)\b/u',$lower),
            'apply_coupon'=>$this->explicitSku($lower,(string)($arguments['code']??''),'/\b(?:apply|use|add)\b.*\b(?:coupon|promo|discount code)\b/u'),
            'remove_coupon'=>(bool)preg_match('/\b(?:remove|delete|clear)\b.*\b(?:coupon|promo|discount code)\b/u',$lower),
            'get_wishlist'=>(bool)preg_match('/\b(?:wishlist|wish list|saved items?)\b/u',$lower),
            'add_recent_product_to_wishlist'=>(bool)preg_match('/\b(?:save|add)\b.*\b(?:wishlist|wish list|saved items?)\b/u',$lower),
            'add_product_to_wishlist'=>$this->explicitSku($lower,(string)($arguments['sku']??''),'/\b(?:save|add)\b.*\b(?:wishlist|wish list|saved items?)\b/u'),
            'remove_wishlist_item'=>(bool)preg_match('/\b(?:remove|delete)\b.*\b(?:wishlist|wish list|saved items?)\b/u',$lower),
            'get_recent_orders','get_order'=>(bool)preg_match('/\b(?:order|orders|tracking|shipment|delivery status)\b/u',$lower),
            'get_catalog_navigation'=>(bool)preg_match('/\b(?:categories|browse the catalog)\b/u',$lower),
            'get_product_options'=>(bool)preg_match('/\b(?:option|options|variant|variants|size|color|colour|configuration|bundle|choice|choose)\b/u',$lower),
            'get_inventory','compare_inventory','get_variant_availability'=>(bool)preg_match('/\b(?:stock|in[ -]?stock|out[ -]?of[ -]?stock|availability|inventory|remaining|left|available|can i buy|which.*stock|compare.*stock)\b/u',$lower),
            'get_product_experience'=>(bool)preg_match('/\b(?:details?|everything|full info|tell me about|product info|overview)\b/u',$lower),
            'get_product_price'=>(bool)preg_match('/\b(?:price|cost|how much|discount|special price|tier price)\b/u',$lower),
            'get_checkout_state','get_shipping_methods','get_payment_methods'=>(bool)preg_match('/\b(?:checkout|shipping|delivery method|payment method|ready to order)\b/u',$lower),
            'get_customer_profile','get_customer_addresses','request_customer_form','prepare_delete_saved_address'=>(bool)preg_match('/\b(?:my profile|my account|saved address|my address|addresses|edit profile|change profile|add address|new address|delete address|remove address)\b/u',$lower),
            'use_saved_shipping_address','use_saved_billing_address'=>(bool)preg_match('/\b(?:use|select|choose)\b.*\b(?:saved|first|second|third|address)\b.*\b(?:shipping|billing|address)\b/u',$lower),
            'set_shipping_method'=>(bool)preg_match('/\b(?:select|choose|use|set)\b.*\b(?:shipping|delivery)\b/u',$lower),
            'set_payment_method'=>(bool)preg_match('/\b(?:select|choose|use|set)\b.*\bpayment\b/u',$lower),
            'get_newsletter_status','subscribe_newsletter','unsubscribe_newsletter'=>(bool)preg_match('/\bnewsletter\b/u',$lower),
            'get_product_reviews','submit_product_review'=>(bool)preg_match('/\b(?:review|reviews|rating|feedback)\b/u',$lower),
            'subscribe_product_alert'=>(bool)preg_match('/\b(?:alert|notify me|price drop|back in stock|in stock)\b/u',$lower),
            'get_store_context'=>(bool)preg_match('/\b(?:currency|store view|language|which store|current store)\b/u',$lower),
            'prepare_place_order'=>(bool)preg_match('/\b(?:place|submit|complete|finish)\b.*\border\b|\bbuy now\b/u',$lower),
            'confirm_pending_action'=>(bool)preg_match('/\b(?:confirm|yes,? place|place it|confirm order)\b/u',$lower),
            'cancel_pending_action'=>(bool)preg_match('/\b(?:cancel|never mind|do not)\b.*\b(?:pending|order|confirmation)\b/u',$lower),
            default=>true,
        };
    }
    private function explicitSku(string $message,string $value,string $intentPattern):bool{$value=trim($value);return$value!==''&&(bool)preg_match($intentPattern,$message)&&str_contains($message,mb_strtolower($value));}
    private function isCatalogSearchIntent(string $message):bool
    {
        if(preg_match('/\bcompare\b/u',$message)||$this->isStoreInformationIntent($message)||$this->isKnowledgeIntent($message))return false;
        if(preg_match('/^(?:hi|hello|hey|good\s+(?:morning|afternoon|evening))\b/u',$message)||preg_match('/\b(?:what can you do|how can you help|your capabilities)\b/u',$message))return false;
        if(preg_match('/\b(?:my\s+cart|wishlist|wish list|my\s+orders?|order\s+#?|coupon|promo code|checkout|shipping method|payment method|saved address|my address|my profile|my account|newsletter|review|rating|product alert|notify me|currency|store view|confirm order|place order)\b/u',$message)&&!preg_match('/\b(?:product|products|item|items|course|courses|shop|find|show|search|buy|recommend)\b/u',$message))return false;
        return true;
    }
    private function isStoreInformationIntent(string $message):bool{return(bool)preg_match('/\b(?:who\s+are\s+you|what\s+(?:website|site|store)\s+is\s+this|which\s+(?:website|site|store)|who\s+(?:is\s+(?:the\s+)?owner\s+of|owns|runs|operates)\s+(?:this|the)\s+(?:website|site|store)|(?:website|site|store)\s+owner|what\s+can\s+you\s+do|your\s+capabilities|how\s+can\s+you\s+help|contact(?:\s+details?)?|contact\s+number|phone(?:\s+number)?|telephone|customer\s+care|support\s+number|email(?:\s+address)?|(?:store|business)\s+address|address\s+of\s+(?:the\s+)?(?:shop|store|site|website)|what(?:\'s|s|\s+is)\s+(?:the\s+|your\s+)?address|opening\s+hours?|store\s+hours?|timings?)\b/u',$message);}
    private function isKnowledgeIntent(string $message):bool
    {
        return $this->knowledgeIntentMatcher->matches($message);
    }
}
