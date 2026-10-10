<?php
declare(strict_types=1);

namespace TaskBuddy\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TaskBuddy\Services\FileService;
use TaskBuddy\Support\App;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Env;
use TaskBuddy\Support\Request;

/**
 * Drives the real front controller (App::handle) against a real MySQL database.
 * Needs TB_TEST_DB_* environment variables and an already migrated database
 * (php bin/migrate.php --fresh); otherwise tests are skipped, never faked.
 */
abstract class ApiTestCase extends TestCase
{
    private const REFERENCE_TABLES = ['schema_migrations', 'matching_configurations', 'service_categories', 'skills', 'category_skills'];

    protected string $storage = '';

    public static function setUpBeforeClass(): void
    {
        $name = getenv('TB_TEST_DB_NAME');
        if (!$name) {
            return;
        }
        Env::set('DB_HOST', getenv('TB_TEST_DB_HOST') ?: '127.0.0.1');
        Env::set('DB_PORT', getenv('TB_TEST_DB_PORT') ?: '3306');
        Env::set('DB_NAME', $name);
        Env::set('DB_USER', getenv('TB_TEST_DB_USER') ?: 'root');
        Env::set('DB_PASS', getenv('TB_TEST_DB_PASS') ?: '');
        Env::set('APP_ENV', 'testing');
        Env::set('APP_TIMEZONE', 'UTC');
        Env::set('RATE_LIMIT_AUTH_MAX', '1000');
        Database::reset();
    }

    protected function setUp(): void
    {
        if (!getenv('TB_TEST_DB_NAME')) {
            $this->markTestSkipped('TB_TEST_DB_NAME not set: integration tests need a MySQL test database.');
        }
        if (stripos((string)getenv('TB_TEST_DB_NAME'), 'test') === false) {
            $this->fail('Refusing to run: the integration tests truncate every table, so TB_TEST_DB_NAME must contain "test".');
        }
        Clock::freeze(null);
        $this->resetDatabase();
        $this->storage = sys_get_temp_dir() . '/tb_storage_' . bin2hex(random_bytes(4));
        mkdir($this->storage . '/uploads', 0775, true);
        Env::set('STORAGE_PATH', $this->storage);
        FileService::$allowNonUploaded = true;
    }

    protected function tearDown(): void
    {
        if ($this->storage !== '' && is_dir($this->storage)) {
            exec('rm -rf ' . escapeshellarg($this->storage));
        }
        FileService::$allowNonUploaded = false;
    }

    protected function resetDatabase(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $t) {
            if (!in_array($t, self::REFERENCE_TABLES, true)) {
                $pdo->exec('TRUNCATE TABLE `' . $t . '`');
            }
        }
        $pdo->exec('DELETE FROM matching_configurations WHERE version > 1');
        $pdo->exec('UPDATE matching_configurations SET is_active = 1 WHERE version = 1');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * @param array<string,mixed> $body
     * @param array<string,mixed> $query
     * @param array<string,mixed> $files
     * @return array{0:int,1:array<string,mixed>}
     */
    protected function api(string $method, string $path, array $body = [], ?string $token = null, array $query = [], array $files = []): array
    {
        $headers = ['content-type' => 'application/json'];
        if ($token !== null) {
            $headers['authorization'] = 'Bearer ' . $token;
        }
        $res = App::handle(new Request($method, $path, $query, $body, $headers, $files, '203.0.113.7'));
        return [$res->status, (array)$res->payload];
    }

    /** @return array{0:int,1:\TaskBuddy\Support\Response} */
    protected function raw(string $method, string $path, ?string $token): array
    {
        $headers = $token ? ['authorization' => 'Bearer ' . $token] : [];
        $res = App::handle(new Request($method, $path, [], [], $headers));
        return [$res->status, $res];
    }

    /** @return array<string,mixed> */
    protected function ok(array $r, int $status = 200): array
    {
        $this->assertSame($status, $r[0], 'Unexpected status; body: ' . json_encode($r[1]));
        $this->assertTrue($r[1]['success'] ?? false);
        return $r[1]['data'] ?? [];
    }

    protected function assertError(array $r, int $status, string $code): void
    {
        $this->assertSame($status, $r[0], 'Unexpected status; body: ' . json_encode($r[1]));
        $this->assertFalse($r[1]['success']);
        $this->assertSame($code, $r[1]['error']['code'], json_encode($r[1]));
    }

    /** @return array{id:int,token:string} */
    protected function register(string $role, string $name = '', string $email = ''): array
    {
        static $n = 0;
        $n++;
        $email = $email ?: "$role$n@example.test";
        $d = $this->ok($this->api('POST', '/api/auth/register', [
            'email' => $email, 'password' => 'Passw0rd-' . $n, 'full_name' => $name ?: ucfirst($role) . " $n", 'role' => $role,
        ]), 201);
        return ['id' => (int)$d['user']['id'], 'token' => $d['token'], 'email' => $email, 'password' => 'Passw0rd-' . $n];
    }

    protected function categoryId(string $name = null): int
    {
        return (int)Database::value($name ? 'SELECT id FROM service_categories WHERE name = ?' : 'SELECT id FROM service_categories ORDER BY id LIMIT 1', $name ? [$name] : []);
    }

    /** @return int[] */
    protected function skillIds(int $categoryId, int $limit = 3): array
    {
        return array_map('intval', array_column(Database::all('SELECT s.id FROM skills s JOIN category_skills cs ON cs.skill_id = s.id WHERE cs.category_id = ? ORDER BY s.id LIMIT ' . $limit, [$categoryId]), 'id'));
    }

    /** Create a fully profiled, available provider. @return array{id:int,token:string} */
    protected function provider(int $categoryId, array $skillIds, string $minRate = '100.00', string $name = ''): array
    {
        $p = $this->register('provider', $name);
        $this->ok($this->api('PUT', '/api/profile', [
            'headline' => 'Experienced pro', 'experience_summary' => 'Ten years of delivering quality work.', 'years_experience' => 10,
            'min_rate' => $minRate, 'category_ids' => [$categoryId],
        ], $p['token']));
        $this->ok($this->api('PUT', '/api/provider/skills', ['skill_ids' => $skillIds], $p['token']));
        $slots = [];
        for ($d = 0; $d <= 6; $d++) {
            $slots[] = ['day_of_week' => $d, 'start_time' => '00:00', 'end_time' => '23:59'];
        }
        $this->ok($this->api('PUT', '/api/provider/availability', ['slots' => $slots], $p['token']));
        return $p;
    }

    /** @return array<string,mixed> request payload */
    protected function requestPayload(int $categoryId, array $skillIds, array $over = []): array
    {
        return array_merge([
            'title' => 'Fix my kitchen sink', 'description' => 'The sink leaks and must be repaired properly.',
            'category_id' => $categoryId,
            'skills' => array_map(static fn($id, $i) => ['skill_id' => $id, 'is_mandatory' => $i === 0], $skillIds, array_keys($skillIds)),
            'deadline' => gmdate('Y-m-d\TH:i:s\Z', time() + 5 * 86400), 'compensation' => '500.00', 'estimated_hours' => 2,
        ], $over);
    }

    /** Write a tiny valid file and return a $_FILES style entry. @return array<string,mixed> */
    protected function fakeUpload(string $name, string $content, string $mime = 'application/octet-stream'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tbup');
        file_put_contents($tmp, $content);
        return ['name' => $name, 'type' => $mime, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    protected function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }
}
