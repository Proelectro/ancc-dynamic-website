<?php

declare(strict_types=1);

namespace Ancc\Auth;

use Ancc\Config;
use Ancc\Db;
use Ancc\Http\Request;
use Ancc\Http\Response;
use Ancc\Support\Uuid;
use Ancc\Users;

/**
 * Login sessions: a short-lived signed access token (JWT) and a long-lived opaque refresh token
 * stored hashed in the database. Both travel in HttpOnly cookies scoped to BASE_PATH.
 */
final class Session
{
    public const ACCESS_COOKIE = 'ancc_access';
    public const REFRESH_COOKIE = 'ancc_refresh';
    public const STATE_COOKIE = 'ancc_oauth_state';

    public function __construct(private Config $config, private Db $db, private Users $users)
    {
    }

    /** The logged-in, active user for this request, or null. */
    public function currentUser(Request $request): ?array
    {
        $token = $request->cookie(self::ACCESS_COOKIE);
        if ($token === null) {
            return null;
        }
        $payload = Jwt::decode($token, $this->config->str('JWT_SECRET'), 'access');
        if ($payload === null || !is_string($payload['sub'] ?? null)) {
            return null;
        }
        $user = $this->users->find($payload['sub']);
        return $user !== null && $user['is_active'] ? $user : null;
    }

    /** Issues a new access + refresh token pair as cookies on $response. */
    public function start(array $user, Request $request, Response $response): void
    {
        $raw = bin2hex(random_bytes(32));
        $this->db->run(
            'INSERT INTO refresh_tokens (id, user_id, token_hash, expires_at, user_agent, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                Uuid::v4(), $user['id'], hash('sha256', $raw),
                Db::now('+' . $this->config->int('REFRESH_TOKEN_DAYS') . ' days'),
                substr((string) $request->header('user-agent'), 0, 300), $request->ip, Db::now(),
            ]
        );
        $access = Jwt::encode([
            'sub' => $user['id'],
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + 60 * $this->config->int('ACCESS_TOKEN_MINUTES'),
        ], $this->config->str('JWT_SECRET'));

        [$accessPath, $refreshPath] = $this->paths();
        $response->cookie(self::ACCESS_COOKIE, $access, $this->cookieOptions($accessPath, 60 * $this->config->int('ACCESS_TOKEN_MINUTES')));
        $response->cookie(self::REFRESH_COOKIE, $raw, $this->cookieOptions($refreshPath, 86400 * $this->config->int('REFRESH_TOKEN_DAYS')));
    }

    /** Revokes the presented refresh token and issues a new pair. Returns the user, or null if invalid. */
    public function rotate(string $raw, Request $request, Response $response): ?array
    {
        $row = $this->db->one('SELECT * FROM refresh_tokens WHERE token_hash = ?', [hash('sha256', $raw)]);
        if ($row === null || $row['revoked_at'] !== null || $row['expires_at'] <= Db::now()) {
            return null;
        }
        $user = $this->users->find($row['user_id']);
        if ($user === null || !$user['is_active']) {
            return null;
        }
        $this->db->run('UPDATE refresh_tokens SET revoked_at = ? WHERE id = ?', [Db::now(), $row['id']]);
        $this->start($user, $request, $response);
        return $user;
    }

    public function revoke(?string $raw): void
    {
        if ($raw === null) {
            return;
        }
        $this->db->run(
            'UPDATE refresh_tokens SET revoked_at = ? WHERE token_hash = ? AND revoked_at IS NULL',
            [Db::now(), hash('sha256', $raw)]
        );
    }

    public function clear(Response $response): void
    {
        [$accessPath, $refreshPath] = $this->paths();
        $response->cookie(self::ACCESS_COOKIE, '', $this->cookieOptions($accessPath, -3600));
        $response->cookie(self::REFRESH_COOKIE, '', $this->cookieOptions($refreshPath, -3600));
    }

    /** @return array{0: string, 1: string} access cookie path, refresh cookie path */
    private function paths(): array
    {
        $base = $this->config->str('BASE_PATH');
        return [$base === '' ? '/' : $base, $base . '/api/v1/auth'];
    }

    /** @return array<string, mixed> */
    public function cookieOptions(string $path, int $maxAge): array
    {
        return [
            'expires' => time() + $maxAge,
            'path' => $path,
            'secure' => $this->config->bool('COOKIE_SECURE'),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /** OAuth `state`: a random value plus a signed, 10-minute cookie holding it. @return array{0: string, 1: string} */
    public function newState(): array
    {
        $state = bin2hex(random_bytes(16));
        $signed = Jwt::encode(['state' => $state, 'type' => 'state', 'exp' => time() + 600], $this->config->str('JWT_SECRET'));
        return [$state, $signed];
    }

    public function stateMatches(?string $signedCookie, ?string $state): bool
    {
        if ($signedCookie === null || $state === null || $state === '') {
            return false;
        }
        $payload = Jwt::decode($signedCookie, $this->config->str('JWT_SECRET'), 'state');
        return $payload !== null && hash_equals((string) ($payload['state'] ?? ''), $state);
    }
}
