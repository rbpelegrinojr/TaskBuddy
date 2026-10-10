<?php
declare(strict_types=1);

namespace TaskBuddy\Admin;

use TaskBuddy\Admin\Controllers as C;
use TaskBuddy\Admin\Http\Request;
use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Http\Session;
use TaskBuddy\Services\AuditLogger;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Env;
use TaskBuddy\Support\Logger;
use TaskBuddy\Support\Router;

/**
 * Administrator dashboard front controller.
 * - PHP session authentication (cookie: HttpOnly, SameSite=Strict, Secure on HTTPS), idle + absolute timeouts
 * - administrator role is re-checked in the database on every request
 * - every POST requires a valid CSRF token; state changes never happen on GET
 */
final class AdminApp
{
    public static function handle(Request $request, Session $session, string $base = ''): Response
    {
        $ctx = new Context($request, $session, $base);
        AuditLogger::$ip = $request->ip;
        try {
            $router = new Router();
            self::routes($router);
            $m = $router->match($request->method, $request->path);
            if ($m === null) {
                return self::finish(self::errorPage($ctx, 404, 'Page not found'));
            }
            [$class, $method] = $m['route']['handler'];
            $public = !$m['route']['auth'];

            if (!$public) {
                $redirect = self::authenticate($ctx);
                if ($redirect !== null) {
                    return self::finish($redirect);
                }
            }
            if ($request->method === 'POST' && !$ctx->csrfValid()) {
                return self::finish(self::errorPage($ctx, 403, 'Your session token is missing or has expired. Reload the page and try again.'));
            }
            return self::finish((new $class($ctx))->$method($m['params']));
        } catch (ApiException $e) {
            if ($e->status === 422) {
                $ctx->flash(implode(' ', array_map(static fn($k, $v) => $k . ': ' . $v, array_keys($e->details['fields'] ?? []), $e->details['fields'] ?? [])) ?: $e->getMessage(), 'error');
                return self::finish(Response::redirect(self::back($request, $ctx)));
            }
            if ($e->status === 409 || $e->status === 404 || $e->status === 429) {
                $ctx->flash($e->getMessage(), 'error');
                return self::finish(Response::redirect(self::back($request, $ctx)));
            }
            return self::finish(self::errorPage($ctx, $e->status, $e->getMessage()));
        } catch (\Throwable $e) {
            Logger::error('Admin unhandled exception', ['type' => $e::class, 'message' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
            return self::finish(self::errorPage($ctx, 500, 'An unexpected error occurred.'));
        }
    }

    /** Redirect back to the page that submitted the form (same-site path only; never an external URL). */
    private static function back(Request $request, Context $ctx): string
    {
        $path = $request->postStr('_return');
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\r\n]/', $path)) {
            $path = $request->method === 'POST' ? $request->path : '/';
        }
        return $ctx->base . $path;
    }

    /** @return Response|null redirect when the visitor is not an authenticated administrator */
    private static function authenticate(Context $ctx): ?Response
    {
        $s = $ctx->session;
        $id = $s->get('admin_id');
        $loginUrl = $ctx->url('/login');
        if (!is_int($id)) {
            return Response::redirect($loginUrl);
        }
        $now = Clock::now()->getTimestamp();
        $idle = Env::int('ADMIN_SESSION_IDLE_MINUTES', 30) * 60;
        $max = Env::int('ADMIN_SESSION_MAX_HOURS', 8) * 3600;
        if ($now - (int)$s->get('last_seen', 0) > $idle || $now - (int)$s->get('login_at', 0) > $max) {
            $s->destroy();
            return Response::redirect($loginUrl);
        }
        $u = Database::one("SELECT id, email, full_name, role, status FROM users WHERE id = ?", [$id]);
        if ($u === null || $u['role'] !== 'admin' || $u['status'] !== 'active') {
            $s->destroy();
            return Response::redirect($loginUrl);
        }
        $s->set('last_seen', $now);
        $ctx->admin = $u;
        return null;
    }

    private static function errorPage(Context $ctx, int $status, string $message): Response
    {
        $ctx->admin ??= null;
        return new Response($status, View::render($ctx, 'error', ['status' => $status, 'message' => $message, '_bare' => $ctx->admin === null], 'Error'), ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private static function finish(Response $r): Response
    {
        $r->headers += [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'none'; style-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            'Permissions-Policy' => 'geolocation=(), camera=(), microphone=()',
        ];
        return $r;
    }

    public static function routes(Router $r): void
    {
        $r->add('GET', '/login', [C\AuthController::class, 'showLogin'], false);
        $r->add('POST', '/login', [C\AuthController::class, 'login'], false);
        $r->add('GET', '/assets/admin.css', [C\AssetController::class, 'css'], false);
        $r->add('POST', '/logout', [C\AuthController::class, 'logout']);

        $r->add('GET', '/', [C\DashboardController::class, 'index']);
        $r->add('GET', '/users', [C\UsersController::class, 'index']);
        $r->add('POST', '/users/{id}/status', [C\UsersController::class, 'setStatus']);
        $r->add('GET', '/providers', [C\UsersController::class, 'providers']);
        $r->add('GET', '/catalog', [C\CatalogController::class, 'index']);
        $r->add('POST', '/catalog/categories', [C\CatalogController::class, 'createCategory']);
        $r->add('POST', '/catalog/categories/{id}', [C\CatalogController::class, 'updateCategory']);
        $r->add('POST', '/catalog/skills', [C\CatalogController::class, 'createSkill']);
        $r->add('POST', '/catalog/skills/{id}', [C\CatalogController::class, 'updateSkill']);
        $r->add('POST', '/catalog/links', [C\CatalogController::class, 'link']);
        $r->add('GET', '/requests', [C\RequestsController::class, 'index']);
        $r->add('GET', '/requests/{id}', [C\RequestsController::class, 'show']);
        $r->add('GET', '/matching', [C\MatchingController::class, 'index']);
        $r->add('GET', '/matching/{id}', [C\MatchingController::class, 'show']);
        $r->add('GET', '/transactions', [C\RequestsController::class, 'transactions']);
        $r->add('GET', '/payments', [C\PaymentsController::class, 'index']);
        $r->add('GET', '/disputes', [C\DisputesController::class, 'index']);
        $r->add('GET', '/disputes/{id}', [C\DisputesController::class, 'show']);
        $r->add('POST', '/disputes/{id}/resolve', [C\DisputesController::class, 'resolve']);
        $r->add('GET', '/moderation', [C\DisputesController::class, 'reports']);
        $r->add('POST', '/moderation/{id}', [C\DisputesController::class, 'reviewReport']);
        $r->add('GET', '/announcements', [C\AnnouncementsController::class, 'index']);
        $r->add('POST', '/announcements', [C\AnnouncementsController::class, 'send']);
        $r->add('GET', '/logs', [C\LogsController::class, 'index']);
        $r->add('GET', '/reports', [C\ReportsController::class, 'index']);
        $r->add('GET', '/reports/export', [C\ReportsController::class, 'export']);
        $r->add('GET', '/matching-config', [C\MatchingConfigController::class, 'index']);
        $r->add('POST', '/matching-config', [C\MatchingConfigController::class, 'update']);
        $r->add('GET', '/account', [C\AccountController::class, 'index']);
        $r->add('POST', '/account/password', [C\AccountController::class, 'password']);
        $r->add('POST', '/account/profile', [C\AccountController::class, 'profile']);
    }
}
