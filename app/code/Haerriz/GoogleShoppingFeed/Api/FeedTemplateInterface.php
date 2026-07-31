<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface FeedTemplateInterface
{
    public function getCode(): string;
    public function getName(): string;
    public function getFormat(): string;
}
