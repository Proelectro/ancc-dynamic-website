<?php

declare(strict_types=1);

namespace Ancc\Auth;

use Ancc\Config;
use RuntimeException;

/**
 * IITD OAuth 2.0 (authorization-code flow), as documented at https://oauth2.iitd.ac.in/client_config.php:
 * authorize.php → our callback gets ?code → token.php (client_id, client_secret, grant_type, code)
 * → resource.php (access_token) returns user_id, mail, name, uniqueiitdid, category, department.
 */
final class IitdOAuth
{
    public function __construct(private Config $config)
    {
    }

    public function authorizeUrl(string $state): string
    {
        return $this->config->str('IITD_OAUTH_AUTHORIZE_URL') . '?' . http_build_query($this->withRedirect([
            'response_type' => 'code',
            'client_id' => $this->config->str('IITD_OAUTH_CLIENT_ID'),
            'state' => $state,
        ]));
    }

    public function exchangeCode(string $code): string
    {
        $data = $this->post($this->config->str('IITD_OAUTH_TOKEN_URL'), $this->withRedirect([
            'client_id' => $this->config->str('IITD_OAUTH_CLIENT_ID'),
            'client_secret' => $this->config->str('IITD_OAUTH_CLIENT_SECRET'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]));
        $token = $data['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('No access_token in IITD response');
        }
        return $token;
    }

    public function fetchIdentity(string $accessToken): Identity
    {
        $data = $this->post($this->config->str('IITD_OAUTH_RESOURCE_URL'), ['access_token' => $accessToken]);
        // user_id is the kerberos ID (IITD's own example uses it as the login id).
        $kid = $data['user_id'] ?? null;
        if (!is_string($kid) || trim($kid) === '') {
            throw new RuntimeException('IITD response has no user_id');
        }
        $kid = strtolower(trim($kid));
        $str = static fn (string $k): ?string => is_string($data[$k] ?? null) && trim($data[$k]) !== '' ? trim($data[$k]) : null;
        return new Identity(
            $kid,
            $str('name') ?? $kid,
            $str('mail') ?? "$kid@iitd.ac.in",
            $str('department'),
        );
    }

    /**
     * Adds redirect_uri only if one is configured (IITD normally uses the registered one).
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private function withRedirect(array $fields): array
    {
        $uri = $this->config->str('IITD_OAUTH_REDIRECT_URI');
        return $uri === '' ? $fields : $fields + ['redirect_uri' => $uri];
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function post(string $url, array $fields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (!is_string($body) || $status !== 200) {
            throw new RuntimeException("IITD OAuth request failed ($status)");
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('IITD OAuth returned invalid JSON');
        }
        return $data;
    }
}
