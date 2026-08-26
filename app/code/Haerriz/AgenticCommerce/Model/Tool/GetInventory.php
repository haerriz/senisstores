<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\VariantAvailabilityService;
use Magento\Framework\Exception\LocalizedException;

/**
 * Variant-aware inventory entry point. A configurable parent is never reported as though its parent
 * stock quantity were the shopper's exact variant quantity; the service resolves visible choices to
 * child variants first. Simple/virtual/downloadable products collapse to one exact candidate.
 */
class GetInventory implements ToolInterface
{
    public function __construct(private VariantAvailabilityService $variants) {}
    public function getName(): string { return 'get_inventory'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Check storefront-safe stock/salability and remaining salable quantity for an exact SKU or recent product. Configurable products resolve shopper-visible variant labels to child inventory.',
            'parameters'=>['type'=>'object','properties'=>[
                'sku'=>['type'=>'string'],
                'index'=>['type'=>'integer','minimum'=>1,'maximum'=>24],
                'requested_qty'=>['type'=>'number','minimum'=>0.0001,'maximum'=>10000],
                'query'=>['type'=>'string','maxLength'=>500],
                'selections'=>['type'=>'array','items'=>['type'=>'object','properties'=>['code'=>['type'=>'string'],'values'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['code','values']]],
            ]],
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $sku = trim((string)($arguments['sku'] ?? ''));
        if ($sku === '' && !empty($arguments['index'])) {
            $index = max(1, (int)$arguments['index']);
            $recent = (array)($context['recent_products'] ?? []);
            $sku = trim((string)($recent[$index - 1]['sku'] ?? ''));
        }
        if ($sku === '') throw new LocalizedException(__('Tell me which product or SKU you want stock information for.'));
        $data = $this->variants->resolve(
            $sku,
            (int)($context['identity']['store_id'] ?? 0),
            is_array($arguments['selections'] ?? null) ? $arguments['selections'] : [],
            mb_substr(trim((string)($arguments['query'] ?? '')), 0, 500),
            max(0.0001, (float)($arguments['requested_qty'] ?? 1)),
            (int)($context['identity']['customer_group_id'] ?? 0)
        );
        $result = ['variant_availability'=>$data,'assistant_message'=>(string)$data['assistant_message']];
        if (!empty($data['complete']) && count((array)($data['candidates'] ?? [])) === 1) {
            $result['inventory'] = (array)$data['candidates'][0]['inventory'];
        }
        return $result;
    }
}
