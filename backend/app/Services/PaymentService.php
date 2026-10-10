<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;

/**
 * SIMULATED / TEST TRANSACTION records only. No gateway, card, wallet or bank integration exists.
 *
 * Deterministic rules:
 *   assignment accepted  -> record created as PENDING (exactly one per request; unique constraints)
 *   client approves      -> COMPLETED
 *   dispute opened       -> ON_HOLD            (only from PENDING)
 *   dispute resolved     -> COMPLETE_TASK: COMPLETED | CANCEL_TASK: CANCELLED | RESUME_TASK: PENDING  (only from ON_HOLD)
 */
final class PaymentService
{
    public const LABEL = 'SIMULATED / TEST TRANSACTION';

    private const TRANSITIONS = [
        'PENDING' => ['COMPLETED', 'ON_HOLD', 'CANCELLED'],
        'ON_HOLD' => ['COMPLETED', 'CANCELLED', 'PENDING'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    /** Create the PENDING record for a new assignment. Must run inside the accept transaction. */
    public static function createPending(int $requestId, int $assignmentId, int $clientId, int $providerId, string $amount): int
    {
        $ref = 'SIM-' . Clock::now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        return Database::insert(
            'INSERT INTO simulated_payments (reference, request_id, assignment_id, client_id, provider_id, amount, state) VALUES (?,?,?,?,?,?,?)',
            [$ref, $requestId, $assignmentId, $clientId, $providerId, $amount, 'PENDING']
        );
    }

    public static function transition(int $requestId, string $to, ?int $actorId, ?string $actorRole): void
    {
        $p = Database::one('SELECT id, state FROM simulated_payments WHERE request_id = ? FOR UPDATE', [$requestId]);
        if ($p === null) {
            throw ApiException::notFound('Simulated payment');
        }
        if (!in_array($to, self::TRANSITIONS[$p['state']] ?? [], true)) {
            throw ApiException::conflict('INVALID_PAYMENT_TRANSITION', "Simulated payment cannot move from {$p['state']} to $to.");
        }
        Database::run('UPDATE simulated_payments SET state = ?, completed_at = ? WHERE id = ?', [$to, $to === 'COMPLETED' ? Clock::sql() : null, $p['id']]);
        AuditLogger::log($actorId, $actorRole, 'PAYMENT_STATE_CHANGED', 'simulated_payment', (int)$p['id'], ['from' => $p['state'], 'to' => $to, 'simulated' => true]);
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    public static function format(array $r): array
    {
        return [
            'id' => (int)$r['id'], 'reference' => $r['reference'], 'label' => self::LABEL, 'is_simulated' => true,
            'task_id' => (int)$r['request_id'], 'task_title' => $r['title'] ?? null,
            'client' => ['id' => (int)$r['client_id'], 'full_name' => $r['client_name'] ?? null],
            'provider' => ['id' => (int)$r['provider_id'], 'full_name' => $r['provider_name'] ?? null],
            'amount' => $r['amount'], 'currency_note' => 'Simulated amount; no real funds are moved.',
            'state' => $r['state'], 'created_at' => Clock::iso($r['created_at']), 'completed_at' => Clock::iso($r['completed_at']),
        ];
    }

    private const SELECT = 'SELECT p.*, r.title, c.full_name AS client_name, pr.full_name AS provider_name
        FROM simulated_payments p JOIN service_requests r ON r.id = p.request_id
        JOIN users c ON c.id = p.client_id JOIN users pr ON pr.id = p.provider_id';

    /** @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>} */
    public function list(int $userId, string $role, int $page, int $perPage, ?string $state): array
    {
        $col = $role === 'provider' ? 'p.provider_id' : 'p.client_id';
        $where = "$col = ?" . ($state ? ' AND p.state = ?' : '');
        $params = $state ? [$userId, $state] : [$userId];
        $total = (int)Database::value("SELECT COUNT(*) FROM simulated_payments p WHERE $where", $params);
        $rows = Database::all(self::SELECT . " WHERE $where ORDER BY p.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
        $sums = Database::all("SELECT state, COALESCE(SUM(amount),0) AS total, COUNT(*) AS n FROM simulated_payments p WHERE $col = ? GROUP BY state", [$userId]);
        $summary = ['label' => self::LABEL, 'is_simulated' => true, 'pending_total' => '0.00', 'completed_total' => '0.00', 'on_hold_total' => '0.00', 'cancelled_total' => '0.00'];
        foreach ($sums as $s) {
            $summary[strtolower($s['state']) . '_total'] = number_format((float)$s['total'], 2, '.', '');
        }
        return ['items' => array_map([self::class, 'format'], $rows),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'summary' => $summary]];
    }

    /** @return array<string,mixed> */
    public function get(int $userId, int $id): array
    {
        $r = Database::one(self::SELECT . ' WHERE p.id = ?', [$id]);
        if ($r === null || ((int)$r['client_id'] !== $userId && (int)$r['provider_id'] !== $userId)) {
            throw ApiException::notFound('Simulated payment');
        }
        return self::format($r);
    }
}
