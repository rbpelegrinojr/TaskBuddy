<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Services\AuditLogger;
use TaskBuddy\Services\AuthService;
use TaskBuddy\Services\RateLimiter;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Env;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        if (is_int($this->ctx->session->get('admin_id'))) {
            return $this->redirect('/');
        }
        return $this->view('login', 'Sign in', ['_bare' => true]);
    }

    public function login(): Response
    {
        $req = $this->ctx->request;
        $email = strtolower($req->postStr('email'));
        $password = $req->post['password'] ?? '';
        $generic = 'Incorrect email or password, or this account is not an administrator.';
        try {
            RateLimiter::hit('admin-login:' . $req->ip . ':' . $email, Env::int('RATE_LIMIT_AUTH_MAX', 10), Env::int('RATE_LIMIT_AUTH_WINDOW', 900));
        } catch (ApiException $e) {
            return $this->view('login', 'Sign in', ['_bare' => true, 'error' => 'Too many attempts. Please try again later.', 'email' => $email], 429);
        }
        if ($email === '' || !is_string($password) || $password === '') {
            return $this->view('login', 'Sign in', ['_bare' => true, 'error' => $generic, 'email' => $email], 422);
        }
        try {
            $user = (new AuthService())->verifyCredentials($email, $password);
            if ($user['role'] !== 'admin') {
                throw new ApiException(401, 'INVALID_CREDENTIALS', $generic);
            }
        } catch (ApiException) {
            AuditLogger::log(null, null, 'ADMIN_LOGIN_FAILED', 'user', null, ['email_hash' => substr(hash('sha256', $email), 0, 16)]);
            return $this->view('login', 'Sign in', ['_bare' => true, 'error' => $generic, 'email' => $email], 401);
        }
        RateLimiter::reset('admin-login:' . $req->ip . ':' . $email);
        $s = $this->ctx->session;
        $s->regenerate();
        $s->set('admin_id', (int)$user['id']);
        $s->set('login_at', Clock::now()->getTimestamp());
        $s->set('last_seen', Clock::now()->getTimestamp());
        $s->forget('csrf');
        Database::run('UPDATE users SET last_login_at = ? WHERE id = ?', [Clock::sql(), $user['id']]);
        AuditLogger::log((int)$user['id'], 'admin', 'ADMIN_LOGIN', 'user', (int)$user['id']);
        return Response::redirect($this->ctx->url('/'));
    }

    public function logout(): Response
    {
        AuditLogger::log($this->adminId(), 'admin', 'ADMIN_LOGOUT', 'user', $this->adminId());
        $this->ctx->session->destroy();
        return Response::redirect($this->ctx->url('/login'));
    }
}
