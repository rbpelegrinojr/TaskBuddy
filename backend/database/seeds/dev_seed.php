<?php
declare(strict_types=1);

/**
 * DEVELOPMENT ONLY. Creates demo accounts through the public API layer (so every business rule applies)
 * and one open service request (matching runs automatically when a request is created). Passwords are random, printed once, never stored in the repo.
 *
 *   php database/seeds/dev_seed.php
 *
 * Refuses to run when APP_ENV=production or when users already exist (unless SEED_FORCE=1).
 */

use TaskBuddy\Support\App;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Env;
use TaskBuddy\Support\Request;

require __DIR__ . '/../../vendor/autoload.php';
App::boot();

if (strtolower((string)Env::get('APP_ENV', 'production')) === 'production') {
    fwrite(STDERR, "Refusing to seed demo data when APP_ENV=production.\n");
    exit(1);
}
if ((int)Database::value('SELECT COUNT(*) FROM users') > 0 && getenv('SEED_FORCE') !== '1') {
    fwrite(STDERR, "Users already exist. Set SEED_FORCE=1 to add demo accounts anyway.\n");
    exit(1);
}

/** @return array<string,mixed> */
function call(string $method, string $path, array $body = [], ?string $token = null): array
{
    $headers = ['content-type' => 'application/json'] + ($token ? ['authorization' => 'Bearer ' . $token] : []);
    $res = App::handle(new Request($method, $path, [], $body, $headers, [], '127.0.0.1'));
    if ($res->status >= 400) {
        fwrite(STDERR, "Seed step failed: $method $path -> {$res->status} " . json_encode($res->payload) . "\n");
        exit(1);
    }
    return (array)($res->payload['data'] ?? []);
}

function account(string $role, string $name, string $email): array
{
    $password = bin2hex(random_bytes(9)) . 'Aa1';
    $d = call('POST', '/api/auth/register', ['email' => $email, 'password' => $password, 'full_name' => $name, 'role' => $role]);
    echo str_pad($email, 34) . $password . "\n";
    return ['id' => (int)$d['user']['id'], 'token' => $d['token']];
}

$cat = (int)Database::value("SELECT id FROM service_categories WHERE name = 'Home Repair'") ?: (int)Database::value('SELECT id FROM service_categories ORDER BY id LIMIT 1');
$skills = array_map('intval', array_column(Database::all('SELECT s.id FROM skills s JOIN category_skills cs ON cs.skill_id = s.id WHERE cs.category_id = ? ORDER BY s.id LIMIT 4', [$cat]), 'id'));
if (count($skills) < 4) {
    fwrite(STDERR, "Reference data is missing; run php bin/migrate.php first.\n");
    exit(1);
}

echo "Demo accounts (email / one-time password):\n";
$client = account('client', 'Demo Client', 'client@demo.example');
$week = array_map(static fn($d) => ['day_of_week' => $d, 'start_time' => '09:00', 'end_time' => '17:00'], [1, 2, 3, 4, 5]);
$providers = [
    ['Demo Provider One', 'provider1@demo.example', $skills, '400.00'],
    ['Demo Provider Two', 'provider2@demo.example', array_slice($skills, 0, 3), '450.00'],
    ['Demo Provider Three', 'provider3@demo.example', array_slice($skills, 0, 1), '300.00'],
    ['Demo Provider Four', 'provider4@demo.example', $skills, '800.00'],
];
foreach ($providers as [$name, $email, $ids, $rate]) {
    $p = account('provider', $name, $email);
    call('PUT', '/api/profile', ['headline' => 'Demo service provider', 'experience_summary' => 'Demo profile used for local testing only.', 'years_experience' => 5, 'min_rate' => $rate, 'category_ids' => [$cat]], $p['token']);
    call('PUT', '/api/provider/skills', ['skill_ids' => $ids], $p['token']);
    call('PUT', '/api/provider/availability', ['slots' => $week], $p['token']);
}

$req = call('POST', '/api/requests', [
    'title' => 'Demo: repair a leaking kitchen sink', 'description' => 'Demo request created by the development seed.',
    'category_id' => $cat, 'skills' => array_map(static fn($id, $i) => ['skill_id' => $id, 'is_mandatory' => $i === 0], $skills, array_keys($skills)),
    'deadline' => gmdate('Y-m-d\TH:i:s\Z', time() + 7 * 86400), 'compensation' => '500.00', 'estimated_hours' => 3,
], $client['token']);
$rid = (int)$req['request']['id'];
echo "\nCreated demo request #$rid (matching runs automatically when a request is created). Create an administrator with bin/create_admin.php.\n";
