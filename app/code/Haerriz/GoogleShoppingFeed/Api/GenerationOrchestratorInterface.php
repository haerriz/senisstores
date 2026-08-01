<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface GenerationOrchestratorInterface
{
    public function generate(\Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface $profile, string $trigger = 'manual'): \Haerriz\GoogleShoppingFeed\Api\Data\GenerationResultInterface;
}
