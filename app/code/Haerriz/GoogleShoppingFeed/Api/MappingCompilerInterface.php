<?php
namespace Haerriz\GoogleShoppingFeed\Api;

interface MappingCompilerInterface
{
    public function compile(array $mapping): array;
}
