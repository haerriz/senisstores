<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface FeedTemplateRegistryInterface
{
    public function getTemplate(string $code): FeedTemplateInterface;
    public function getTemplates(): array;
}
