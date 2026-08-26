<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

interface FeedbackInterface
{
    /** @return mixed[] */
    public function submit(string $conversationId,string $message,string $toolName,int $rating,?string $clientId=null,?string $comment=null): array;
}
