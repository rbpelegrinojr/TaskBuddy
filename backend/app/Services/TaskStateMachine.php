<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

/** Validated state machine for service_requests.status. */
final class TaskStateMachine
{
    public const OPEN = 'OPEN';
    public const PENDING_ACCEPTANCE = 'PENDING_ACCEPTANCE';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const SUBMITTED = 'SUBMITTED';
    public const UNDER_REVIEW = 'UNDER_REVIEW';
    public const REVISION_REQUESTED = 'REVISION_REQUESTED';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';
    public const EXPIRED = 'EXPIRED';
    public const DISPUTED = 'DISPUTED';

    /** @var array<string,string[]> */
    public const TRANSITIONS = [
        self::OPEN => [self::PENDING_ACCEPTANCE, self::CANCELLED, self::EXPIRED],
        self::PENDING_ACCEPTANCE => [self::IN_PROGRESS, self::OPEN, self::CANCELLED],
        self::IN_PROGRESS => [self::SUBMITTED, self::DISPUTED],
        self::SUBMITTED => [self::UNDER_REVIEW, self::DISPUTED],
        self::UNDER_REVIEW => [self::COMPLETED, self::REVISION_REQUESTED, self::DISPUTED],
        self::REVISION_REQUESTED => [self::IN_PROGRESS, self::DISPUTED],
        // Only an administrator dispute resolution leaves DISPUTED.
        self::DISPUTED => [self::COMPLETED, self::CANCELLED, self::IN_PROGRESS],
        self::COMPLETED => [],
        self::CANCELLED => [],
        self::EXPIRED => [],
    ];

    /** Statuses in which a dispute can be opened. */
    public const DISPUTABLE = [self::IN_PROGRESS, self::SUBMITTED, self::UNDER_REVIEW, self::REVISION_REQUESTED];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw ApiException::conflict('INVALID_TRANSITION', "A request in status $from cannot move to $to.");
        }
    }

    /**
     * Lock the request row, validate and apply a transition, and log it. Must run inside a transaction.
     * @param array<string,mixed> $details
     */
    public static function apply(int $requestId, string $to, ?int $actorId, ?string $actorRole, array $details = []): string
    {
        $from = Database::value('SELECT status FROM service_requests WHERE id = ? FOR UPDATE', [$requestId]);
        if ($from === null) {
            throw ApiException::notFound('Request');
        }
        self::assertTransition((string)$from, $to);
        Database::run('UPDATE service_requests SET status = ? WHERE id = ?', [$to, $requestId]);
        AuditLogger::log($actorId, $actorRole, 'STATUS_CHANGED', 'service_request', $requestId, ['from' => $from, 'to' => $to] + $details);
        return (string)$from;
    }
}
