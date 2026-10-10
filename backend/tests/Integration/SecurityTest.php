<?php
declare(strict_types=1);

namespace TaskBuddy\Tests\Integration;

use TaskBuddy\Services\AuthService;
use TaskBuddy\Services\MatchingService;
use TaskBuddy\Services\PaymentService;
use TaskBuddy\Services\TaskStateMachine;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

final class SecurityTest extends ApiTestCase
{
    public function testRegistrationValidationAndDuplicateEmail(): void
    {
        $ok = ['email' => 'a@example.test', 'password' => 'Passw0rd1', 'full_name' => 'A B', 'role' => 'client'];
        $this->ok($this->api('POST', '/api/auth/register', $ok), 201);
        $this->assertError($this->api('POST', '/api/auth/register', array_merge($ok, ['email' => 'A@Example.test'])), 409, 'EMAIL_TAKEN');
        $this->assertError($this->api('POST', '/api/auth/register', array_merge($ok, ['email' => 'bad'])), 422, 'VALIDATION_FAILED');
        $this->assertError($this->api('POST', '/api/auth/register', array_merge($ok, ['email' => 'b@example.test', 'password' => 'short'])), 422, 'VALIDATION_FAILED');
        $this->assertError($this->api('POST', '/api/auth/register', array_merge($ok, ['email' => 'c@example.test', 'password' => 'onlyletters'])), 422, 'VALIDATION_FAILED');
        // role escalation through the public API is impossible
        $this->assertError($this->api('POST', '/api/auth/register', array_merge($ok, ['email' => 'd@example.test', 'role' => 'admin'])), 422, 'VALIDATION_FAILED');
    }

    public function testPasswordsAndTokensAreStoredHashed(): void
    {
        $u = $this->register('client');
        $row = Database::one('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
        $this->assertStringStartsWith('$', $row['password_hash']);
        $this->assertStringNotContainsString($u['password'], $row['password_hash']);
        $this->assertSame(0, (int)Database::value('SELECT COUNT(*) FROM mobile_auth_tokens WHERE token_hash = ?', [$u['token']]));
        $this->assertSame(1, (int)Database::value('SELECT COUNT(*) FROM mobile_auth_tokens WHERE token_hash = ?', [hash('sha256', $u['token'])]));
    }

    public function testLoginLogoutRefreshAndExpiry(): void
    {
        $u = $this->register('client');
        $this->assertError($this->api('POST', '/api/auth/login', ['email' => $u['email'], 'password' => 'wrong-Pass1']), 401, 'INVALID_CREDENTIALS');
        $this->assertError($this->api('POST', '/api/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong-Pass1']), 401, 'INVALID_CREDENTIALS');
        $s = $this->ok($this->api('POST', '/api/auth/login', ['email' => $u['email'], 'password' => $u['password']]));
        $this->assertSame($u['id'], $s['user']['id']);
        $this->assertArrayNotHasKey('password_hash', $s['user']);

        $new = $this->ok($this->api('POST', '/api/auth/refresh', [], $s['token']));
        $this->assertNotSame($s['token'], $new['token']);
        $this->assertError($this->api('GET', '/api/auth/me', [], $s['token']), 401, 'UNAUTHENTICATED');
        $this->ok($this->api('GET', '/api/auth/me', [], $new['token']));

        $this->ok($this->api('POST', '/api/auth/logout', [], $new['token']));
        $this->assertError($this->api('GET', '/api/auth/me', [], $new['token']), 401, 'UNAUTHENTICATED');

        $t = $this->ok($this->api('POST', '/api/auth/login', ['email' => $u['email'], 'password' => $u['password']]))['token'];
        Database::run('UPDATE mobile_auth_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)');
        $this->assertError($this->api('GET', '/api/auth/me', [], $t), 401, 'UNAUTHENTICATED');
    }

