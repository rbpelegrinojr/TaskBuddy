<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Http;

use TaskBuddy\Support\Env;

/** Thin wrapper around PHP sessions; `memory()` keeps state in an array (used by tests / CLI). */
final class Session
{
    /** @var array<string,mixed> */
    private array $mem = [];

    private function __construct(private readonly bool $native)
    {
    }

    public static function memory(): self
    {
        return new self(false);
    }

    public static function native(bool $secure): self
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $cookieSecure = match (strtolower((string)Env::get('ADMIN_COOKIE_SECURE', 'auto'))) {
                '1', 'true', 'yes' => true,
                '0', 'false', 'no' => false,
                default => $secure,
            };
            session_name(Env::get('ADMIN_SESSION_NAME', 'taskbuddy_admin') ?: 'taskbuddy_admin');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $cookieSecure, 'httponly' => true, 'samesite' => 'Strict']);
            session_start();
        }
        return new self(true);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->native ? ($_SESSION[$key] ?? $default) : ($this->mem[$key] ?? $default);
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->native) {
            $_SESSION[$key] = $value;
        } else {
            $this->mem[$key] = $value;
        }
    }

    public function forget(string $key): void
    {
        if ($this->native) {
            unset($_SESSION[$key]);
        } else {
            unset($this->mem[$key]);
        }
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $v = $this->get($key, $default);
        $this->forget($key);
        return $v;
    }

    /** New session id after a privilege change (prevents session fixation). */
    public function regenerate(): void
    {
        if ($this->native) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        if ($this->native) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
        } else {
            $this->mem = [];
        }
    }
}
