<?php
namespace Haerriz\GoogleShoppingFeed\Model\Mapping;

use Haerriz\GoogleShoppingFeed\Api\MappingCompilerInterface;

class Compiler implements MappingCompilerInterface
{
    public function compile(array $mapping): array
    {
        return $mapping;
    }
}
