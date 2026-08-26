<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Api;

interface SuggestionProviderInterface
{
    /** @return string[] */
    public function getSuggestions(array $response): array;
}
