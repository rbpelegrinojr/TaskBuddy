<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\AuthService;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

final class AuthController extends BaseController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function health(Request $r, array $p): Response
    {
        return Response::ok(['status' => 'ok']);
    }

    public function register(Request $r, array $p): Response
    {
        return Response::created($this->auth->register($r->body, $r->ip, $r->header('user-agent')));
    }

    public function login(Request $r, array $p): Response
    {
        return Response::ok($this->auth->login($r->body, $r->ip, $r->header('user-agent')));
    }

    public function logout(Request $r, array $p): Response
    {
        $this->auth->logout((int)Auth::tokenId());
        return Response::ok(['logged_out' => true]);
    }

    public function me(Request $r, array $p): Response
    {
        return Response::ok(['user' => AuthService::publicUser(Auth::id())]);
    }

    public function refresh(Request $r, array $p): Response
    {
        return Response::ok($this->auth->refresh(Auth::id(), (int)Auth::tokenId(), $r->header('user-agent')));
    }
}
