<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Context;
use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\View;

abstract class Controller
{
    public function __construct(protected Context $ctx)
    {
    }

    /** @param array<string,mixed> $data */
    protected function view(string $template, string $title, array $data = [], int $status = 200): Response
    {
        return new Response($status, View::render($this->ctx, $template, $data, $title), ['Content-Type' => 'text/html; charset=utf-8']);
    }

    protected function redirect(string $path, ?string $flash = null, string $type = 'success', array $query = []): Response
    {
        if ($flash !== null) {
            $this->ctx->flash($flash, $type);
        }
        return Response::redirect($this->ctx->url($path, $query));
    }

    /** App-relative same-site path to return to after a POST (never an external URL). */
    protected function returnPath(string $default): string
    {
        $back = $this->ctx->request->postStr('_return');
        if ($back !== '' && $back[0] === '/' && !str_starts_with($back, '//') && !str_contains($back, '\\') && !preg_match('/[\r\n]/', $back)) {
            return $back;
        }
        return $default;
    }

    protected function redirectBack(string $default, ?string $flash = null, string $type = 'success'): Response
    {
        if ($flash !== null) {
            $this->ctx->flash($flash, $type);
        }
        return Response::redirect($this->ctx->base . $this->returnPath($default));
    }

    /** @param array<string,mixed> $query */
    protected function path(string $path, array $query = []): string
    {
        return $path . ($query ? '?' . http_build_query($query) : '');
    }

    protected function adminId(): int
    {
        return (int)$this->ctx->admin['id'];
    }

    protected function csv(string $filename, string $content): Response
    {
        return new Response(200, $content, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"',
        ]);
    }

    /** Escapes LIKE wildcards in user-supplied search text. */
    protected function like(string $s): string
    {
        return '%' . addcslashes($s, '\\%_') . '%';
    }

    /** @param array<string,mixed> $query current filters (without "page") */
    protected function pagerHtml(\TaskBuddy\Admin\Support\Pager $p, string $path, array $query): string
    {
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<nav class="pager" aria-label="Pagination"><span class="muted">' . $e($p->total) . ' result(s) · page ' . $e($p->page) . ' of ' . $e($p->pages) . '</span>';
        if ($p->page > 1) {
            $html .= '<a class="btn small secondary" href="' . $e($this->ctx->url($path, $query + ['page' => $p->page - 1])) . '">Previous</a>';
        }
        if ($p->page < $p->pages) {
            $html .= '<a class="btn small secondary" href="' . $e($this->ctx->url($path, $query + ['page' => $p->page + 1])) . '">Next</a>';
        }
        return $html . '</nav>';
    }
}
