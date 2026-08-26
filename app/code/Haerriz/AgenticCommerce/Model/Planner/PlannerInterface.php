<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Planner;

interface PlannerInterface
{
    /** @return mixed[] */
    public function plan(string $message, array $context = []): array;
}
