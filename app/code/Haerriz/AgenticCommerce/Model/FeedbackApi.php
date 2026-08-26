<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model;

use Haerriz\AgenticCommerce\Api\FeedbackInterface;
use Haerriz\AgenticCommerce\Model\Identity\IdentityResolver;
use Haerriz\AgenticCommerce\Model\Learning\FeedbackService;

class FeedbackApi implements FeedbackInterface
{
    public function __construct(private FeedbackService $feedback,private IdentityResolver $identity) {}
    public function submit(string $conversationId,string $message,string $toolName,int $rating,?string $clientId=null,?string $comment=null): array
    {
        return $this->feedback->submit($conversationId,$message,$toolName,$rating,$this->identity->resolve(null,$clientId,'rest'),$comment);
    }
}
