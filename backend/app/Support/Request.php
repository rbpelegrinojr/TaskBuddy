<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $body @param array<string,string> $headers @param array<string,mixed> $files */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $files = [],
        public readonly string $ip = '0.0.0.0'
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string)$v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string)$_SERVER['CONTENT_TYPE'];
        }
        if (!isset($headers['authorization']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        $body = [];
        $ct = $headers['content-type'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new ApiException(400, 'INVALID_JSON', 'Request body is not valid JSON.');
                }
                $body = $decoded;
            }
        } else {
            $body = $_POST;
        }
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($path, '/') ?: '/', $_GET, $body, $headers, $_FILES, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $h = $this->header('authorization');
        if ($h !== null && preg_match('/^Bearer\s+([A-Za-z0-9]{20,200})$/', $h, $m)) {
            return $m[1];
        }
        return null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function queryInt(string $key, int $default, int $min = 1, int $max = 100): int
    {
        $v = $this->query[$key] ?? null;
        if ($v === null || !is_numeric($v)) {
            return $default;
        }
        return max($min, min($max, (int)$v));
    }
}
