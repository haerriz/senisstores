<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

/**
 * DI-mergeable provider registry. Enterprise modules can register another provider without
 * changing Config, ProviderManager or Admin source models.
 */
class ProviderRegistry
{
    /** @param array<string,ProviderInterface> $providers @param array<string,string> $labels */
    public function __construct(private array $providers = [], private array $labels = []) {}

    public function get(string $code): ?ProviderInterface
    {
        $provider = $this->providers[$code] ?? null;
        return $provider instanceof ProviderInterface ? $provider : null;
    }

    /** @return string[] */
    public function getCodes(): array { return array_keys($this->providers); }

    public function has(string $code): bool { return $this->get($code) instanceof ProviderInterface; }

    public function getOptions(bool $includeDeterministic = true): array
    {
        $options = $includeDeterministic ? [['value'=>'deterministic','label'=>(string)__('RizAI — Hybrid Neural Commerce Brain (local, no API key)')]] : [];
        foreach ($this->providers as $code => $provider) {
            if (!$provider instanceof ProviderInterface) continue;
            $label = trim((string)($this->labels[$code] ?? '')) ?: ucwords(str_replace('_', ' ', (string)$code));
            $options[] = ['value'=>(string)$code,'label'=>$label];
        }
        return $options;
    }
}
