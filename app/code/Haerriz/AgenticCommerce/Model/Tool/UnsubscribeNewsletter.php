<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Customer\NewsletterService;

class UnsubscribeNewsletter implements ToolInterface
{
    public function __construct(private NewsletterService $newsletter) {}
    public function getName(): string { return 'unsubscribe_newsletter'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Unsubscribe the signed-in customer from the store newsletter after an explicit request.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $s=$this->newsletter->unsubscribe((array)$context['identity']); return ['newsletter'=>$s,'assistant_message'=>$s['assistant_message']];
    }
}
