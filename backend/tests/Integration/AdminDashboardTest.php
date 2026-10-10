<?php
declare(strict_types=1);

namespace TaskBuddy\Tests\Integration;

use TaskBuddy\Admin\AdminApp;
use TaskBuddy\Admin\Http\Request as AdminRequest;
use TaskBuddy\Admin\Http\Response as AdminResponse;
use TaskBuddy\Admin\Http\Session;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;

/** Drives the administrator dashboard front controller (AdminApp::handle) against real MySQL. */
final class AdminDashboardTest extends ApiTestCase
{
    private Session $session;
    private const ADMIN_PASSWORD = 'Adm1n-Strong-Pass';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../../admin/app/bootstrap.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = Session::memory();
        \TaskBuddy\Support\Env::set('ADMIN_SESSION_IDLE_MINUTES', '30');
    }

    protected function tearDown(): void
    {
        // the catalog tables are reference data that the base class does not reset
        Database::run("DELETE FROM service_categories WHERE name = 'Pet Care'");
        Database::run("DELETE FROM skills WHERE name = 'Dog Walking'");
        parent::tearDown();
    }

    private function admin(string $email = 'admin@example.test'): int
    {
        return Database::insert("INSERT INTO users (email, password_hash, full_name, role, status) VALUES (?, ?, 'Site Admin', 'admin', 'active')", [$email, password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT)]);
    }

    /** @param array<string,mixed> $query @param array<string,mixed> $post */
    private function call(string $method, string $path, array $query = [], array $post = [], ?Session $session = null, bool $csrf = true): AdminResponse
    {
        $session ??= $this->session;
        if ($method === 'POST' && $csrf && !isset($post['_csrf'])) {
            $post['_csrf'] = $this->csrfToken($session);
        }
        return AdminApp::handle(new AdminRequest($method, $path, $query, $post, '198.51.100.9', 'phpunit'), $session, '');
    }

    private function csrfToken(Session $s): string
    {
        $t = $s->get('csrf');
        if (!is_string($t)) {
            $this->call('GET', '/login', [], [], $s);
            $t = bin2hex(random_bytes(32));
            $s->set('csrf', $t);
        }
        return $t;
    }

    private function login(string $email = 'admin@example.test'): void
    {
        $r = $this->call('POST', '/login', [], ['email' => $email, 'password' => self::ADMIN_PASSWORD]);
        $this->assertSame(303, $r->status, $r->body);
        $this->assertSame('/', $r->headers['Location']);
    }

    /** @return array{0:array,1:array,2:int} client, provider, request id with completed setup */
    private function assignedTask(): array
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 201);
        $this->ok($this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $prov['token']));
        return [$client, $prov, $rid];
    }

    public function testEveryPageRequiresAuthentication(): void
    {
        $this->admin();
        foreach (['/', '/users', '/providers', '/catalog', '/requests', '/requests/1', '/matching', '/matching/1', '/transactions', '/payments', '/disputes', '/disputes/1', '/moderation',
            '/announcements', '/logs', '/reports', '/reports/export', '/matching-config', '/account'] as $path) {
            $r = $this->call('GET', $path);
            $this->assertSame(303, $r->status, $path);
            $this->assertSame('/login', $r->headers['Location'], $path);
        }
        foreach (['/logout', '/users/1/status', '/catalog/categories', '/announcements', '/matching-config', '/account/password', '/disputes/1/resolve'] as $path) {
            $r = $this->call('POST', $path, [], [], null, false);
            $this->assertSame(303, $r->status, "POST $path");
            $this->assertSame('/login', $r->headers['Location']);
        }
    }

    public function testLoginSecurity(): void
    {
        $this->admin();
        $client = $this->register('client');
        $bad = $this->call('POST', '/login', [], ['email' => 'admin@example.test', 'password' => 'wrong']);
        $this->assertSame(401, $bad->status);
        $this->assertStringNotContainsString('wrong', $bad->body);
        $nonAdmin = $this->call('POST', '/login', [], ['email' => $client['email'], 'password' => $client['password']]);
        $this->assertSame(401, $nonAdmin->status);
        $this->assertNull($this->session->get('admin_id'));
        $this->login();
        $this->assertIsInt($this->session->get('admin_id'));
        $this->assertSame(200, $this->call('GET', '/')->status);
        $this->assertGreaterThan(0, (int)Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'ADMIN_LOGIN'"));
        $this->assertGreaterThan(0, (int)Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'ADMIN_LOGIN_FAILED'"));
        // logout destroys the session
        $this->assertSame(303, $this->call('POST', '/logout')->status);
        $this->assertSame('/login', $this->call('GET', '/')->headers['Location']);
    }

    public function testLoginIsRateLimited(): void
    {
        $this->admin();
        \TaskBuddy\Support\Env::set('RATE_LIMIT_AUTH_MAX', '3');
        try {
            $last = null;
            for ($i = 0; $i < 6; $i++) {
                $last = $this->call('POST', '/login', [], ['email' => 'admin@example.test', 'password' => 'nope-nope']);
            }
            $this->assertSame(429, $last->status);
        } finally {
            \TaskBuddy\Support\Env::set('RATE_LIMIT_AUTH_MAX', '1000');
        }
    }

    public function testNonAdminSessionIsRejectedAndSuspendedAdminIsLockedOut(): void
    {
        $client = $this->register('client');
        $this->session->set('admin_id', $client['id']);
        $this->session->set('login_at', Clock::now()->getTimestamp());
        $this->session->set('last_seen', Clock::now()->getTimestamp());
        $this->assertSame('/login', $this->call('GET', '/users')->headers['Location']);

        $id = $this->admin();
        $s = Session::memory();
        $this->loginWith($s);
        $this->assertSame(200, $this->call('GET', '/users', [], [], $s)->status);
        Database::run("UPDATE users SET status = 'suspended' WHERE id = ?", [$id]);
        $this->assertSame('/login', $this->call('GET', '/users', [], [], $s)->headers['Location']);
    }

    private function loginWith(Session $s): void
    {
        $r = $this->call('POST', '/login', [], ['email' => 'admin@example.test', 'password' => self::ADMIN_PASSWORD], $s);
        $this->assertSame(303, $r->status);
    }

    public function testIdleTimeoutEndsTheSession(): void
    {
        $this->admin();
        $this->login();
        $this->assertSame(200, $this->call('GET', '/')->status);
        $this->session->set('last_seen', Clock::now()->getTimestamp() - 31 * 60);
        $this->assertSame('/login', $this->call('GET', '/')->headers['Location']);
    }

    public function testCsrfTokenIsRequiredForEveryStateChange(): void
    {
        $this->admin();
        $client = $this->register('client');
        $this->login();
        foreach ([["/users/{$client['id']}/status", ['status' => 'suspended']], ['/announcements', ['title' => 'Hello', 'body' => 'World', 'audience' => 'all']],
            ['/catalog/categories', ['name' => 'Plumbing Pro']], ['/matching-config', ['w_category' => 25, 'w_skills' => 25, 'w_availability' => 25, 'w_budget' => 25]]] as [$path, $post]) {
            $r = $this->call('POST', $path, [], $post, null, false);
            $this->assertSame(403, $r->status, $path);
            $post['_csrf'] = 'forged-token';
            $this->assertSame(403, $this->call('POST', $path, [], $post, null, false)->status, $path . ' forged');
        }
        $this->assertSame('active', Database::value('SELECT status FROM users WHERE id = ?', [$client['id']]));
        $this->assertSame(1, (int)Database::value('SELECT COUNT(*) FROM matching_configurations'));
        $this->assertSame(0, (int)Database::value('SELECT COUNT(*) FROM announcements'));
    }

    public function testResponsesCarrySecurityHeaders(): void
    {
        $r = $this->call('GET', '/login');
        $this->assertSame(200, $r->status);
        foreach (['X-Content-Type-Options', 'X-Frame-Options', 'Content-Security-Policy', 'Referrer-Policy'] as $h) {
            $this->assertArrayHasKey($h, $r->headers);
        }
        $this->assertStringContainsString("default-src 'none'", $r->headers['Content-Security-Policy']);
        $this->assertStringNotContainsString('<script', $r->body);
        $this->assertSame(404, $this->call('GET', '/nope')->status);
        // the rendered login form must carry the CSRF token that the server then accepts
        $this->assertSame(1, preg_match('/name="_csrf" value="([0-9a-f]{64})"/', $r->body, $m));
        $this->assertSame($this->session->get('csrf'), $m[1]);
    }

    public function testAllPagesRenderWithRealData(): void
    {
        $this->admin();
        [$client, $prov, $rid] = $this->assignedTask();
        $this->ok($this->api('POST', "/api/tasks/$rid/dispute", ['reason' => 'Provider disappeared completely.'], $client['token']));
        $this->ok($this->api('POST', '/api/reports', ['user_id' => $prov['id'], 'reason' => 'Unprofessional behaviour'], $client['token']), 201);
        $this->ok($this->api('POST', "/api/conversations/" . $this->ok($this->api('GET', '/api/conversations', [], $client['token']))[0]['id'] . '/messages', ['body' => 'hello there'], $client['token']), 201);
        $this->login();
        $resultId = (int)Database::value('SELECT id FROM matching_results LIMIT 1');
        $disputeId = (int)Database::value('SELECT id FROM service_disputes LIMIT 1');
        $pages = ['/' => 'Total users', '/users' => $client['email'], '/providers' => 'Eligible for matching', '/catalog' => 'Home Repair', '/requests' => 'Fix my kitchen sink',
            "/requests/$rid" => 'SIMULATED / TEST TRANSACTION', '/matching' => 'Matching', "/matching/$resultId" => 'Providers evaluated', '/transactions' => 'Fix my kitchen sink',
            '/payments' => 'SIMULATED / TEST TRANSACTIONS', '/disputes' => 'Provider disappeared', "/disputes/$disputeId" => 'Resolve dispute', '/moderation' => 'Unprofessional behaviour',
            '/announcements' => 'New announcement', '/logs' => 'DISPUTE', '/reports' => 'CSV exports', '/matching-config' => 'Active weights', '/account' => 'Change password'];
        foreach ($pages as $path => $needle) {
            $r = $this->call('GET', $path);
            $this->assertSame(200, $r->status, $path);
            $this->assertStringContainsString($needle, $r->body, $path);
            $this->assertStringContainsString('TaskBuddy Admin', $r->body);
            $this->assertStringNotContainsString('Stack trace', $r->body);
            $this->assertStringNotContainsString('Warning:', $r->body);
        }
        $dash = $this->call('GET', '/')->body;
        $this->assertStringContainsString('<svg', $dash);
        $this->assertStringContainsString('SIMULATED / TEST', $dash);
        // filters and pagination parameters are accepted
        $this->assertSame(200, $this->call('GET', '/users', ['q' => '100%_', 'role' => 'client', 'status' => 'active', 'page' => '9999'])->status);
        $this->assertSame(200, $this->call('GET', '/requests', ['status' => 'DISPUTED', 'category' => '1'])->status);
        $this->assertSame(200, $this->call('GET', '/requests', ['status' => "x' OR 1=1 --"])->status);
        $this->assertSame(303, $this->call('GET', '/requests/99999')->status);
        $this->assertSame(303, $this->call('GET', '/disputes/99999')->status);
        $this->assertStringContainsString('not found', strtolower($this->call('GET', '/requests')->body));
    }

    public function testOutputIsEscaped(): void
    {
        $this->admin();
        $evil = '<script>alert(1)</script>';
        $this->ok($this->api('POST', '/api/auth/register', ['email' => 'x@example.test', 'password' => 'Passw0rd1', 'full_name' => $evil, 'role' => 'client']), 201);
        $this->login();
        $body = $this->call('GET', '/users')->body;
        $this->assertStringNotContainsString($evil, $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function testSuspendRevokesMobileAccessAndAdminsAreProtected(): void
    {
        $adminId = $this->admin();
        $client = $this->register('client');
        $this->login();
        $this->assertSame(303, $this->call('POST', "/users/{$client['id']}/status", [], ['status' => 'suspended', '_return' => '/users?role=client'])->status);
        $this->assertSame('suspended', Database::value('SELECT status FROM users WHERE id = ?', [$client['id']]));
        $this->assertError($this->api('GET', '/api/auth/me', [], $client['token']), 401, 'UNAUTHENTICATED');
        $this->assertSame('ACCOUNT_SUSPENDED', $this->api('POST', '/api/auth/login', ['email' => $client['email'], 'password' => $client['password']])[1]['error']['code']);
        $this->assertSame(1, (int)Database::value("SELECT COUNT(*) FROM activity_logs WHERE action = 'USER_SUSPENDED'"));
        $this->call('POST', "/users/{$client['id']}/status", [], ['status' => 'active']);
        $this->assertSame('active', Database::value('SELECT status FROM users WHERE id = ?', [$client['id']]));
        // administrators cannot be modified through the dashboard
        $this->call('POST', "/users/$adminId/status", [], ['status' => 'suspended']);
        $this->assertSame('active', Database::value('SELECT status FROM users WHERE id = ?', [$adminId]));
    }

    public function testReturnParameterCannotRedirectOffSite(): void
    {
        $this->admin();
        $client = $this->register('client');
        $this->login();
        foreach (['https://evil.example/x', '//evil.example', "/ok\r\nSet-Cookie: a=b", '\\\\evil'] as $ret) {
            $r = $this->call('POST', "/users/{$client['id']}/status", [], ['status' => 'suspended', '_return' => $ret]);
            $this->assertSame(303, $r->status);
            $this->assertSame('/users', $r->headers['Location'], $ret);
        }
    }

    public function testAdminResolvesDisputeAndStateAndPaymentFollow(): void
    {
        $this->admin();
        [$client, $prov, $rid] = $this->assignedTask();
        $this->ok($this->api('POST', "/api/tasks/$rid/dispute", ['reason' => 'The work was never delivered.'], $prov['token']));
        $this->login();
        $did = (int)Database::value('SELECT id FROM service_disputes LIMIT 1');
        // a note is mandatory and the outcome must be valid
        $this->call('POST', "/disputes/$did/resolve", [], ['resolution' => 'COMPLETE_TASK', 'note' => '']);
        $this->assertSame('OPEN', Database::value('SELECT status FROM service_disputes WHERE id = ?', [$did]));
        $this->call('POST', "/disputes/$did/resolve", [], ['resolution' => 'REFUND_EVERYTHING', 'note' => 'a valid note here']);
        $this->assertSame('OPEN', Database::value('SELECT status FROM service_disputes WHERE id = ?', [$did]));

        $this->call('POST', "/disputes/$did/resolve", [], ['resolution' => 'CANCEL_TASK', 'note' => 'Provider confirmed no work done.']);
        $this->assertSame('RESOLVED', Database::value('SELECT status FROM service_disputes WHERE id = ?', [$did]));
        $this->assertSame('CANCELLED', Database::value('SELECT status FROM service_requests WHERE id = ?', [$rid]));
        $this->assertSame('CANCELLED', Database::value('SELECT state FROM simulated_payments WHERE request_id = ?', [$rid]));
        // resolving twice is rejected
        $this->call('POST', "/disputes/$did/resolve", [], ['resolution' => 'COMPLETE_TASK', 'note' => 'second attempt here']);
        $this->assertSame('CANCELLED', Database::value('SELECT status FROM service_requests WHERE id = ?', [$rid]));
        $this->assertGreaterThan(0, count($this->ok($this->api('GET', '/api/notifications', [], $client['token']))));
    }

    public function testMatchingConfigurationVersioning(): void
    {
        $this->admin();
        $this->login();
        $r = $this->call('POST', '/matching-config', [], ['w_category' => '25', 'w_skills' => '25', 'w_availability' => '25', 'w_budget' => '25', 'note' => 'equal weights']);
        $this->assertSame(303, $r->status);
        $this->assertSame(2, (int)Database::value('SELECT COUNT(*) FROM matching_configurations'));
        $this->assertSame(25, (int)Database::value('SELECT weight_skills FROM matching_configurations WHERE is_active = 1'));
        // invalid sums / values are rejected with no change
        foreach ([['50', '50', '10', '10'], ['abc', '25', '25', '25'], ['-5', '55', '25', '25'], ['101', '0', '0', '0']] as [$a, $b, $c, $d]) {
            $this->call('POST', '/matching-config', [], ['w_category' => $a, 'w_skills' => $b, 'w_availability' => $c, 'w_budget' => $d]);
        }
        $this->assertSame(2, (int)Database::value('SELECT COUNT(*) FROM matching_configurations'));
        $this->assertSame(1, (int)Database::value('SELECT COUNT(*) FROM matching_configurations WHERE is_active = 1'));
        $flash = $this->call('GET', '/matching-config')->body;
        $this->assertStringContainsString('equal weights', $flash);
    }

    public function testAnnouncementReachesActiveUsersOnly(): void
    {
        $this->admin();
        $c = $this->register('client');
        $p = $this->register('provider');
        $susp = $this->register('client');
        Database::run("UPDATE users SET status = 'suspended' WHERE id = ?", [$susp['id']]);
        $this->login();
        $this->call('POST', '/announcements', [], ['title' => 'Maintenance', 'body' => 'Short downtime tonight.', 'audience' => 'clients']);
        $this->assertSame(1, (int)Database::value('SELECT recipients FROM announcements'));
        $n = $this->ok($this->api('GET', '/api/notifications', [], $c['token']));
        $this->assertSame('ANNOUNCEMENT', $n[0]['type']);
        $this->assertSame([], $this->ok($this->api('GET', '/api/notifications', [], $p['token'])));
        $this->call('POST', '/announcements', [], ['title' => 'x', 'body' => 'y', 'audience' => 'everyone']);
        $this->assertSame(1, (int)Database::value('SELECT COUNT(*) FROM announcements'));
    }

    public function testCatalogManagement(): void
    {
        Database::run("DELETE FROM service_categories WHERE name = 'Pet Care'");
        Database::run("DELETE FROM skills WHERE name = 'Dog Walking'");
        $this->admin();
        $this->login();
        $this->call('POST', '/catalog/categories', [], ['name' => 'Pet Care', 'description' => 'Walking and sitting']);
        $cid = (int)Database::value("SELECT id FROM service_categories WHERE name = 'Pet Care'");
        $this->assertGreaterThan(0, $cid);
        $this->call('POST', '/catalog/categories', [], ['name' => 'Pet Care']);
        $this->assertSame(1, (int)Database::value("SELECT COUNT(*) FROM service_categories WHERE name = 'Pet Care'"));
        $this->call('POST', '/catalog/skills', [], ['name' => 'Dog Walking', 'category_id' => (string)$cid]);
        $sid = (int)Database::value("SELECT id FROM skills WHERE name = 'Dog Walking'");
        $this->assertSame(1, (int)Database::value('SELECT COUNT(*) FROM category_skills WHERE category_id = ? AND skill_id = ?', [$cid, $sid]));
        $this->call('POST', "/catalog/categories/$cid", [], ['name' => 'Pet Care', 'description' => 'x']);
        $this->assertSame(0, (int)Database::value('SELECT is_active FROM service_categories WHERE id = ?', [$cid]));
        $this->call('POST', '/catalog/links', [], ['category_id' => (string)$cid, 'skill_id' => (string)$sid, 'op' => 'remove']);
        $this->assertSame(0, (int)Database::value('SELECT COUNT(*) FROM category_skills WHERE category_id = ? AND skill_id = ?', [$cid, $sid]));
        $this->call('POST', '/catalog/categories/99999', [], ['name' => 'Nope']);
        $this->assertSame(0, (int)Database::value("SELECT COUNT(*) FROM service_categories WHERE name = 'Nope'"));
        // deactivated categories disappear from the public catalog
        $names = array_column($this->ok($this->api('GET', '/api/categories')), 'name');
        $this->assertNotContains('Pet Care', $names);
    }

    public function testCsvExportsAreSafe(): void
    {
        $this->admin();
        $this->ok($this->api('POST', '/api/auth/register', ['email' => 'csv@example.test', 'password' => 'Passw0rd1', 'full_name' => '=HYPERLINK("http://evil")', 'role' => 'client']), 201);
        $this->login();
        $r = $this->call('GET', '/reports/export', ['type' => 'users']);
        $this->assertSame(200, $r->status);
        $this->assertStringStartsWith("\xEF\xBB\xBF\"id\",\"full_name\"", $r->body);
        $this->assertStringContainsString("\"'=HYPERLINK(", $r->body);
        $this->assertStringNotContainsString('password', strtolower($r->body));
        $this->assertStringContainsString('attachment', $r->headers['Content-Disposition']);
        foreach (['requests', 'matching', 'payments', 'logs'] as $t) {
            $this->assertSame(200, $this->call('GET', '/reports/export', ['type' => $t])->status);
        }
        $this->assertSame(303, $this->call('GET', '/reports/export', ['type' => 'secrets'])->status);
    }

    public function testAccountPasswordChange(): void
    {
        $this->admin();
        $this->login();
        $this->call('POST', '/account/password', [], ['current_password' => 'wrong-password-1', 'new_password' => 'Another-Strong-Pass1', 'confirm_password' => 'Another-Strong-Pass1']);
        $this->assertTrue(password_verify(self::ADMIN_PASSWORD, (string)Database::value("SELECT password_hash FROM users WHERE email = 'admin@example.test'")));
        $this->call('POST', '/account/password', [], ['current_password' => self::ADMIN_PASSWORD, 'new_password' => 'short1', 'confirm_password' => 'short1']);
        $this->assertTrue(password_verify(self::ADMIN_PASSWORD, (string)Database::value("SELECT password_hash FROM users WHERE email = 'admin@example.test'")));
        $this->call('POST', '/account/password', [], ['current_password' => self::ADMIN_PASSWORD, 'new_password' => 'Another-Strong-Pass1', 'confirm_password' => 'Another-Strong-Pass1']);
        $this->assertTrue(password_verify('Another-Strong-Pass1', (string)Database::value("SELECT password_hash FROM users WHERE email = 'admin@example.test'")));
    }

    public function testAdminCannotSeeUploadedFileContents(): void
    {
        $this->admin();
        [$client, $prov, $rid] = $this->assignedTask();
        $files = ['files' => $this->fakeUpload('secret-report.pdf', "%PDF-1.4\n%secret-content\n%%EOF", 'application/pdf')];
        $this->ok($this->api('POST', "/api/tasks/$rid/submit", ['note' => 'done'], $prov['token'], [], $files), 201);
        $this->login();
        $body = $this->call('GET', "/requests/$rid")->body;
        $this->assertStringContainsString('secret-report.pdf', $body);
        $this->assertStringNotContainsString('secret-content', $body);
        $this->assertStringNotContainsString('stored_name', $body);
        $this->assertSame(404, $this->call('GET', '/submission-files/1/download')->status);
    }
}
