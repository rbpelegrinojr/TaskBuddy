<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;

final class NotificationService
{
    public const TYPES = [
        'INVITATION_RECEIVED', 'INVITATION_ACCEPTED', 'INVITATION_DECLINED', 'INVITATION_EXPIRED', 'TASK_ASSIGNED', 'NEW_MESSAGE',
        'WORK_SUBMITTED', 'REVISION_REQUESTED', 'TASK_COMPLETED', 'DISPUTE_CREATED', 'DISPUTE_RESOLVED',
        'PAYMENT_RECORDED', 'ANNOUNCEMENT',
    ];

    public static function notify(int $userId, string $type, string $title, string $body, ?int $requestId = null): void
    {
        Database::insert(
            'INSERT INTO notifications (user_id, type, title, body, request_id) VALUES (?,?,?,?,?)',
            [$userId, $type, mb_substr($title, 0, 150), mb_substr($body, 0, 500), $requestId]
        );
    }

    /** @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>} */
    public function list(int $userId, int $page, int $perPage, bool $unreadOnly): array
    {
        $where = 'user_id = ?' . ($unreadOnly ? ' AND read_at IS NULL' : '');
        $total = (int)Database::value("SELECT COUNT(*) FROM notifications WHERE $where", [$userId]);
        $rows = Database::all(
            "SELECT id, type, title, body, request_id, read_at, created_at FROM notifications WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            [$userId]
        );
        $items = array_map(static fn(array $r) => [
            'id' => (int)$r['id'], 'type' => $r['type'], 'title' => $r['title'], 'body' => $r['body'],
            'request_id' => $r['request_id'] === null ? null : (int)$r['request_id'],
            'is_read' => $r['read_at'] !== null, 'created_at' => Clock::iso($r['created_at']),
        ], $rows);
        $unread = (int)Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
        return ['items' => $items, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'unread_count' => $unread]];
    }

    public function markRead(int $userId, int $id): void
    {
        $owner = Database::value('SELECT user_id FROM notifications WHERE id = ?', [$id]);
        if ($owner === null || (int)$owner !== $userId) {
            throw ApiException::notFound('Notification');
        }
        Database::run('UPDATE notifications SET read_at = COALESCE(read_at, ?) WHERE id = ?', [Clock::sql(), $id]);
    }

    public function markAllRead(int $userId): int
    {
        return Database::run('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [Clock::sql(), $userId]);
    }
}
