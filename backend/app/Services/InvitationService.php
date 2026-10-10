<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use PDOException;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Env;
use TaskBuddy\Support\Validator;

final class InvitationService
{
    public function __construct(private readonly MatchingService $matching = new MatchingService())
    {
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function invite(int $clientId, int $requestId, array $in): array
    {
        $v = new Validator($in);
        $providerId = $v->int('provider_id', true, 1);
        $message = $v->string('message', false, 0, 1000);
        $hours = $v->int('expires_in_hours', false, 1, 720) ?? Env::int('INVITATION_TTL_HOURS', 72);
        $v->fail();
        $this->expireStale();

        $id = Database::transaction(function () use ($clientId, $requestId, $providerId, $message, $hours): int {
            $req = Database::one('SELECT * FROM service_requests WHERE id = ? FOR UPDATE', [$requestId]);
            if ($req === null || (int)$req['client_id'] !== $clientId) {
                throw ApiException::notFound('Request');
            }
            if ($req['status'] !== 'OPEN') {
                throw ApiException::conflict('INVALID_STATE', 'Providers can only be invited while the request is OPEN.');
            }
            $resultId = $this->matching->isRecommended($requestId, $providerId);
            if ($resultId === null) {
                throw new ApiException(422, 'PROVIDER_NOT_RECOMMENDED', 'Only providers in the latest matching result can be invited.');
            }
            $reasons = $this->matching->eligibilityFor($requestId, $providerId);
            if ($reasons) {
                throw new ApiException(409, 'PROVIDER_NOT_ELIGIBLE', 'This provider is no longer eligible for the request.', ['reasons' => $reasons]);
            }
            if (Database::value("SELECT 1 FROM service_invitations WHERE request_id = ? AND provider_id = ? AND status = 'DECLINED'", [$requestId, $providerId]) !== null) {
                throw ApiException::conflict('PROVIDER_ALREADY_DECLINED', 'This provider already declined this request.');
            }
            try {
                $expires = Clock::now()->modify("+$hours hours");
                $id = Database::insert(
                    'INSERT INTO service_invitations (request_id, provider_id, client_id, matching_result_id, message, status, pending_key, expires_at) VALUES (?,?,?,?,?,?,?,?)',
                    [$requestId, $providerId, $clientId, $resultId, $message, 'PENDING', $requestId, Clock::sql($expires)]
                );
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    throw ApiException::conflict('INVITATION_PENDING', 'A pending invitation already exists for this request.');
                }
                throw $e;
            }
            TaskStateMachine::apply($requestId, 'PENDING_ACCEPTANCE', $clientId, 'client', ['invitation_id' => $id]);
            AuditLogger::log($clientId, 'client', 'INVITATION_SENT', 'service_invitation', $id, ['request_id' => $requestId, 'provider_id' => $providerId]);
            NotificationService::notify($providerId, 'INVITATION_RECEIVED', 'New invitation', 'You were invited to: ' . $req['title'], $requestId);
            return $id;
        });
        return $this->get($id, $clientId, 'client');
    }

