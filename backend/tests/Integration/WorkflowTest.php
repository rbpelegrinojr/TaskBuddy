<?php
declare(strict_types=1);

namespace TaskBuddy\Tests\Integration;

use TaskBuddy\Support\Database;

final class WorkflowTest extends ApiTestCase
{
    public function testFullEndToEndWorkflow(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 3);
        $client = $this->register('client');
        $providerA = $this->provider($cat, $skills, '100.00', 'Provider A');
        $providerB = $this->provider($cat, [$skills[0]], '900.00', 'Provider B (over budget)');

        // 1. create request -> matching runs automatically
        $created = $this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201);
        $rid = (int)$created['request']['id'];
        $this->assertSame('OPEN', $created['request']['status']);
        $this->assertFalse($created['matching']['no_match']);

        // 2. recommendations
        $m = $this->api('GET', "/api/requests/$rid/matches", [], $client['token']);
        $items = $this->ok($m);
        $this->assertCount(2, $items);
        $this->assertSame($providerA['id'], $items[0]['provider']['id']);
        $this->assertSame(100.0, $items[0]['final_score']);
        $this->assertSame('OVER_BUDGET', $items[1]['budget_status']);
        $this->assertSame(1, $m[1]['meta']['weights']['version'] ?? 1);

        // provider sees the opportunity
        $opps = $this->ok($this->api('GET', '/api/requests', [], $providerA['token']));
        $this->assertSame($rid, $opps[0]['id']);

        // 3. invite
        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $providerA['id'], 'message' => 'Hello'], $client['token']), 201);
        $this->assertSame('PENDING', $inv['status']);
        $this->assertSame('PENDING_ACCEPTANCE', $this->ok($this->api('GET', "/api/requests/$rid", [], $client['token']))['status']);
        $this->assertError($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $providerB['id']], $client['token']), 409, 'INVITATION_PENDING');

        // 4. accept
        $acc = $this->ok($this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $providerA['token']));
        $this->assertSame('ACCEPTED', $acc['status']);
        $task = $this->ok($this->api('GET', "/api/tasks/$rid", [], $client['token']));
        $this->assertSame('IN_PROGRESS', $task['status']);
        $this->assertSame('PENDING', $task['payment']['state']);
        $this->assertTrue($task['payment']['is_simulated']);

        // 5. messaging
        $convs = $this->ok($this->api('GET', '/api/conversations', [], $client['token']));
        $cid = $convs[0]['id'];
        $msg = $this->ok($this->api('POST', "/api/conversations/$cid/messages", ['body' => 'When can you start?'], $client['token']), 201);
        $this->assertSame('When can you start?', $msg['body']);
        $list = $this->api('GET', "/api/conversations/$cid/messages", [], $providerA['token']);
        $this->assertCount(1, $this->ok($list));
        $this->ok($this->api('POST', "/api/conversations/$cid/read", [], $providerA['token']));

        // 6. submit work
        $files = ['files' => $this->fakeUpload('result.png', $this->pngBytes(), 'image/png')];
        $sub = $this->ok($this->api('POST', "/api/tasks/$rid/submit", ['note' => 'Done'], $providerA['token'], [], $files), 201);
        $this->assertSame('SUBMITTED', $sub['task']['status']);

        // download is permitted to participants only
        $fileId = $sub['task']['submissions'][0]['files'][0]['id'];
        [$st] = $this->raw('GET', "/api/submission-files/$fileId/download", $client['token']);
        $this->assertSame(200, $st);
        $outsider = $this->register('client');
        [$st] = $this->raw('GET', "/api/submission-files/$fileId/download", $outsider['token']);
        $this->assertSame(404, $st);

        // 7. revision loop
        $this->ok($this->api('POST', "/api/tasks/$rid/revision", ['reason' => 'Please add detail'], $client['token']));
        $this->assertSame('IN_PROGRESS', $this->ok($this->api('GET', "/api/tasks/$rid", [], $client['token']))['status']);
        $files = ['files' => $this->fakeUpload('v2.png', $this->pngBytes(), 'image/png')];
        $this->ok($this->api('POST', "/api/tasks/$rid/submit", ['note' => 'v2'], $providerA['token'], [], $files), 201);

        // 8. approve -> payment completed
        $done = $this->ok($this->api('POST', "/api/tasks/$rid/approve", [], $client['token']));
        $this->assertSame('COMPLETED', $done['status']);
        $pay = $this->ok($this->api('GET', '/api/payments', [], $providerA['token']));
        $this->assertSame('COMPLETED', $pay[0]['state']);
        $this->assertSame('500.00', $pay[0]['amount']);
        $this->assertSame('SIMULATED / TEST TRANSACTION', $pay[0]['label']);

        // completed task closes the chat
        $this->assertError($this->api('POST', "/api/conversations/$cid/messages", ['body' => 'late'], $client['token']), 409, 'CONVERSATION_CLOSED');

        // 9. notifications were produced for both parties
        $this->assertGreaterThan(0, count($this->ok($this->api('GET', '/api/notifications', [], $providerA['token']))));
        $this->assertGreaterThan(0, count($this->ok($this->api('GET', '/api/notifications', [], $client['token']))));

        // audit trail written
        $this->assertGreaterThan(0, (int)Database::value('SELECT COUNT(*) FROM activity_logs'));
    }

    public function testDeclineReturnsRequestToOpenAndProviderCannotBeReInvited(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 201);
        $this->ok($this->api('POST', "/api/invitations/{$inv['id']}/decline", [], $prov['token']));
        $this->assertSame('OPEN', $this->ok($this->api('GET', "/api/requests/$rid", [], $client['token']))['status']);
        $this->assertError($this->api('POST', "/api/invitations/{$inv['id']}/decline", [], $prov['token']), 409, 'INVITATION_NOT_PENDING');
        $this->assertError($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 409, 'PROVIDER_ALREADY_DECLINED');
    }

    public function testNoMatchesYieldsEmptyListWithMessage(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $created = $this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201);
        $this->assertTrue($created['matching']['no_match']);
        $r = $this->api('GET', "/api/requests/{$created['request']['id']}/matches", [], $client['token']);
        $this->assertSame([], $this->ok($r));
        $this->assertTrue($r[1]['meta']['no_match']);
        $this->assertNotEmpty($r[1]['meta']['message']);
    }

    public function testInviteRejectedForProviderNotInRecommendations(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $stranger = $this->provider($cat, $skills);
        $incomplete = $this->register('provider');
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $this->assertError($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $incomplete['id']], $client['token']), 422, 'PROVIDER_NOT_RECOMMENDED');
        $this->assertNotNull($stranger['id']);
    }

    public function testCancelOpenRequestAndExpiredInvitationReopens(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 201);
        Database::run('UPDATE service_invitations SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id = ?', [$inv['id']]);
        $this->assertError($this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $prov['token']), 409, 'INVITATION_EXPIRED');
        $this->assertSame('OPEN', $this->ok($this->api('GET', "/api/requests/$rid", [], $client['token']))['status']);
        $this->ok($this->api('POST', "/api/requests/$rid/cancel", [], $client['token']));
        $this->assertError($this->api('POST', "/api/requests/$rid/cancel", [], $client['token']), 409, 'INVALID_TRANSITION');
    }

    public function testDisputePutsPaymentOnHold(): void
    {
        [$client, $prov, $rid] = $this->assignedTask();
        $this->assertError($this->api('POST', "/api/tasks/$rid/dispute", ['reason' => 'short'], $client['token']), 422, 'VALIDATION_FAILED');
        $this->ok($this->api('POST', "/api/tasks/$rid/dispute", ['reason' => 'Provider is not responding at all.'], $client['token']));
        $this->assertSame('DISPUTED', $this->ok($this->api('GET', "/api/tasks/$rid", [], $prov['token']))['status']);
        $this->assertSame('ON_HOLD', $this->ok($this->api('GET', '/api/payments', [], $client['token']))[0]['state']);
        $this->assertError($this->api('POST', "/api/tasks/$rid/dispute", ['reason' => 'Another dispute attempt here.'], $prov['token']), 409, 'INVALID_TRANSITION');
    }

    /** @return array{0:array,1:array,2:int} */
    protected function assignedTask(): array
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
}
