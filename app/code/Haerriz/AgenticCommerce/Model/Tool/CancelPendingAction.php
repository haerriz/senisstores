<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Confirmation\ConfirmationService;

class CancelPendingAction implements ToolInterface
{
    public function __construct(private ConfirmationService $confirmations) {}
    public function getName(): string { return 'cancel_pending_action'; }
    public function getDefinition(): array { return ['type'=>'function','function'=>['name'=>$this->getName(),'description'=>'Cancel the latest pending consequential action.','parameters'=>['type'=>'object','properties'=>new \stdClass()]]]; }
    public function execute(array $arguments, array $context = []): array
    {
        $ok=$this->confirmations->cancelLatest((string)($context['conversation_public_id']??''),(array)$context['identity']); return ['assistant_message'=>$ok?(string)__('The pending action was cancelled.'):(string)__('There is no pending action to cancel.')];
    }
}
