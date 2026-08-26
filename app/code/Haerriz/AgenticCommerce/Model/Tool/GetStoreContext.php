<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Store\StoreContextService;

class GetStoreContext implements ToolInterface
{
    public function __construct(private StoreContextService $storeContext) {}
    public function getName(): string { return 'get_store_context'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Read current store view, website and currency choices.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $s=$this->storeContext->get((int)$context['identity']['store_id']); return ['store_context'=>$s,'assistant_message'=>(string)__('You are shopping in %1 using %2.',$s['store_name'],$s['currency'])];
    }
}
