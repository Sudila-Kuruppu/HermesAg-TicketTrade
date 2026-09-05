<?php

/**
 * TicketTrade — Support\Network IP resolution
 *
 * Resolves the best-effort client IP for rate-limit keying and audit
 * logging. Default behavior honors REMOTE_ADDR only (safe — never trust
 * client-supplied forwarding headers unconditionally). If the request
 * came from a trusted proxy IP, the first hop in X-Forwarded-For /
 * X-Real-IP is preferred.
 *
 * Trusted-proxy list is empty by default (CLI / direct). Operators add
 * their reverse-proxy CIDRs to TRUSTED_PROXIES via env var
 * TT_TRUSTED_PROXIES (comma-separated CIDR list). When the connection
 * is not from a trusted IP, X-Forwarded-For is ignored and REMOTE_ADDR
 * is returned (no log spam — the caller already gets a valid IP).
 */

declare(strict_types=1);

namespace App\Support;

class Network
{
    /**
     * Return the best-effort client IP. Safe to call when no HTTP
     * request is active (CLI) — returns '127.0.0.1'.
     */
    public static function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if ($remote === '' || $remote === '0.0.0.0') {
            $remote = '127.0.0.1';
        }
        if (!self::isTrustedProxy($remote)) {
            return $remote;
        }
        // Trusted proxy — pick the leftmost X-Forwarded-For (the
        // originating client). The proxy is responsible for the chain.
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $parts = explode(',', $xff);
            $first = trim($parts[0]);
            if (self::isValidIp($first)) {
                return $first;
            }
        }
        $xri = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? '');
        if ($xri !== '' && self::isValidIp($xri)) {
            return $xri;
        }
        return $remote;
    }

    private static function isTrustedProxy(string $ip): bool
    {
        $cidrs = self::trustedProxies();
        if ($cidrs === []) {
            return false;
        }
        foreach ($cidrs as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    private static function trustedProxies(): array
    {
        $env = getenv('TT_TRUSTED_PROXIES');
        if (!is_string($env) || $env === '') {
            return [];
        }
        $out = [];
        foreach (explode(',', $env) as $cidr) {
            $cidr = trim($cidr);
            if ($cidr !== '') {
                $out[] = $cidr;
            }
        }
        return $out;
    }

    private static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Minimal IPv4 CIDR check (no IPv6 support yet — campus IPv4 only).
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        if (!self::isValidIp($ip) || !self::isValidIp($subnet)) {
            return false;
        }
        $ipL = ip2long($ip);
        $snL = ip2long($subnet);
        if ($ipL === false || $snL === false) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }
        $mask = -1 << (32 - $bits);
        return ($ipL & $mask) === ($snL & $mask);
    }
}