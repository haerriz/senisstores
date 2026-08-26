<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

/**
 * Strict parser for the model-neutral JSON tool-call contract used by RizAI generative training.
 *
 * Native OpenAI tool_calls remain preferred. This parser exists so a fine-tuned transformer can be
 * served by an inference stack that does not implement a model-specific tool parser. It accepts only
 * a complete JSON document and only caller-supplied tool names; arbitrary prose/code-fence extraction
 * is intentionally not supported.
 */
final class PortableToolCallParser
{
    /**
     * @param string[] $allowedNames
     * @return array<int,array{name:string,arguments:array<string,mixed>}>
     */
    public function parse(string $content, array $allowedNames, int $maxCalls): array
    {
        $content = trim($content);
        if ($content === '' || strlen($content) > 20000 || $maxCalls < 1) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 128, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }

        $candidates = [];
        if (isset($decoded['tools']) && is_array($decoded['tools'])) {
            $candidates = $decoded['tools'];
        } elseif (isset($decoded['tool']) || isset($decoded['name'])) {
            $candidates = [$decoded];
        } elseif (array_is_list($decoded)) {
            $candidates = $decoded;
        }

        $allowed = array_fill_keys(array_values(array_filter(array_map('strval', $allowedNames))), true);
        if ($allowed === []) {
            return [];
        }

        $out = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $name = trim((string)($candidate['name'] ?? $candidate['tool'] ?? ''));
            if ($name === '' || !isset($allowed[$name])) {
                continue;
            }
            $arguments = $candidate['arguments'] ?? [];
            if (is_string($arguments)) {
                try {
                    $arguments = json_decode($arguments, true, 64, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    continue;
                }
            }
            if (!is_array($arguments) || array_is_list($arguments)) {
                // Tool arguments are always a JSON object in the module contract.
                if ($arguments !== []) {
                    continue;
                }
            }
            $out[] = ['name' => $name, 'arguments' => $arguments];
            if (count($out) >= $maxCalls) {
                break;
            }
        }
        return $out;
    }
}
