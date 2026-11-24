<?php

declare(strict_types=1);

namespace Ancc\Http;

final class Response
{
    /** @var array<string, string> */
    public array $headers = [];

    /** @var list<array{name: string, value: string, options: array<string, mixed>}> */
    public array $cookies = [];

    public function __construct(public int $status = 200, public string $body = '')
    {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $r = new self($status, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $r->headers['Content-Type'] = 'application/json';
        return $r;
    }

    public static function redirect(string $url): self
    {
        $r = new self(302);
        $r->headers['Location'] = $url;
        return $r;
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    /** @param array<string, mixed> $options same keys as setcookie(): expires, path, secure, httponly, samesite */
    public function cookie(string $name, string $value, array $options): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        return $this;
    }

    /** @return mixed decoded JSON body (used by tests) */
    public function data(): mixed
    {
        return json_decode($this->body, true);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        foreach ($this->cookies as $c) {
            setcookie($c['name'], $c['value'], $c['options']);
        }
        if ($this->status !== 204) {
            echo $this->body;
        }
    }
}
