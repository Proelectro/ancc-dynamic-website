<?php

declare(strict_types=1);

namespace Ancc\Tests;

use Ancc\App;
use Ancc\Http\Request;
use Ancc\Http\Response;

/** Calls the app in-process and keeps cookies between requests, like a browser. */
final class TestClient
{
    /** @var array<string, string> */
    public array $cookies = [];

    public function __construct(private App $app)
    {
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $query
     */
    public function request(string $method, string $path, ?array $json = null, array $query = []): Response
    {
        $headers = ['user-agent' => 'phpunit'];
        if ($json !== null) {
            $headers['content-type'] = 'application/json';
        }
        $response = $this->app->handle(new Request(
            $method,
            $path,
            $query,
            $headers,
            $this->cookies,
            $json === null ? '' : (string) json_encode($json),
            '10.0.0.5',
        ));
        foreach ($response->cookies as $c) {
            if ($c['value'] === '' || ($c['options']['expires'] ?? PHP_INT_MAX) < time()) {
                unset($this->cookies[$c['name']]);
            } else {
                $this->cookies[$c['name']] = $c['value'];
            }
        }
        return $response;
    }

    /**
     * @param list<string> $roles
     * @return array<string, mixed>
     */
    public function login(string $kerberosId, array $roles = [], ?string $name = null): array
    {
        $body = ['kerberos_id' => $kerberosId, 'roles' => $roles];
        if ($name !== null) {
            $body['name'] = $name;
        }
        $r = $this->request('POST', '/api/v1/auth/dev-login', $body);
        if ($r->status !== 200) {
            throw new \RuntimeException("dev-login failed: {$r->status} {$r->body}");
        }
        return $r->data();
    }
}
