<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\Database;

/** Writes immutable activity_logs rows. Never include secrets in $details. */
final class AuditLogger
{
    public static string $ip = '';

    /** @param array<string,mixed> $details */
    public static function log(?int $actorId, ?string $actorRole, string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        Database::insert(
            'INSERT INTO activity_logs (actor_id, actor_role, action, entity_type, entity_id, details_json, ip_address) VALUES (?,?,?,?,?,?,?)',
            [$actorId, $actorRole, $action, $entityType, $entityId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, self::$ip !== '' ? self::$ip : null]
        );
    }
}