    public function testMissingOrMalformedTokenIsRejected(): void
    {
        $this->assertError($this->api('GET', '/api/profile'), 401, 'UNAUTHENTICATED');
        $this->assertError($this->api('GET', '/api/profile', [], 'not-a-real-token-0123456789abcdef'), 401, 'UNAUTHENTICATED');
        $this->assertError($this->api('GET', '/api/nope'), 404, 'NOT_FOUND');
        $this->assertError($this->api('DELETE', '/api/categories'), 405, 'METHOD_NOT_ALLOWED');
    }

    public function testAdminAccountsCannotUseMobileApiAndSuspendedUsersAreBlocked(): void
    {
        $admin = (new AuthService())->session($this->createAdmin(), null);
        $this->assertError($this->api('GET', '/api/auth/me', [], $admin['token']), 401, 'UNAUTHENTICATED');
        $login = $this->api('POST', '/api/auth/login', ['email' => 'admin@example.test', 'password' => 'AdminPass1']);
        $this->assertFalse($login[1]['success']);
        $this->assertContains($login[0], [401, 403]);

        $u = $this->register('client');
        Database::run("UPDATE users SET status = 'suspended' WHERE id = ?", [$u['id']]);
        $r = $this->api('GET', '/api/auth/me', [], $u['token']);
        $this->assertContains($r[0], [401, 403]);
        $this->assertFalse($r[1]['success']);
    }

