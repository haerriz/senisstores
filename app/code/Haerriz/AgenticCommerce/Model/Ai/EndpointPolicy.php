<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

/** Restricts merchant-configured AI endpoints and blocks common SSRF/private-network targets by default. */
class EndpointPolicy
{
    /** @param string[] $allowedHosts */
    public function assertAllowed(
        string $url,
        bool $allowInsecureHttp = false,
        bool $allowPrivateNetwork = false,
        array $allowedHosts = []
    ): void {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('AI provider endpoint is not a valid absolute URL.');
        }
        $scheme = mb_strtolower((string)$parts['scheme']);
        if ($scheme !== 'https' && !($allowInsecureHttp && $scheme === 'http')) {
            throw new \InvalidArgumentException('AI provider endpoint must use HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('AI provider endpoint must not embed credentials in the URL.');
        }
        $host = mb_strtolower(rtrim(trim((string)$parts['host'], '[]'), '.'));
        if ($host === '') throw new \InvalidArgumentException('AI provider endpoint host is invalid.');

        $normalizedAllowlist = array_values(array_filter(array_map(
            static fn(string $value): string => mb_strtolower(rtrim(trim($value), '.')),
            $allowedHosts
        )));
        if ($normalizedAllowlist !== [] && !$this->hostAllowed($host, $normalizedAllowlist)) {
            throw new \InvalidArgumentException('AI provider endpoint host is not in the configured allowlist.');
        }

        if (!$allowPrivateNetwork && $this->isPrivateTarget($host)) {
            throw new \InvalidArgumentException('AI provider endpoint may not target localhost, metadata, or a private/reserved network.');
        }
    }

    /** @param string[] $allowlist */
    private function hostAllowed(string $host, array $allowlist): bool
    {
        foreach ($allowlist as $allowed) {
            if ($allowed === '') continue;
            if ($host === $allowed) return true;
            if (str_starts_with($allowed, '*.') && str_ends_with($host, substr($allowed, 1))) return true;
        }
        return false;
    }

    private function isPrivateTarget(string $host): bool
    {
        if (in_array($host, ['localhost', 'localhost.localdomain', 'metadata.google.internal'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) return !$this->isPublicIp($host);

        // Best-effort A/AAAA DNS check. It supplements, but does not replace, network egress controls.
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
                    if ($ip !== '' && !$this->isPublicIp($ip)) return true;
                }
            }
        } else {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                foreach ($resolved as $ip) if (!$this->isPublicIp((string)$ip)) return true;
            }
        }
        return false;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
