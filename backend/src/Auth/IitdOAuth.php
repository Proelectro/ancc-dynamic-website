<?php

declare(strict_types=1);

namespace Ancc\Auth;

use Ancc\Config;
use RuntimeException;

/**
 * IITD OAuth 2.0 (authorization-code flow).
 * The endpoint URLs and the field names in the profile response must be confirmed with CSC when
 * the club registers its OAuth client. Everything is configurable in config.php.
 */
final class IitdOAuth
{
    public function __construct(private Config $config)
    {
    }

    public function authorizeUrl(string $state): string
    {
        return $this->config->str('IITD_OAUTH_AUTHORIZE_URL') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config->str('IITD_OAUTH_CLIENT_ID'),
            'redirect_uri' => $this->config->str('IITD_OAUTH_REDIRECT_URI'),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): string
    {
        $data = $this->post($this->config->str('IITD_OAUTH_TOKEN_URL'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config->str('IITD_OAUTH_REDIRECT_URI'),
            'client_id' => $this->config->str('IITD_OAUTH_CLIENT_ID'),
            'client_secret' => $this->config->str('IITD_OAUTH_CLIENT_SECRET'),
        ]);
        $token = $data['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('No access_token in IITD response');
        }
        return $token;
    }

    public function fetchIdentity(string $accessToken): Identity
    {
        $data = $this->post($this->config->str('IITD_OAUTH_RESOURCE_URL'), ['access_token' => $accessToken]);
        // Field names to confirm with CSC. We accept a few likely spellings.
        $kid = $data['user_id'] ?? $data['uniqueiitdid'] ?? $data['kerberos'] ?? $data['username'] ?? null;
        if (!is_string($kid) || $kid === '') {
            throw new RuntimeException('IITD response has no kerberos ID');
        }
        return new Identity(
            $kid,
            is_string($data['name'] ?? null) ? $data['name'] : $kid,
            is_string($data['email'] ?? null) ? $data['email'] : "$kid@iitd.ac.in",
            is_string($data['department'] ?? null) ? $data['department'] : null,
        );
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
