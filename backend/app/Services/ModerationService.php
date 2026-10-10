<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Validator;

final class ModerationService
{
    public function block(int $clientId, int $providerId): void
    {
        $this->assertProvider($providerId);
        Database::run('INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?,?)', [$clientId, $providerId]);
        AuditLogger::log($clientId, 'client', 'PROVIDER_BLOCKED', 'user', $providerId);
    }

    public function unblock(int $clientId, int $providerId): void
    {
        Database::run('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?', [$clientId, $providerId]);
        AuditLogger::log($clientId, 'client', 'PROVIDER_UNBLOCKED', 'user', $providerId);
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function report(int $reporterId, array $in): array
    {
        $v = new Validator($in);
        $reported = $v->int('user_id', true, 1);
        $reason = $v->string('reason', true, 10, 1000);
        $v->fail();
        if ($reported === $reporterId || Database::value("SELECT id FROM users WHERE id = ? AND role <> 'admin'", [$reported]) === null) {
            throw ApiException::validation(['user_id' => 'Unknown user.']);
        }
        $id = Database::insert('INSERT INTO user_reports (reporter_id, reported_id, reason) VALUES (?,?,?)', [$reporterId, $reported, $reason]);
        AuditLogger::log($reporterId, null, 'USER_REPORTED', 'user', $reported, ['report_id' => $id]);
        return ['id' => $id, 'status' => 'OPEN'];
    }

    private function assertProvider(int $id): void
    {
        if (Database::value("SELECT id FROM users WHERE id = ? AND role = 'provider'", [$id]) === null) {
            throw ApiException::notFound('Provider');
        }
    }
}
