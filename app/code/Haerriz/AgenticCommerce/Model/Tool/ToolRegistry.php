<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $toolsByName = [];

    /** @param ToolInterface[] $tools */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $this->toolsByName[$tool->getName()] = $tool;
            }
        }
    }

    public function getNames(): array
    {
        return array_keys($this->toolsByName);
    }

    public function has(string $name): bool
    {
        return isset($this->toolsByName[$name]);
    }

    public function getDefinitions(): array
    {
        return array_values(array_map(static fn(ToolInterface $tool): array => $tool->getDefinition(), $this->toolsByName));
    }

    public function execute(string $name, array $arguments, array $context = []): array
    {
        if (!isset($this->toolsByName[$name])) {
            return ['error' => 'Unsupported tool: ' . $name];
        }
        return $this->toolsByName[$name]->execute($arguments, $context);
    }
}