    public function testRoleAuthorisation(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $this->assertError($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $prov['token']), 403, 'FORBIDDEN');
        $this->assertError($this->api('PUT', '/api/provider/skills', ['skill_ids' => $skills], $client['token']), 403, 'FORBIDDEN');
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $this->assertError($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $prov['token']), 403, 'FORBIDDEN');
    }

    public function testObjectLevelAuthorisationHidesOtherUsersData(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $owner = $this->register('client');
        $other = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $otherProv = $this->provider($cat, [$skills[0]]);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $owner['token']), 201)['request']['id'];

        $this->assertError($this->api('GET', "/api/requests/$rid", [], $other['token']), 404, 'NOT_FOUND');
        $this->assertError($this->api('GET', "/api/requests/$rid/matches", [], $other['token']), 404, 'NOT_FOUND');
        $this->assertError($this->api('PUT', "/api/requests/$rid", ['title' => 'Hijacked title'], $other['token']), 404, 'NOT_FOUND');
        $this->assertError($this->api('POST', "/api/requests/$rid/cancel", [], $other['token']), 404, 'NOT_FOUND');
        $this->assertError($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $other['token']), 404, 'NOT_FOUND');

        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $owner['token']), 201);
        $this->assertError($this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $otherProv['token']), 404, 'NOT_FOUND');
        $this->ok($this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $prov['token']));

        $cid = $this->ok($this->api('GET', '/api/conversations', [], $owner['token']))[0]['id'];
        $this->assertError($this->api('GET', "/api/conversations/$cid/messages", [], $other['token']), 404, 'NOT_FOUND');
        $this->assertError($this->api('POST', "/api/conversations/$cid/messages", ['body' => 'intruder'], $otherProv['token']), 404, 'NOT_FOUND');
        $this->assertError($this->api('GET', "/api/tasks/$rid", [], $other['token']), 404, 'NOT_FOUND');
        $this->assertError($this->api('POST', "/api/tasks/$rid/approve", [], $other['token']), 404, 'NOT_FOUND');
        $this->assertSame([], $this->ok($this->api('GET', '/api/payments', [], $other['token'])));
        $payId = $this->ok($this->api('GET', '/api/payments', [], $owner['token']))[0]['id'];
        $this->assertError($this->api('GET', "/api/payments/$payId", [], $other['token']), 404, 'NOT_FOUND');

        // the provider cannot approve their own work (client-only action)
        $this->assertError($this->api('POST', "/api/tasks/$rid/approve", [], $prov['token']), 403, 'FORBIDDEN');
        // client cannot submit work
        $this->assertError($this->api('POST', "/api/tasks/$rid/submit", ['note' => 'x'], $owner['token'], [], ['files' => $this->fakeUpload('a.txt', 'hello', 'text/plain')]), 403, 'FORBIDDEN');
    }

    public function testClientSuppliedIdentityAndStatusFieldsAreIgnored(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $victim = $this->register('client');
        $payload = $this->requestPayload($cat, $skills, ['client_id' => $victim['id'], 'status' => 'COMPLETED']);
        $d = $this->ok($this->api('POST', '/api/requests', $payload, $client['token']), 201);
        $this->assertSame($client['id'], $d['request']['client']['id']);
        $this->assertSame('OPEN', $d['request']['status']);
        $this->ok($this->api('PUT', '/api/profile', ['role' => 'admin', 'status' => 'active', 'full_name' => 'Renamed User'], $client['token']));
        $this->assertSame('client', Database::value('SELECT role FROM users WHERE id = ?', [$client['id']]));
    }

    public function testRequestValidation(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $bad = [
            ['deadline' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600)],
            ['compensation' => '-5'],
            ['compensation' => '0'],
            ['compensation' => 'abc'],
            ['title' => ''],
            ['title' => str_repeat('x', 300)],
            ['category_id' => 999999],
            ['deadline' => 'not-a-date'],
        ];
        foreach ($bad as $over) {
            $this->assertError($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills, $over), $client['token']), 422, 'VALIDATION_FAILED');
        }
        // SQL-injection style input is just data
        $d = $this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills, ['title' => "x'); DROP TABLE users;--"]), $client['token']), 201);
        $this->assertSame("x'); DROP TABLE users;--", $d['request']['title']);
        $this->assertGreaterThan(0, (int)Database::value('SELECT COUNT(*) FROM users'));
    }

    public function testRequestCannotBeEditedOnceNotOpen(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $this->ok($this->api('PUT', "/api/requests/$rid", ['title' => 'Renamed request'], $client['token']));
        $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 201);
        $this->assertError($this->api('PUT', "/api/requests/$rid", ['title' => 'Too late now'], $client['token']), 409, 'INVALID_STATE');
    }

    public function testStateMachineRejectsIllegalTransitions(): void
    {
        $this->assertTrue(TaskStateMachine::canTransition('OPEN', 'PENDING_ACCEPTANCE'));
        $this->assertTrue(TaskStateMachine::canTransition('SUBMITTED', 'UNDER_REVIEW'));
        foreach ([['COMPLETED', 'OPEN'], ['CANCELLED', 'IN_PROGRESS'], ['OPEN', 'COMPLETED'], ['IN_PROGRESS', 'COMPLETED'], ['EXPIRED', 'OPEN'], ['OPEN', 'SUBMITTED']] as [$a, $b]) {
            $this->assertFalse(TaskStateMachine::canTransition($a, $b), "$a -> $b must be illegal");
        }
        $this->expectException(ApiException::class);
        TaskStateMachine::assertTransition('COMPLETED', 'OPEN');
    }

    public function testApproveBeforeSubmissionAndSubmitWithoutFilesAreRejected(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 201);
        $this->ok($this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $prov['token']));
        $r = $this->api('POST', "/api/tasks/$rid/approve", [], $client['token']);
        $this->assertSame(409, $r[0]);
        $this->assertSame('COMPLETED' === ($this->ok($this->api('GET', "/api/tasks/$rid", [], $client['token']))['status']), false);
        $r = $this->api('POST', "/api/tasks/$rid/submit", ['note' => 'nothing'], $prov['token']);
        $this->assertContains($r[0], [409, 422]);
        $this->assertSame('PENDING', Database::value('SELECT state FROM simulated_payments LIMIT 1'));
    }

    public function testDuplicateAssignmentAndPaymentAreImpossibleAtDatabaseLevel(): void
    {
        [$client, $prov, $rid, $assignmentId] = $this->assigned();
        $paymentCount = (int)Database::value('SELECT COUNT(*) FROM simulated_payments');
        $this->assertSame(1, $paymentCount);
        try {
            Database::run('INSERT INTO service_assignments (request_id, invitation_id, client_id, provider_id) SELECT request_id, invitation_id, client_id, provider_id FROM service_assignments WHERE id = ?', [$assignmentId]);
            $this->fail('A second assignment for the same request must be rejected by the database.');
        } catch (\PDOException $e) {
            $this->assertSame('23000', (string)$e->getCode());
        }
        $this->expectException(\PDOException::class);
        PaymentService::createPending($rid, $assignmentId, $client['id'], $prov['id'], '500.00');
    }

    public function testSecondAcceptanceOfTheSameInvitationFails(): void
    {
        [, $prov] = $this->assigned();
        $invId = (int)Database::value('SELECT id FROM service_invitations LIMIT 1');
        $this->assertError($this->api('POST', "/api/invitations/$invId/accept", [], $prov['token']), 409, 'INVITATION_NOT_PENDING');
        $this->assertSame(1, (int)Database::value('SELECT COUNT(*) FROM service_assignments'));
    }

    public function testExclusiveTaskConflictBlocksSecondAssignmentToSameProvider(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $c1 = $this->register('client');
        $c2 = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $deadline = gmdate('Y-m-d\TH:i:s\Z', time() + 3 * 86400);
        $r1 = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills, ['requires_exclusive' => true, 'deadline' => $deadline]), $c1['token']), 201)['request']['id'];
        $r2 = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills, ['deadline' => $deadline, 'title' => 'Another job']), $c2['token']), 201)['request']['id'];
        $inv1 = $this->ok($this->api('POST', "/api/requests/$r1/invite", ['provider_id' => $prov['id']], $c1['token']), 201);
        // while r1 is pending the second request may still invite, but only one can win
        $inv2 = $this->api('POST', "/api/requests/$r2/invite", ['provider_id' => $prov['id']], $c2['token']);
        $this->ok($this->api('POST', "/api/invitations/{$inv1['id']}/accept", [], $prov['token']));
        if ($inv2[0] === 201) {
            $this->assertError($this->api('POST', "/api/invitations/{$inv2[1]['data']['id']}/accept", [], $prov['token']), 409, 'ASSIGNMENT_CONFLICT');
        } else {
            $this->assertSame(409, $inv2[0]);
        }
        $this->assertSame(1, (int)Database::value('SELECT COUNT(*) FROM service_assignments'));
    }

    public function testTransactionRollsBackOnFailure(): void
    {
        $before = (int)Database::value('SELECT COUNT(*) FROM users');
        try {
            Database::transaction(function (): void {
                Database::run("INSERT INTO users (email, password_hash, full_name, role, status) VALUES ('rb@example.test', 'x', 'Rollback', 'client', 'active')");
                throw new \RuntimeException('boom');
            });
            $this->fail('exception expected');
        } catch (\RuntimeException) {
        }
        $this->assertSame($before, (int)Database::value('SELECT COUNT(*) FROM users'));
    }

    public function testAcceptRollsBackEverythingWhenPaymentCreationFails(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 201);
        // force a failure late inside the accept transaction: the payment table is temporarily unavailable
        Database::pdo()->exec('RENAME TABLE simulated_payments TO simulated_payments_hidden');
        try {
            $r = $this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $prov['token']);
            $this->assertSame(500, $r[0]);
        } finally {
            Database::pdo()->exec('RENAME TABLE simulated_payments_hidden TO simulated_payments');
        }
        $this->assertSame(0, (int)Database::value('SELECT COUNT(*) FROM service_assignments'));
        $this->assertSame(0, (int)Database::value('SELECT COUNT(*) FROM conversations'));
        $this->assertSame('PENDING', Database::value('SELECT status FROM service_invitations WHERE id = ?', [$inv['id']]));
        $this->assertSame('PENDING_ACCEPTANCE', Database::value('SELECT status FROM service_requests WHERE id = ?', [$rid]));
    }

    public function testWeightChangeCreatesNewVersionAndKeepsHistoricalResultsImmutable(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $before = Database::one('SELECT * FROM matching_results WHERE request_id = ?', [$rid]);
        $detailBefore = Database::one('SELECT final_score, explanation_json FROM matching_result_details WHERE result_id = ?', [$before['id']]);

        $admin = $this->createAdmin();
        $svc = new MatchingService();
        $cfg = $svc->updateWeights($admin, ['category' => 10, 'skills' => 20, 'availability' => 10, 'budget' => 60], 'budget focus');
        $this->assertSame(2, (int)$cfg['version']);
        $this->assertSame(1, (int)Database::value('SELECT COUNT(*) FROM matching_configurations WHERE is_active = 1'));
        $this->assertSame(2, (int)Database::value('SELECT COUNT(*) FROM matching_configurations'));

        $this->assertSame($before, Database::one('SELECT * FROM matching_results WHERE id = ?', [$before['id']]));
        $this->assertSame($detailBefore, Database::one('SELECT final_score, explanation_json FROM matching_result_details WHERE result_id = ?', [$before['id']]));

        $this->ok($this->api('POST', "/api/requests/$rid/matches", [], $client['token']));
        $latest = Database::one('SELECT * FROM matching_results WHERE request_id = ? ORDER BY id DESC LIMIT 1', [$rid]);
        $this->assertNotSame((int)$before['id'], (int)$latest['id']);
        $this->assertGreaterThan(0, (int)Database::value("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%WEIGHT%' OR action LIKE '%MATCHING_CONFIG%'"));

        $this->expectException(ApiException::class);
        $svc->updateWeights($admin, ['category' => 50, 'skills' => 50, 'availability' => 10, 'budget' => 10], null);
    }

    public function testForeignKeysAreEnforced(): void
    {
        $this->expectException(\PDOException::class);
        Database::run("INSERT INTO service_requests (client_id, category_id, title, description, deadline, compensation, status) VALUES (999999, 1, 't', 'd', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY), 10, 'OPEN')");
    }

    public function testSchemaContainsAllRequiredTables(): void
    {
        $required = ['users', 'user_profiles', 'service_categories', 'skills', 'category_skills', 'provider_categories', 'provider_skills', 'provider_availability',
            'service_requests', 'service_request_skills', 'service_request_attachments', 'matching_configurations', 'matching_results', 'matching_result_details',
            'service_invitations', 'service_assignments', 'conversations', 'messages', 'task_submissions', 'submission_files', 'task_revisions', 'service_disputes',
            'simulated_payments', 'notifications', 'activity_logs', 'mobile_auth_tokens'];
        $have = Database::pdo()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($required as $t) {
            $this->assertContains($t, $have, "Missing table $t");
        }
    }

    public function testRateLimitingOnLogin(): void
    {
        \TaskBuddy\Support\Env::set('RATE_LIMIT_AUTH_MAX', '3');
        try {
            $u = $this->register('client');
            $last = null;
            for ($i = 0; $i < 6; $i++) {
                $last = $this->api('POST', '/api/auth/login', ['email' => $u['email'], 'password' => 'wrong-Pass1']);
            }
            $this->assertError($last, 429, 'RATE_LIMITED');
        } finally {
            \TaskBuddy\Support\Env::set('RATE_LIMIT_AUTH_MAX', '1000');
        }
    }

    // ---- helpers

    private function createAdmin(): int
    {
        return Database::insert("INSERT INTO users (email, password_hash, full_name, role, status) VALUES ('admin@example.test', ?, 'Admin', 'admin', 'active')", [password_hash('AdminPass1', PASSWORD_DEFAULT)]);
    }

    /** @return array{0:array,1:array,2:int,3:int} */
    private function assigned(): array
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 201);
        $this->ok($this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $prov['token']));
        return [$client, $prov, $rid, (int)Database::value('SELECT id FROM service_assignments WHERE request_id = ?', [$rid])];
    }
}
