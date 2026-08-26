<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ProductOptionService;

class GetProductOptions implements ToolInterface
{
    public function __construct(private ProductOptionService $options) {}
    public function getName(): string { return 'get_product_options'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Return required configurable, bundle, grouped, downloadable and custom options for an exact SKU.','parameters'=>['type'=>'object','properties'=>['sku'=>['type'=>'string']],'required'=>['sku']]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $sku=trim((string)($arguments['sku']??'')); if($sku==='') return ['assistant_message'=>(string)__('Tell me which product SKU to inspect.')]; $data=$this->options->describe($sku,(int)$context['identity']['store_id']); return ['product_options'=>$data,'assistant_message'=>$data['requires_options']?(string)__('Choose the required options for %1.',$data['name']):(string)__('%1 does not require product options.',$data['name'])];
    }
}
