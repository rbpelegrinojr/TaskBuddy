<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Http;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $post */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly string $ip = '0.0.0.0',
        public readonly string $userAgent = '',
        public readonly bool $secure = false
    ) {
    }

    /** Builds a request from PHP globals; $base is the URL prefix the app is mounted under (e.g. "/admin"). */
    public static function fromGlobals(string $base = ''): self
    {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if ($path === '/index.php') {
            $path = '/';
        }
        $path = '/' . trim($path, '/');
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, $_POST, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $secure);
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public function queryStr(string $key, string $default = ''): string
    {
        $v = $this->query[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public function queryInt(string $key, int $default = 1, int $min = 1, int $max = 1000000): int
    {
        $v = $this->query[$key] ?? null;
        return is_numeric($v) ? max($min, min($max, (int)$v)) : $default;
    }

    public function postStr(string $key, string $default = ''): string
    {
        $v = $this->post[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }
}
