<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Suggestion;

use Haerriz\AgenticCommerce\Api\SuggestionProviderInterface;

class CoreSuggestionProvider implements SuggestionProviderInterface
{
    public function getSuggestions(array $response): array
    {
        $suggestions=[];
        $products=is_array($response['products']??null)?$response['products']:[];
        if(count($products)>=2)$suggestions[]=(string)__('Compare the first two');
        if($products!==[]){$suggestions[]=(string)__('Show cheaper options');$suggestions[]=(string)__('Recommend products similar to the first one');}
        if(!empty($response['cart']['items'])){$suggestions[]=(string)__('What is in my cart?');$suggestions[]=(string)__('Open checkout');}
        if($products===[]&&empty($response['cart'])&&empty($response['knowledge'])&&empty($response['actions'])){
            $suggestions[]=(string)__('Show latest products');$suggestions[]=(string)__('Show cheapest products');
        }
        return $suggestions;
    }
}
