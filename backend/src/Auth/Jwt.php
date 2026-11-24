<?php

declare(strict_types=1);

namespace Ancc\Auth;

/** Minimal HS256 JWT (enough for our own short-lived tokens). */
final class Jwt
{
    /** @param array<string, mixed> $payload */
    public static function encode(array $payload, string $secret): string
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body = self::b64(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
        return "$header.$body.$sig";
    }

    /**
     * Returns the payload if the signature is valid, it hasn't expired, and `type` matches.
     * @return array<string, mixed>|null
     */
    public static function decode(string $token, string $secret, string $expectedType): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $sig] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $h = json_decode(self::unb64($header), true);
        $payload = json_decode(self::unb64($body), true);
        if (!is_array($h) || ($h['alg'] ?? '') !== 'HS256' || !is_array($payload)) {
            return null;
        }
        if (!isset($payload['exp']) || !is_int($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }
        if (($payload['type'] ?? null) !== $expectedType) {
            return null;
        }
        return $payload;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
