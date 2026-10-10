<?php
declare(strict_types=1);

namespace TaskBuddy\Middleware;

use TaskBuddy\Services\AuthService;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;

/** Resolves the bearer token to a server-side identity and enforces role requirements. */
final class AuthMiddleware
{
    /** @param string[] $roles */
    public static function handle(Request $r, array $roles): void
    {
        $token = $r->bearerToken();
        if ($token === null) {
            throw ApiException::unauthorized();
        }
        $res = (new AuthService())->authenticate($token);
        if ($res === null) {
            throw ApiException::unauthorized('Invalid or expired token.');
        }
        Auth::set($res['user'], $res['token_id']);
        if ($roles && !in_array($res['user']['role'], $roles, true)) {
            throw ApiException::forbidden('Your role is not permitted to use this endpoint.');
        }
    }
}
