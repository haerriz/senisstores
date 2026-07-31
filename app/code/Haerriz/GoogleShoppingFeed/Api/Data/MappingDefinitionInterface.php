<?php
namespace Haerriz\GoogleShoppingFeed\Api\Data;

interface MappingDefinitionInterface
{
    public function getTargetField(): string;
    public function getSourceAttribute(): string;
}