    /** @return array<string,mixed> */
    public function accept(int $providerId, int $invitationId): array
    {
        $this->expireStale();
        Database::transaction(function () use ($providerId, $invitationId): void {
            // Serialise all accept operations of one provider so overlapping commitments cannot race.
            Database::one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$providerId]);
            $inv = $this->lockOwnedByProvider($invitationId, $providerId);
            if ($inv['status'] !== 'PENDING') {
                throw ApiException::conflict('INVITATION_NOT_PENDING', 'This invitation is no longer pending (' . $inv['status'] . ').');
            }
            if ($inv['expires_at'] <= Clock::sql()) {
                throw ApiException::conflict('INVITATION_EXPIRED', 'This invitation has expired.');
            }
            $requestId = (int)$inv['request_id'];
            $req = Database::one('SELECT * FROM service_requests WHERE id = ? FOR UPDATE', [$requestId]);
            if ($req === null || $req['status'] !== 'PENDING_ACCEPTANCE') {
                throw ApiException::conflict('INVALID_STATE', 'The request is not awaiting acceptance.');
            }
            $reasons = $this->matching->eligibilityFor($requestId, $providerId);
            if ($reasons) {
                $code = in_array(MatchingEngine::R_CONFLICT, $reasons, true) ? 'ASSIGNMENT_CONFLICT' : 'PROVIDER_NOT_ELIGIBLE';
                throw new ApiException(409, $code, 'You cannot accept this task right now.', ['reasons' => $reasons]);
            }
            try {
                $assignmentId = Database::insert(
                    'INSERT INTO service_assignments (request_id, invitation_id, client_id, provider_id) VALUES (?,?,?,?)',
                    [$requestId, $invitationId, $req['client_id'], $providerId]
                );
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    throw ApiException::conflict('ALREADY_ASSIGNED', 'This request has already been assigned.');
                }
                throw $e;
            }
            Database::run('INSERT INTO conversations (assignment_id, client_id, provider_id) VALUES (?,?,?)', [$assignmentId, $req['client_id'], $providerId]);
            PaymentService::createPending($requestId, $assignmentId, (int)$req['client_id'], $providerId, $req['compensation']);
            Database::run("UPDATE service_invitations SET status='ACCEPTED', pending_key=NULL, responded_at=? WHERE id=?", [Clock::sql(), $invitationId]);
            TaskStateMachine::apply($requestId, 'IN_PROGRESS', $providerId, 'provider', ['assignment_id' => $assignmentId]);
            AuditLogger::log($providerId, 'provider', 'INVITATION_ACCEPTED', 'service_invitation', $invitationId, ['assignment_id' => $assignmentId]);
            NotificationService::notify((int)$req['client_id'], 'INVITATION_ACCEPTED', 'Invitation accepted', 'A provider accepted your invitation for: ' . $req['title'], $requestId);
            NotificationService::notify((int)$req['client_id'], 'TASK_ASSIGNED', 'Task assigned', 'Your task is now in progress: ' . $req['title'], $requestId);
            NotificationService::notify($providerId, 'TASK_ASSIGNED', 'Task assigned', 'You were assigned: ' . $req['title'], $requestId);
            NotificationService::notify((int)$req['client_id'], 'PAYMENT_RECORDED', 'Simulated payment recorded', 'A PENDING simulated payment was recorded (SIMULATED / TEST TRANSACTION).', $requestId);
            NotificationService::notify($providerId, 'PAYMENT_RECORDED', 'Simulated payment recorded', 'A PENDING simulated payment was recorded (SIMULATED / TEST TRANSACTION).', $requestId);
        });
        return $this->get($invitationId, $providerId, 'provider');
    }

    /** @return array<string,mixed> */
    public function decline(int $providerId, int $invitationId): array
    {
        $this->expireStale();
        Database::transaction(function () use ($providerId, $invitationId): void {
            $inv = $this->lockOwnedByProvider($invitationId, $providerId);
            if ($inv['status'] !== 'PENDING') {
                throw ApiException::conflict('INVITATION_NOT_PENDING', 'This invitation is no longer pending (' . $inv['status'] . ').');
            }
            $req = Database::one('SELECT id, client_id, title FROM service_requests WHERE id = ?', [$inv['request_id']]);
            Database::run("UPDATE service_invitations SET status='DECLINED', pending_key=NULL, responded_at=? WHERE id=?", [Clock::sql(), $invitationId]);
            TaskStateMachine::apply((int)$inv['request_id'], 'OPEN', $providerId, 'provider', ['reason' => 'invitation_declined']);
            AuditLogger::log($providerId, 'provider', 'INVITATION_DECLINED', 'service_invitation', $invitationId);
            NotificationService::notify((int)$req['client_id'], 'INVITATION_DECLINED', 'Invitation declined', 'A provider declined your invitation for: ' . $req['title'], (int)$req['id']);
        });
        return $this->get($invitationId, $providerId, 'provider');
    }

    /** @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>} */
    public function list(int $userId, string $role, int $page, int $perPage, ?string $status, ?int $requestId = null): array
    {
        $this->expireStale();
        $col = $role === 'provider' ? 'i.provider_id' : 'i.client_id';
        $where = "$col = ?";
        $params = [$userId];
        if ($status) {
            $where .= ' AND i.status = ?';
            $params[] = $status;
        }
        if ($requestId !== null && $role === 'client') {
            $where .= ' AND i.request_id = ?';
            $params[] = $requestId;
        }
        $total = (int)Database::value("SELECT COUNT(*) FROM service_invitations i WHERE $where", $params);
        $rows = Database::all("SELECT i.id FROM service_invitations i WHERE $where ORDER BY i.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
        $items = array_map(fn($r) => $this->get((int)$r['id'], $userId, $role), $rows);
        return ['items' => $items, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'has_more' => $page * $perPage < $total]];
    }

    /** @return array<string,mixed> */
    public function get(int $id, int $userId, string $role): array
    {
        $i = Database::one(
            'SELECT i.*, r.title, r.compensation, r.deadline, r.status AS request_status, cat.name AS category_name, cu.full_name AS client_name, pu.full_name AS provider_name
             FROM service_invitations i JOIN service_requests r ON r.id = i.request_id JOIN service_categories cat ON cat.id = r.category_id
             JOIN users cu ON cu.id = i.client_id JOIN users pu ON pu.id = i.provider_id WHERE i.id = ?',
            [$id]
        );
        $mine = $i !== null && ($role === 'provider' ? (int)$i['provider_id'] === $userId : (int)$i['client_id'] === $userId);
        if (!$mine) {
            throw ApiException::notFound('Invitation');
        }
        $assignment = Database::value('SELECT id FROM service_assignments WHERE invitation_id = ?', [$id]);
        return [
            'id' => (int)$i['id'], 'status' => $i['status'], 'message' => $i['message'],
            'expires_at' => Clock::iso($i['expires_at']), 'responded_at' => Clock::iso($i['responded_at']), 'created_at' => Clock::iso($i['created_at']),
            'request' => ['id' => (int)$i['request_id'], 'title' => $i['title'], 'compensation' => $i['compensation'], 'deadline' => Clock::iso($i['deadline']),
                'category' => $i['category_name'], 'status' => $i['request_status']],
            'client' => ['id' => (int)$i['client_id'], 'full_name' => $i['client_name']],
            'provider' => ['id' => (int)$i['provider_id'], 'full_name' => $i['provider_name']],
            'assignment_id' => $assignment === null ? null : (int)$assignment,
        ];
    }

    /**
     * Expire overdue pending invitations (request returns to OPEN) and OPEN requests past their deadline.
     * Called lazily by list endpoints and by bin/maintenance.php (cron). Returns the number of changed rows.
     */
    public function expireStale(): int
    {
        $n = 0;
        $due = Database::all("SELECT id FROM service_invitations WHERE status = 'PENDING' AND expires_at <= ?", [Clock::sql()]);
        foreach ($due as $d) {
            $n += Database::transaction(function () use ($d): int {
                $inv = Database::one("SELECT * FROM service_invitations WHERE id = ? AND status = 'PENDING' FOR UPDATE", [$d['id']]);
                if ($inv === null) {
                    return 0;
                }
                Database::run("UPDATE service_invitations SET status='EXPIRED', pending_key=NULL, responded_at=? WHERE id=?", [Clock::sql(), $inv['id']]);
                $st = Database::value('SELECT status FROM service_requests WHERE id = ? FOR UPDATE', [$inv['request_id']]);
                if ($st === 'PENDING_ACCEPTANCE') {
                    TaskStateMachine::apply((int)$inv['request_id'], 'OPEN', null, 'system', ['reason' => 'invitation_expired']);
                }
                AuditLogger::log(null, 'system', 'INVITATION_EXPIRED', 'service_invitation', (int)$inv['id']);
                NotificationService::notify((int)$inv['client_id'], 'INVITATION_EXPIRED', 'Invitation expired', 'An invitation expired without a response. You can invite another provider.', (int)$inv['request_id']);
                return 1;
            });
        }
        $overdue = Database::all("SELECT id, client_id, title FROM service_requests WHERE status = 'OPEN' AND deadline <= ?", [Clock::sql()]);
        foreach ($overdue as $r) {
            $n += Database::transaction(function () use ($r): int {
                $st = Database::value('SELECT status FROM service_requests WHERE id = ? FOR UPDATE', [$r['id']]);
                if ($st !== 'OPEN') {
                    return 0;
                }
                TaskStateMachine::apply((int)$r['id'], 'EXPIRED', null, 'system', ['reason' => 'deadline_passed']);
                NotificationService::notify((int)$r['client_id'], 'INVITATION_EXPIRED', 'Request expired', 'Your request expired because its deadline passed: ' . $r['title'], (int)$r['id']);
                return 1;
            });
        }
        return $n;
    }

    /** @return array<string,mixed> */
    private function lockOwnedByProvider(int $id, int $providerId): array
    {
        $inv = Database::one('SELECT * FROM service_invitations WHERE id = ? FOR UPDATE', [$id]);
        if ($inv === null || (int)$inv['provider_id'] !== $providerId) {
            throw ApiException::notFound('Invitation');
        }
        return $inv;
    }
}
