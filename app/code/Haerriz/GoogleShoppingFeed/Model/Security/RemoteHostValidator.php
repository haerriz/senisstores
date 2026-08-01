<?php
namespace Haerriz\GoogleShoppingFeed\Model\Security;

/**
 * Validates remote hosts to prevent SSRF against private/internal networks.
 */
class RemoteHostValidator
{
    private const PRIVATE_RANGES = [
        ['10.0.0.0', '10.255.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['127.0.0.0', '127.255.255.255'],
        ['169.254.0.0', '169.254.255.255'],
        ['0.0.0.0', '0.255.255.255'],
        ['224.0.0.0', '239.255.255.255'],
        ['240.0.0.0', '255.255.255.255'],
    ];

    /**
     * @return bool
     */
    public function isValid(string $host): bool
    {
        return $this->validate($host) === null;
    }

    /**
     * Validate a host. Returns null on success, or an error string on failure.
     *
     * @param string $host
     * @return string|null
     */
    public function validate(string $host): ?string
    {
        $host = trim($host);
        if ($host === '') {
            return 'Host is empty.';
        }

        $normalized = $this->normalizeHost($host);
        if ($normalized === '') {
            return 'Host is invalid.';
        }

        $ip = $this->resolveHost($normalized);
        if ($ip === null) {
            return 'Unable to resolve host, or host resolves to a private/reserved address.';
        }

        if ($this->isPrivateIp($ip) || $this->isPrivateIpv6($ip)) {
            return 'Private, loopback, link-local, or reserved IP addresses are not allowed.';
        }

        return null;
    }

    private function normalizeHost(string $host): string
    {
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = explode('/', $host, 2)[0];
        $host = explode(':', $host, 2)[0];
        return trim($host);
    }

    private function resolveHost(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $ips = @gethostbynamel($host);
        if (!$ips) {
            return null;
        }

        foreach ($ips as $resolvedIp) {
            if ($this->isPrivateIp($resolvedIp) || $this->isPrivateIpv6($resolvedIp)) {
                return null;
            }
        }

        return $ips[0];
    }

    private function isPrivateIp(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false;
        }

        foreach (self::PRIVATE_RANGES as [$start, $end]) {
            $startLong = ip2long($start);
            $endLong = ip2long($end);
            if ($startLong !== false && $endLong !== false && $long >= $startLong && $long <= $endLong) {
                return true;
            }
        }

        return false;
    }

    private function isPrivateIpv6(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        $ip = strtolower($ip);
        if ($ip === '::1' || $ip === '::') {
            return true;
        }
        if (strpos($ip, 'fe80:') === 0 || strpos($ip, 'fc') === 0 || strpos($ip, 'fd') === 0) {
            return true;
        }

        return false;
    }
}
