<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

/** Minimal .env loader plus typed accessors. Real environment variables take precedence. */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $v = trim($v);
                if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
                    $q = $v[0];
                    $end = strrpos($v, $q);
                    $v = $end > 0 ? substr($v, 1, $end - 1) : substr($v, 1);
                } else {
                    $v = trim(preg_replace('/\s+#.*$/', '', $v) ?? $v);
                }
                self::$values[trim($k)] = $v;
            }
        }
        self::$loaded = true;
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false) {
            return $real;
        }
        return self::$values[$key] ?? $default;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return $v !== null && is_numeric($v) ? (int)$v : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }
}
