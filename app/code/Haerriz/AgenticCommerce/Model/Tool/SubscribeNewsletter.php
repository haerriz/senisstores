<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Customer\NewsletterService;

class SubscribeNewsletter implements ToolInterface
{
    public function __construct(private NewsletterService $newsletter) {}
    public function getName(): string { return 'subscribe_newsletter'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Subscribe the signed-in customer to the store newsletter after an explicit request.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $s=$this->newsletter->subscribe((array)$context['identity']); return ['newsletter'=>$s,'assistant_message'=>$s['assistant_message']];
    }
}
