<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

interface ToolInterface
{
    public function getName(): string;
    public function getDefinition(): array;
    public function execute(array $arguments, array $context = []): array;
}
