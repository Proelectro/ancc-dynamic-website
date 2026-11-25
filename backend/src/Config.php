<?php

declare(strict_types=1);

namespace Ancc;

use RuntimeException;

/**
 * App settings. Loaded from a PHP file that returns an array (see config.example.php).
 * Environment variables named ANCC_<KEY> override file values, which is handy for tests.
 */
final class Config
{
    public const DEFAULT_SECRET = 'change-me';

    /** @var array<string, mixed> */
    private const DEFAULTS = [
        // development = your machine, staging = the server before IITD login is live,
        // production = the real site (IITD login only).
        'ENV' => 'development',
        'AUTH_MODE' => 'dev', // dev | iitd

        'DB_HOST' => 'localhost',
        'DB_PORT' => 3306,
        'DB_SOCKET' => '',
        'DB_NAME' => 'ancc',
        'DB_USER' => 'ancc',
        'DB_PASSWORD' => 'ancc',

        // URL prefix when the app isn't at the domain root, e.g. "/new" for ancc.iitd.ac.in/new/
        'BASE_PATH' => '',
        'FRONTEND_URL' => 'http://localhost:5173',

        'JWT_SECRET' => self::DEFAULT_SECRET,
        'ACCESS_TOKEN_MINUTES' => 15,
        'REFRESH_TOKEN_DAYS' => 7,
        'COOKIE_SECURE' => false,

        // IITD OAuth: confirm these endpoints and the response fields with CSC.
        'IITD_OAUTH_CLIENT_ID' => '',
        'IITD_OAUTH_CLIENT_SECRET' => '',
        'IITD_OAUTH_AUTHORIZE_URL' => 'https://oauth.iitd.ac.in/authorize.php',
        'IITD_OAUTH_TOKEN_URL' => 'https://oauth.iitd.ac.in/token.php',
        'IITD_OAUTH_RESOURCE_URL' => 'https://oauth.iitd.ac.in/resource.php',
        'IITD_OAUTH_REDIRECT_URI' => 'http://localhost:8000/api/v1/auth/callback',
    ];

    /** @param array<string, mixed> $values */
    private function __construct(private array $values)
    {
    }

    /** @param array<string, mixed> $overrides */
    public static function load(?string $file = null, array $overrides = []): self
    {
        $values = self::DEFAULTS;
        $file ??= getenv('ANCC_CONFIG') ?: (defined('ANCC_CONFIG') ? ANCC_CONFIG : dirname(__DIR__) . '/config.php');
        if (is_file($file)) {
            $fromFile = require $file;
            if (!is_array($fromFile)) {
                throw new RuntimeException("$file must return an array");
            }
            $values = array_merge($values, $fromFile);
        }
        foreach (array_keys(self::DEFAULTS) as $key) {
            $env = getenv('ANCC_' . $key);
            if ($env !== false) {
                $values[$key] = $env;
            }
        }
        $values = array_merge($values, $overrides);

        // Normalise types (env vars are strings).
        foreach (['COOKIE_SECURE'] as $k) {
            $values[$k] = filter_var($values[$k], FILTER_VALIDATE_BOOLEAN);
        }
        foreach (['DB_PORT', 'ACCESS_TOKEN_MINUTES', 'REFRESH_TOKEN_DAYS'] as $k) {
            $values[$k] = (int) $values[$k];
        }
        $values['BASE_PATH'] = rtrim((string) $values['BASE_PATH'], '/');

        $config = new self($values);
        $config->validate();
        return $config;
    }

    private function validate(): void
    {
        $env = $this->str('ENV');
        if (!in_array($env, ['development', 'staging', 'production', 'test'], true)) {
            throw new RuntimeException("Unknown ENV '$env'");
        }
        if (!in_array($this->str('AUTH_MODE'), ['dev', 'iitd'], true)) {
            throw new RuntimeException('AUTH_MODE must be dev or iitd');
        }
        if ($env === 'production' && $this->str('AUTH_MODE') === 'dev') {
            throw new RuntimeException('Refusing to start: AUTH_MODE=dev is not allowed when ENV=production');
        }
        if (in_array($env, ['staging', 'production'], true) && $this->str('JWT_SECRET') === self::DEFAULT_SECRET) {
            throw new RuntimeException("Refusing to start: set JWT_SECRET for ENV=$env");
        }
        $base = $this->str('BASE_PATH');
        if ($base !== '' && $base[0] !== '/') {
            throw new RuntimeException('BASE_PATH must start with / (e.g. /new)');
        }
    }

    public function str(string $key): string
    {
        return (string) ($this->values[$key] ?? '');
    }

    public function int(string $key): int
    {
        return (int) ($this->values[$key] ?? 0);
    }

    public function bool(string $key): bool
    {
        return (bool) ($this->values[$key] ?? false);
    }

    public function isDevLogin(): bool
    {
        return $this->str('AUTH_MODE') === 'dev';
    }
}
