<?php
declare(strict_types=1);

namespace TaskBuddy\Admin;

use TaskBuddy\Admin\Http\Request;
use TaskBuddy\Admin\Http\Session;

/** Per-request state shared by controllers and views. */
final class Context
{
    /** @var array<string,mixed>|null */
    public ?array $admin = null;

    public function __construct(public readonly Request $request, public readonly Session $session, public readonly string $base = '')
    {
    }

    public function url(string $path = '/', array $query = []): string
    {
        $q = array_filter($query, static fn($v) => $v !== '' && $v !== null);
        return $this->base . $path . ($q ? '?' . http_build_query($q) : '');
    }

    public function csrf(): string
    {
        $t = $this->session->get('csrf');
        if (!is_string($t) || strlen($t) < 32) {
            $t = bin2hex(random_bytes(32));
            $this->session->set('csrf', $t);
        }
        return $t;
    }

    public function csrfValid(): bool
    {
        $sent = $this->request->post['_csrf'] ?? '';
        $have = $this->session->get('csrf');
        return is_string($sent) && is_string($have) && $have !== '' && hash_equals($have, $sent);
    }

    public function flash(string $message, string $type = 'success'): void
    {
        $f = $this->session->get('flash', []);
        $f[] = ['type' => $type, 'message' => $message];
        $this->session->set('flash', $f);
    }

    /** @return array<int,array{type:string,message:string}> */
    public function pullFlash(): array
    {
        return (array)$this->session->pull('flash', []);
    }
}
