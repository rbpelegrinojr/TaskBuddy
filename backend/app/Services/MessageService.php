<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Validator;

final class MessageService
{
    /** @return array<int,array<string,mixed>> */
    public function conversations(int $userId): array
    {
        $rows = Database::all(
            'SELECT c.id, c.client_id, c.provider_id, r.id AS task_id, r.title, r.status, cu.full_name AS client_name, pu.full_name AS provider_name
             FROM conversations c JOIN service_assignments a ON a.id = c.assignment_id JOIN service_requests r ON r.id = a.request_id
             JOIN users cu ON cu.id = c.client_id JOIN users pu ON pu.id = c.provider_id
             WHERE c.client_id = ? OR c.provider_id = ? ORDER BY c.id DESC',
            [$userId, $userId]
        );
        return array_map(function (array $c) use ($userId): array {
            $isClient = (int)$c['client_id'] === $userId;
            $last = Database::one('SELECT id, sender_id, body, created_at FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 1', [$c['id']]);
            $unread = (int)Database::value('SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL', [$c['id'], $userId]);
            return [
                'id' => (int)$c['id'], 'task_id' => (int)$c['task_id'], 'task_title' => $c['title'], 'task_status' => $c['status'],
                'other_party' => ['id' => (int)($isClient ? $c['provider_id'] : $c['client_id']), 'full_name' => $isClient ? $c['provider_name'] : $c['client_name']],
                'last_message' => $last === null ? null : ['id' => (int)$last['id'], 'sender_id' => (int)$last['sender_id'], 'body' => $last['body'], 'created_at' => Clock::iso($last['created_at'])],
                'unread_count' => $unread,
            ];
        }, $rows);
    }

    /**
     * Without cursors: the latest $limit messages. before_id: older page. after_id: newer messages (polling).
     * Items are always returned oldest-first.
     * @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function messages(int $conversationId, int $userId, ?int $afterId, ?int $beforeId, int $limit): array
    {
        $this->conversation($conversationId, $userId);
        if ($afterId !== null) {
            $rows = Database::all("SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT $limit", [$conversationId, $afterId]);
        } else {
            $sql = 'SELECT * FROM messages WHERE conversation_id = ?' . ($beforeId !== null ? ' AND id < ?' : '') . " ORDER BY id DESC LIMIT $limit";
            $rows = array_reverse(Database::all($sql, $beforeId !== null ? [$conversationId, $beforeId] : [$conversationId]));
        }
        $oldest = $rows[0]['id'] ?? null;
        $hasMore = $oldest !== null && Database::value('SELECT 1 FROM messages WHERE conversation_id = ? AND id < ? LIMIT 1', [$conversationId, $oldest]) !== null;
        $items = array_map(static fn($m) => [
            'id' => (int)$m['id'], 'sender_id' => (int)$m['sender_id'], 'body' => $m['body'], 'is_read' => $m['read_at'] !== null,
            'read_at' => Clock::iso($m['read_at']), 'created_at' => Clock::iso($m['created_at']),
        ], $rows);
        return ['items' => $items, 'meta' => ['limit' => $limit, 'has_more_older' => $hasMore, 'latest_id' => $rows ? (int)end($rows)['id'] : null]];
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function send(int $conversationId, int $userId, array $in): array
    {
        $v = new Validator($in);
        $body = $v->string('body', true, 1, 2000);
        $v->fail();
        $c = $this->conversation($conversationId, $userId);
        if (in_array($c['status'], ['COMPLETED', 'CANCELLED', 'EXPIRED'], true)) {
            throw ApiException::conflict('CONVERSATION_CLOSED', 'This conversation is closed because the task has ended.');
        }
        $id = Database::insert('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)', [$conversationId, $userId, $body]);
        $other = (int)($userId === (int)$c['client_id'] ? $c['provider_id'] : $c['client_id']);
        $dupe = Database::value("SELECT 1 FROM notifications WHERE user_id = ? AND type = 'NEW_MESSAGE' AND request_id = ? AND read_at IS NULL", [$other, $c['task_id']]);
        if ($dupe === null) {
            NotificationService::notify($other, 'NEW_MESSAGE', 'New message', 'You have a new message about: ' . $c['title'], (int)$c['task_id']);
        }
        $m = Database::one('SELECT * FROM messages WHERE id = ?', [$id]);
        return ['id' => $id, 'sender_id' => $userId, 'body' => $m['body'], 'is_read' => false, 'read_at' => null, 'created_at' => Clock::iso($m['created_at'])];
    }

    public function markRead(int $conversationId, int $userId): int
    {
        $this->conversation($conversationId, $userId);
        return Database::run('UPDATE messages SET read_at = ? WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL', [Clock::sql(), $conversationId, $userId]);
    }

    /** Only the two participants may access a conversation (404 for everyone else). @return array<string,mixed> */
    private function conversation(int $id, int $userId): array
    {
        $c = Database::one('SELECT c.*, r.id AS task_id, r.title, r.status FROM conversations c JOIN service_assignments a ON a.id = c.assignment_id JOIN service_requests r ON r.id = a.request_id WHERE c.id = ?', [$id]);
        if ($c === null || ((int)$c['client_id'] !== $userId && (int)$c['provider_id'] !== $userId)) {
            throw ApiException::notFound('Conversation');
        }
        return $c;
    }
}
