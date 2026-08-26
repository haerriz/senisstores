<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Action;

use Haerriz\AgenticCommerce\Api\DirectActionSanitizerInterface;

class CoreArgumentSanitizer implements DirectActionSanitizerInterface
{
    private const SUPPORTED=['add_product_to_cart','add_product_to_wishlist','remove_wishlist_item','remove_cart_item','update_cart_item','apply_coupon','remove_coupon','clear_cart','get_cart','get_product_options','get_product_content','compare_products','get_variant_availability','get_checkout_state','get_shipping_methods','set_shipping_method','get_payment_methods','set_payment_method','subscribe_newsletter','unsubscribe_newsletter'];
    public function supports(string $toolName): bool { return in_array($toolName,self::SUPPORTED,true); }
    public function sanitize(string $toolName,array $arguments):array
    {
        return match($toolName){
            'add_product_to_cart'=>['sku'=>mb_substr(trim((string)($arguments['sku']??'')),0,64),'quantity'=>max(1.0,min(10000.0,(float)($arguments['quantity']??1))),'selections'=>is_array($arguments['selections']??null)?array_slice($arguments['selections'],0,50):[]],
            'add_product_to_wishlist'=>['sku'=>mb_substr(trim((string)($arguments['sku']??'')),0,64)],
            'remove_wishlist_item'=>['index'=>max(1,min(100,(int)($arguments['index']??1)))],
            'remove_cart_item'=>['index'=>max(0,min(100,(int)($arguments['index']??1)))],
            'update_cart_item'=>['index'=>max(0,min(100,(int)($arguments['index']??1))),'quantity'=>max(0.0,min(10000.0,(float)($arguments['quantity']??1)))],
            'apply_coupon'=>['code'=>mb_substr(trim((string)($arguments['code']??'')),0,64)],
            'get_product_options','get_product_content'=>['sku'=>mb_substr(trim((string)($arguments['sku']??'')),0,64)],
            'compare_products'=>['skus'=>array_slice(array_values(array_unique(array_filter(array_map(static fn($v):string=>mb_substr(trim((string)$v),0,64),(array)($arguments['skus']??[]))))),0,4),'focus'=>array_slice(array_values(array_unique(array_filter(array_map(static fn($v):string=>mb_substr(trim((string)$v),0,32),(array)($arguments['focus']??[]))))),0,7),'goal'=>mb_substr(trim((string)($arguments['goal']??'')),0,500)],
            'get_variant_availability'=>['sku'=>mb_substr(trim((string)($arguments['sku']??'')),0,64),'requested_qty'=>max(0.0001,min(10000.0,(float)($arguments['requested_qty']??1))),'query'=>mb_substr(trim((string)($arguments['query']??'')),0,500),'selections'=>is_array($arguments['selections']??null)?array_slice($arguments['selections'],0,20):[]],
            'set_shipping_method'=>['carrier_code'=>mb_substr(trim((string)($arguments['carrier_code']??'')),0,64),'method_code'=>mb_substr(trim((string)($arguments['method_code']??'')),0,64)],
            'set_payment_method'=>['method_code'=>mb_substr(trim((string)($arguments['method_code']??'')),0,128)],
            'remove_coupon','clear_cart','get_cart','get_checkout_state','get_shipping_methods','get_payment_methods','subscribe_newsletter','unsubscribe_newsletter'=>[],
            default=>[],
        };
    }
}
