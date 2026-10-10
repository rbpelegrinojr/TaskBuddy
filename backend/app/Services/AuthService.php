<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use PDOException;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Env;
use TaskBuddy\Support\Validator;

final class AuthService
{
    /** Hash used to equalise timing when an email does not exist. */
    private const DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function register(array $in, string $ip, ?string $userAgent): array
    {
        RateLimiter::hit("register:$ip", Env::int('RATE_LIMIT_AUTH_MAX', 10), Env::int('RATE_LIMIT_AUTH_WINDOW', 900));
        $v = new Validator($in);
        $email = $v->email('email');
        $name = $v->string('full_name', true, 2, 150);
        $role = $v->enum('role', ['client', 'provider']);
        $phone = $v->string('phone', false, 5, 30);
        $password = $in['password'] ?? null;
        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72
            || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $v->error('password', 'Must be 8-72 characters and contain at least one letter and one digit.');
        }
        $v->fail();

        $userId = Database::transaction(function () use ($email, $password, $name, $role, $phone): int {
            try {
                $id = Database::insert(
                    'INSERT INTO users (email, password_hash, full_name, role, phone) VALUES (?,?,?,?,?)',
                    [$email, password_hash((string)$password, PASSWORD_DEFAULT), $name, $role, $phone]
                );
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    throw ApiException::conflict('EMAIL_TAKEN', 'An account with this email already exists.');
                }
                throw $e;
            }
            Database::run('INSERT INTO user_profiles (user_id) VALUES (?)', [$id]);
            AuditLogger::log($id, $role, 'USER_REGISTERED', 'user', $id, ['role' => $role]);
            return $id;
        });
        return $this->session($userId, $userAgent);
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function login(array $in, string $ip, ?string $userAgent): array
    {
        $v = new Validator($in);
        $email = $v->email('email');
        $password = $in['password'] ?? null;
        if (!is_string($password) || $password === '') {
            $v->error('password', 'This field is required.');
        }
        $v->fail();
        RateLimiter::hit("login:$ip:$email", Env::int('RATE_LIMIT_AUTH_MAX', 10), Env::int('RATE_LIMIT_AUTH_WINDOW', 900));

        $user = $this->verifyCredentials((string)$email, (string)$password);
        if ($user['role'] === 'admin') {
            throw ApiException::forbidden('Administrator accounts cannot sign in through the mobile application.');
        }
        RateLimiter::reset("login:$ip:$email");
        Database::run('UPDATE users SET last_login_at = ? WHERE id = ?', [Clock::sql(), $user['id']]);
        AuditLogger::log((int)$user['id'], (string)$user['role'], 'USER_LOGIN', 'user', (int)$user['id']);
        return $this->session((int)$user['id'], $userAgent);
    }

    /**
     * Shared by the mobile API and the admin dashboard.
     * @return array<string,mixed> user row (without password_hash)
     */
    public function verifyCredentials(string $email, string $password): array
    {
        $row = Database::one('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
        $ok = password_verify($password, $row['password_hash'] ?? self::DUMMY_HASH);
        if ($row === null || !$ok) {
            throw new ApiException(401, 'INVALID_CREDENTIALS', 'Incorrect email or password.');
        }
        if ($row['status'] !== 'active') {
            throw new ApiException(403, 'ACCOUNT_SUSPENDED', 'This account is suspended. Please contact support.');
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        unset($row['password_hash']);
        return $row;
    }

    /** @return array<string,mixed> */
    public function session(int $userId, ?string $userAgent): array
    {
        $token = bin2hex(random_bytes(32));
        $ttl = Env::int('TOKEN_TTL_HOURS', 168);
        $expires = Clock::now()->modify("+$ttl hours");
        Database::insert(
            'INSERT INTO mobile_auth_tokens (user_id, token_hash, user_agent, expires_at) VALUES (?,?,?,?)',
            [$userId, hash('sha256', $token), $userAgent !== null ? mb_substr($userAgent, 0, 255) : null, Clock::sql($expires)]
        );
        return [
            'user' => self::publicUser($userId),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => Clock::iso(Clock::sql($expires)),
        ];
    }

    /** @return array{user:array<string,mixed>,token_id:int}|null */
    public function authenticate(string $token): ?array
    {
        $row = Database::one(
            'SELECT t.id AS token_id, t.expires_at, t.revoked_at, t.last_used_at, u.id, u.email, u.full_name, u.role, u.status, u.phone, u.created_at
             FROM mobile_auth_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ?',
            [hash('sha256', $token)]
        );
        if ($row === null || $row['revoked_at'] !== null || $row['expires_at'] <= Clock::sql() || $row['status'] !== 'active' || $row['role'] === 'admin') {
            return null;
        }
        if ($row['last_used_at'] === null || $row['last_used_at'] < Clock::sql(Clock::now()->modify('-5 minutes'))) {
            Database::run('UPDATE mobile_auth_tokens SET last_used_at = ? WHERE id = ?', [Clock::sql(), $row['token_id']]);
        }
        $tokenId = (int)$row['token_id'];
        unset($row['token_id'], $row['expires_at'], $row['revoked_at'], $row['last_used_at']);
        return ['user' => $row, 'token_id' => $tokenId];
    }

    public function logout(int $tokenId): void
    {
        Database::run('UPDATE mobile_auth_tokens SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [Clock::sql(), $tokenId]);
    }

    /** Rotate the presented token: the old token is revoked and a new one issued. @return array<string,mixed> */
    public function refresh(int $userId, int $tokenId, ?string $userAgent): array
    {
        return Database::transaction(function () use ($userId, $tokenId, $userAgent): array {
            $this->logout($tokenId);
            return $this->session($userId, $userAgent);
        });
    }

    /** @return array<string,mixed> */
    public static function publicUser(int $id): array
    {
        $u = Database::one('SELECT id, email, full_name, role, status, phone, created_at FROM users WHERE id = ?', [$id]);
        if ($u === null) {
            throw ApiException::notFound('User');
        }
        return self::formatUser($u);
    }

    /** @param array<string,mixed> $u @return array<string,mixed> */
    public static function formatUser(array $u): array
    {
        return [
            'id' => (int)$u['id'], 'email' => $u['email'], 'full_name' => $u['full_name'], 'role' => $u['role'],
            'status' => $u['status'], 'phone' => $u['phone'], 'created_at' => Clock::iso($u['created_at']),
        ];
    }
}
