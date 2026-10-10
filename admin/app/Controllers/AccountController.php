<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Services\AuditLogger;
use TaskBuddy\Services\AuthService;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;

final class AccountController extends Controller
{
    public function index(): Response
    {
        return $this->view('account', 'Administrator account', ['me' => Database::one('SELECT email, full_name, last_login_at, created_at FROM users WHERE id = ?', [$this->adminId()])]);
    }

    public function profile(): Response
    {
        $name = $this->ctx->request->postStr('full_name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            throw ApiException::validation(['full_name' => 'Length must be between 2 and 150 characters.']);
        }
        Database::run('UPDATE users SET full_name = ? WHERE id = ?', [$name, $this->adminId()]);
        AuditLogger::log($this->adminId(), 'admin', 'ADMIN_PROFILE_UPDATED', 'user', $this->adminId());
        return $this->redirect('/account', 'Profile updated.');
    }

    public function password(): Response
    {
        $r = $this->ctx->request;
        $current = (string)($r->post['current_password'] ?? '');
        $new = (string)($r->post['new_password'] ?? '');
        $confirm = (string)($r->post['confirm_password'] ?? '');
        $errors = [];
        if (strlen($new) < 12 || strlen($new) > 72) {
            $errors['new_password'] = 'Use 12 to 72 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors['new_password'] = 'Include at least one letter and one digit.';
        } elseif (!hash_equals($new, $confirm)) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        if ($errors) {
            throw ApiException::validation($errors);
        }
        $email = (string)$this->ctx->admin['email'];
        try {
            (new AuthService())->verifyCredentials($email, $current);
        } catch (ApiException) {
            throw ApiException::validation(['current_password' => 'The current password is incorrect.']);
        }
        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $this->adminId()]);
        AuditLogger::log($this->adminId(), 'admin', 'ADMIN_PASSWORD_CHANGED', 'user', $this->adminId());
        $s = $this->ctx->session;
        $s->regenerate();
        $s->set('login_at', Clock::now()->getTimestamp());
        return $this->redirect('/account', 'Password changed.');
    }
}
