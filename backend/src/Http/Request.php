<?php

declare(strict_types=1);

namespace Ancc\Http;

final class Request
{
    /** @var array<string, string> */
    public array $params = []; // route parameters, e.g. {id}

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers lower-case names
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly string $body = '',
        public readonly string $ip = '',
    ) {
    }

    /** Builds the request from PHP's globals. $basePath (e.g. "/new") is stripped from the path. */
    public static function fromGlobals(string $basePath = ''): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath));
        }
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        /** @var array<string, string> $query */
        $query = array_map('strval', array_filter($_GET, 'is_scalar'));
        /** @var array<string, string> $cookies */
        $cookies = array_map('strval', array_filter($_COOKIE, 'is_scalar'));
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $query,
            $headers,
            $cookies,
            (string) file_get_contents('php://input'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        );
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if (trim($this->body) === '') {
            return [];
        }
        $data = json_decode($this->body, true);
        if (!is_array($data) || array_is_list($data) && $data !== []) {
            throw new HttpError(422, [['loc' => ['body'], 'msg' => 'Body must be a JSON object']]);
        }
        return $data;
    }

    public function cookie(string $name): ?string
    {
        $v = $this->cookies[$name] ?? null;
        return $v === '' ? null : $v;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
