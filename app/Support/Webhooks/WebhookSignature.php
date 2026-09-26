<?php

namespace App\Support\Webhooks;

/**
 * Signs each delivery so the receiver can check it came from us and wasn't replayed:
 *
 *     PropertyFlow-Signature: t=1760000000,v1=<hex HMAC-SHA256 of "{t}.{raw body}" with the secret>
 *
 * Receivers recompute v1 from the raw body and their endpoint secret, compare in constant time,
 * and reject timestamps more than a few minutes old.
 */
final class WebhookSignature
{
    public const string HEADER = 'PropertyFlow-Signature';

    public static function header(string $body, string $secret, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.self::compute($body, $secret, $timestamp);
    }

    /**
     * Checks a signature header the way a receiver should.
     */
    public static function verify(string $header, string $body, string $secret, int $now, int $toleranceSeconds = 300): bool
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        if (! isset($parts['t'], $parts['v1']) || ! ctype_digit($parts['t'])) {
            return false;
        }

        $timestamp = (int) $parts['t'];

        return abs($now - $timestamp) <= $toleranceSeconds
            && hash_equals(self::compute($body, $secret, $timestamp), $parts['v1']);
    }

    private static function compute(string $body, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
