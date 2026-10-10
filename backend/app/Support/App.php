<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

use Throwable;
use TaskBuddy\Middleware\AuthMiddleware;
use TaskBuddy\Services\AuditLogger;

/** Front controller: routing, authentication, safe error handling. */
final class App
{
    public static function boot(): void
    {
        Env::load(Paths::backend() . '/.env');
        date_default_timezone_set('UTC');
        mb_internal_encoding('UTF-8');
    }

    public static function handle(Request $request): Response
    {
        Auth::set(null);
        AuditLogger::$ip = $request->ip;
        try {
            $router = new Router();
            (require Paths::backend() . '/config/routes.php')($router);
            $m = $router->match($request->method, $request->path);
            if ($m === null) {
                throw ApiException::notFound('Endpoint');
            }
            if ($m['route']['auth']) {
                AuthMiddleware::handle($request, $m['route']['roles']);
            }
            [$class, $method] = $m['route']['handler'];
            $response = (new $class())->$method($request, $m['params']);
            return $response;
        } catch (ApiException $e) {
            return Response::error($e->status, $e->errorCode, $e->getMessage(), $e->details);
        } catch (Throwable $e) {
            Logger::error('Unhandled exception', ['type' => $e::class, 'message' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
            $details = Env::bool('APP_DEBUG') && Env::get('APP_ENV') !== 'production' ? ['debug' => $e->getMessage()] : [];
            return Response::error(500, 'INTERNAL_ERROR', 'An unexpected error occurred.', $details);
        }
    }
}
