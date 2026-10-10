<?php
declare(strict_types=1);

/**
 * Creates an administrator account. The password is never stored in the repository:
 *   ADMIN_EMAIL=you@example.com ADMIN_NAME="Your Name" ADMIN_PASSWORD='...' php bin/create_admin.php
 * If ADMIN_PASSWORD is not set the script prompts for it (input is not echoed on POSIX terminals).
 */

use TaskBuddy\Support\App;
use TaskBuddy\Support\Database;
use TaskBuddy\Services\AuditLogger;

require __DIR__ . '/../vendor/autoload.php';
App::boot();

$email = strtolower(trim((string)getenv('ADMIN_EMAIL')));
$name = trim((string)(getenv('ADMIN_NAME') ?: 'Administrator'));
$password = (string)getenv('ADMIN_PASSWORD');
if ($password === '') {
    fwrite(STDOUT, 'Password (min 12 chars): ');
    @system('stty -echo 2>/dev/null');
    $password = trim((string)fgets(STDIN));
    @system('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "A valid ADMIN_EMAIL and a password of 12-72 characters are required.\n");
    exit(1);
}
try {
    $id = Database::insert("INSERT INTO users (email, password_hash, full_name, role) VALUES (?,?,?, 'admin')", [$email, password_hash($password, PASSWORD_DEFAULT), $name]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not create administrator (email already in use?).\n");
    exit(1);
}
Database::run('INSERT INTO user_profiles (user_id) VALUES (?)', [$id]);
AuditLogger::log(null, 'system', 'ADMIN_CREATED', 'user', $id);
echo "Administrator #$id created.\n";
