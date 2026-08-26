<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Customer\NewsletterService;

class GetNewsletterStatus implements ToolInterface
{
    public function __construct(private NewsletterService $newsletter) {}
    public function getName(): string { return 'get_newsletter_status'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Read signed-in customer newsletter subscription status.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $s=$this->newsletter->status((array)$context['identity']); return ['newsletter'=>$s,'assistant_message'=>$s['subscribed']?(string)__('You are subscribed to the newsletter.'):(string)__('You are not subscribed to the newsletter.')];
    }
}
