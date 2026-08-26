<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Product\ProductAlertService;

class SubscribeProductAlert implements ToolInterface
{
    public function __construct(private ProductAlertService $alerts) {}
    public function getName(): string { return 'subscribe_product_alert'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Subscribe signed-in shopper to price or stock alert for exact SKU.','parameters'=>['type'=>'object','properties'=>['sku'=>['type'=>'string'],'type'=>['type'=>'string','enum'=>['price','stock']]],'required'=>['sku','type']]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        return $this->alerts->subscribe((array)$context['identity'],(string)($arguments['sku']??''),(string)($arguments['type']??''));
    }
}
