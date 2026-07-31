<?php
namespace Haerriz\GoogleShoppingFeed\Model\Product\Type;

class Pool
{
    private array $strategies;

    public function __construct(array $strategies = [])
    {
        $this->strategies = $strategies;
    }

    public function getStrategy(string $typeId): TypeStrategyInterface
    {
        if (isset($this->strategies[$typeId]) && $this->strategies[$typeId] instanceof TypeStrategyInterface) {
            return $this->strategies[$typeId];
        }
        // Default fallback to simple strategy
        if (isset($this->strategies['simple'])) {
            return $this->strategies['simple'];
        }
        return new Simple();
    }

    public function hasStrategy(string $typeId): bool
    {
        return isset($this->strategies[$typeId]);
    }
}
