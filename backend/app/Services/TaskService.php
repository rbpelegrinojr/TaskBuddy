<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use Throwable;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Validator;

/** A task is a service request that has an assignment; the task id equals the request id. */
final class TaskService
{
    private const TASK_SQL = 'SELECT r.*, a.id AS assignment_id, a.provider_id, a.assigned_at, a.completed_at, pu.full_name AS provider_name, cu.full_name AS client_name,
        cat.name AS category_name, cv.id AS conversation_id
        FROM service_requests r JOIN service_assignments a ON a.request_id = r.id JOIN users pu ON pu.id = a.provider_id JOIN users cu ON cu.id = r.client_id
        JOIN service_categories cat ON cat.id = r.category_id LEFT JOIN conversations cv ON cv.assignment_id = a.id';

    /** @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>} */
    public function list(int $userId, string $role, int $page, int $perPage, ?string $status): array
    {
        $col = $role === 'provider' ? 'a.provider_id' : 'r.client_id';
        $where = "$col = ?" . ($status ? ' AND r.status = ?' : '');
        $params = $status ? [$userId, $status] : [$userId];
        $total = (int)Database::value("SELECT COUNT(*) FROM service_requests r JOIN service_assignments a ON a.request_id = r.id WHERE $where", $params);
        $rows = Database::all(self::TASK_SQL . " WHERE $where ORDER BY r.updated_at DESC, r.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
        return ['items' => array_map(fn($r) => $this->summary($r), $rows),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'has_more' => $page * $perPage < $total]];
    }

    /** @return array<string,mixed> */
    public function get(int $taskId, int $userId, string $role): array
    {
        $r = $this->row($taskId, $userId, $role);
        $out = $this->summary($r);
        $out['description'] = $r['description'];
        $out['requirements'] = $r['requirements'];
        $out['skills'] = array_map(static fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'is_mandatory' => (bool)$s['is_mandatory']],
            Database::all('SELECT s.id, s.name, x.is_mandatory FROM service_request_skills x JOIN skills s ON s.id = x.skill_id WHERE x.request_id = ? ORDER BY s.name', [$taskId]));
        $out['attachments'] = array_map(static fn($a) => ['id' => (int)$a['id'], 'original_name' => $a['original_name'], 'mime_type' => $a['mime_type'], 'size_bytes' => (int)$a['size_bytes'],
            'download_url' => '/api/attachments/' . (int)$a['id'] . '/download'],
            Database::all('SELECT id, original_name, mime_type, size_bytes FROM service_request_attachments WHERE request_id = ?', [$taskId]));
        $subs = Database::all('SELECT * FROM task_submissions WHERE assignment_id = ? ORDER BY id', [$r['assignment_id']]);
        $out['submissions'] = array_map(function ($s) {
            $files = Database::all('SELECT id, original_name, mime_type, size_bytes FROM submission_files WHERE submission_id = ? ORDER BY id', [$s['id']]);
            return ['id' => (int)$s['id'], 'note' => $s['note'], 'created_at' => Clock::iso($s['created_at']),
                'files' => array_map(static fn($f) => ['id' => (int)$f['id'], 'original_name' => $f['original_name'], 'mime_type' => $f['mime_type'],
                    'size_bytes' => (int)$f['size_bytes'], 'download_url' => '/api/submission-files/' . (int)$f['id'] . '/download'], $files)];
        }, $subs);
        $out['revisions'] = array_map(static fn($x) => ['id' => (int)$x['id'], 'reason' => $x['reason'], 'submission_id' => (int)$x['submission_id'], 'created_at' => Clock::iso($x['created_at'])],
            Database::all('SELECT id, reason, submission_id, created_at FROM task_revisions WHERE assignment_id = ? ORDER BY id', [$r['assignment_id']]));
        $d = Database::one('SELECT id, reason, status, resolution, resolution_note, created_at, resolved_at FROM service_disputes WHERE assignment_id = ? ORDER BY id DESC LIMIT 1', [$r['assignment_id']]);
        $out['dispute'] = $d === null ? null : ['id' => (int)$d['id'], 'reason' => $d['reason'], 'status' => $d['status'], 'resolution' => $d['resolution'],
            'resolution_note' => $d['resolution_note'], 'created_at' => Clock::iso($d['created_at']), 'resolved_at' => Clock::iso($d['resolved_at'])];
        $p = Database::one('SELECT p.*, r.title, c.full_name AS client_name, pr.full_name AS provider_name FROM simulated_payments p JOIN service_requests r ON r.id = p.request_id
            JOIN users c ON c.id = p.client_id JOIN users pr ON pr.id = p.provider_id WHERE p.request_id = ?', [$taskId]);
        $out['payment'] = $p === null ? null : PaymentService::format($p);
        $out['allowed_actions'] = $this->allowedActions($r['status'], $role, (bool)($d && $d['status'] === 'OPEN'));
        return $out;
    }

    /** @param array<int,array<string,mixed>> $files normalised $_FILES entries @return array<string,mixed> */
    public function submit(int $providerId, int $taskId, ?string $note, array $files): array
    {
        $v = new Validator(['note' => $note]);
        $note = $v->string('note', false, 0, 3000);
        $v->fail();
        if (count($files) < 1 || count($files) > 10) {
            throw ApiException::validation(['files' => 'Between 1 and 10 files are required.']);
        }
        $r = $this->row($taskId, $providerId, 'provider');
        if ($r['status'] !== 'IN_PROGRESS') {
            throw ApiException::conflict('INVALID_STATE', 'Work can only be submitted while the task is IN_PROGRESS.');
        }
        $stored = [];
        try {
            foreach ($files as $f) {
                $stored[] = FileService::store($f, 'submission');
            }
            $submissionId = Database::transaction(function () use ($providerId, $taskId, $r, $note, $stored): int {
                TaskStateMachine::apply($taskId, 'SUBMITTED', $providerId, 'provider');
                $sid = Database::insert('INSERT INTO task_submissions (assignment_id, submitted_by, note) VALUES (?,?,?)', [$r['assignment_id'], $providerId, $note]);
                foreach ($stored as $m) {
                    Database::run('INSERT INTO submission_files (submission_id, original_name, stored_name, mime_type, size_bytes, sha256) VALUES (?,?,?,?,?,?)',
                        [$sid, $m['original_name'], $m['stored_name'], $m['mime_type'], $m['size_bytes'], $m['sha256']]);
                }
                AuditLogger::log($providerId, 'provider', 'WORK_SUBMITTED', 'service_request', $taskId, ['submission_id' => $sid, 'files' => count($stored)]);
                NotificationService::notify((int)$r['client_id'], 'WORK_SUBMITTED', 'Work submitted', 'The provider submitted work for: ' . $r['title'], $taskId);
                return $sid;
            });
        } catch (Throwable $e) {
            foreach ($stored as $m) {
                FileService::delete('submission', $m['stored_name']);
            }
            throw $e;
        }
        return ['submission_id' => $submissionId, 'task' => $this->get($taskId, $providerId, 'provider')];
    }

    /** @return array<string,mixed> */
    public function startReview(int $clientId, int $taskId): array
    {
        $this->row($taskId, $clientId, 'client');
        Database::transaction(fn() => TaskStateMachine::apply($taskId, 'UNDER_REVIEW', $clientId, 'client'));
        return $this->get($taskId, $clientId, 'client');
    }

    /** @return array<string,mixed> */
    public function approve(int $clientId, int $taskId): array
    {
        $r = $this->row($taskId, $clientId, 'client');
        Database::transaction(function () use ($clientId, $taskId, $r): void {
            $this->ensureUnderReview($taskId, $clientId);
            TaskStateMachine::apply($taskId, 'COMPLETED', $clientId, 'client');
            Database::run('UPDATE service_assignments SET completed_at = ? WHERE id = ?', [Clock::sql(), $r['assignment_id']]);
            PaymentService::transition($taskId, 'COMPLETED', $clientId, 'client');
            AuditLogger::log($clientId, 'client', 'TASK_APPROVED', 'service_request', $taskId);
            NotificationService::notify((int)$r['provider_id'], 'TASK_COMPLETED', 'Task completed', 'The client approved your work: ' . $r['title'], $taskId);
            NotificationService::notify((int)$r['provider_id'], 'PAYMENT_RECORDED', 'Simulated payment completed', 'Simulated payment marked COMPLETED (SIMULATED / TEST TRANSACTION).', $taskId);
            NotificationService::notify($clientId, 'PAYMENT_RECORDED', 'Simulated payment completed', 'Simulated payment marked COMPLETED (SIMULATED / TEST TRANSACTION).', $taskId);
        });
        return $this->get($taskId, $clientId, 'client');
    }

    /** @return array<string,mixed> */
    public function requestRevision(int $clientId, int $taskId, mixed $reason): array
    {
        $v = new Validator(['reason' => $reason]);
        $reason = $v->string('reason', true, 3, 3000);
        $v->fail();
        $r = $this->row($taskId, $clientId, 'client');
        Database::transaction(function () use ($clientId, $taskId, $r, $reason): void {
            $this->ensureUnderReview($taskId, $clientId);
            TaskStateMachine::apply($taskId, 'REVISION_REQUESTED', $clientId, 'client');
            $sub = Database::value('SELECT MAX(id) FROM task_submissions WHERE assignment_id = ?', [$r['assignment_id']]);
            if ($sub === null) {
                throw ApiException::conflict('NO_SUBMISSION', 'There is no submission to revise.');
            }
            $rid = Database::insert('INSERT INTO task_revisions (assignment_id, submission_id, requested_by, reason) VALUES (?,?,?,?)', [$r['assignment_id'], $sub, $clientId, $reason]);
            TaskStateMachine::apply($taskId, 'IN_PROGRESS', $clientId, 'client', ['revision_id' => $rid]);
            AuditLogger::log($clientId, 'client', 'REVISION_REQUESTED', 'service_request', $taskId, ['revision_id' => $rid]);
            NotificationService::notify((int)$r['provider_id'], 'REVISION_REQUESTED', 'Revision requested', 'The client requested changes: ' . $r['title'], $taskId);
        });
        return $this->get($taskId, $clientId, 'client');
    }

    /** @return array<string,mixed> */
    public function dispute(int $userId, string $role, int $taskId, mixed $reason): array
    {
        $v = new Validator(['reason' => $reason]);
        $reason = $v->string('reason', true, 10, 3000);
        $v->fail();
        $r = $this->row($taskId, $userId, $role);
        Database::transaction(function () use ($userId, $role, $taskId, $r, $reason): void {
            $from = TaskStateMachine::apply($taskId, 'DISPUTED', $userId, $role);
            if (!in_array($from, TaskStateMachine::DISPUTABLE, true)) {
                throw ApiException::conflict('INVALID_STATE', "A dispute cannot be opened while the task is $from.");
            }
            $did = Database::insert('INSERT INTO service_disputes (assignment_id, opened_by, reason, previous_status, open_key) VALUES (?,?,?,?,?)', [$r['assignment_id'], $userId, $reason, $from, $r['assignment_id']]);
            PaymentService::transition($taskId, 'ON_HOLD', $userId, $role);
            AuditLogger::log($userId, $role, 'DISPUTE_OPENED', 'service_dispute', $did, ['request_id' => $taskId]);
            $other = $role === 'client' ? (int)$r['provider_id'] : (int)$r['client_id'];
            NotificationService::notify($other, 'DISPUTE_CREATED', 'Dispute opened', 'A dispute was opened for: ' . $r['title'], $taskId);
            NotificationService::notify($userId, 'DISPUTE_CREATED', 'Dispute opened', 'Your dispute was recorded for: ' . $r['title'], $taskId);
        });
        return $this->get($taskId, $userId, $role);
    }

    /**
     * Administrator resolution of an OPEN dispute (used by the admin dashboard).
     * COMPLETE_TASK -> COMPLETED + payment COMPLETED; CANCEL_TASK -> CANCELLED + payment CANCELLED; RESUME_TASK -> IN_PROGRESS + payment PENDING.
     */
    public function resolveDispute(int $adminId, int $disputeId, string $resolution, ?string $note): void
    {
        $map = ['COMPLETE_TASK' => ['COMPLETED', 'COMPLETED'], 'CANCEL_TASK' => ['CANCELLED', 'CANCELLED'], 'RESUME_TASK' => ['IN_PROGRESS', 'PENDING']];
        if (!isset($map[$resolution])) {
            throw ApiException::validation(['resolution' => 'Must be one of: ' . implode(', ', array_keys($map))]);
        }
        Database::transaction(function () use ($adminId, $disputeId, $resolution, $note, $map): void {
            $d = Database::one('SELECT d.*, a.request_id, a.client_id, a.provider_id FROM service_disputes d JOIN service_assignments a ON a.id = d.assignment_id WHERE d.id = ? FOR UPDATE', [$disputeId]);
            if ($d === null) {
                throw ApiException::notFound('Dispute');
            }
            if ($d['status'] !== 'OPEN') {
                throw ApiException::conflict('DISPUTE_RESOLVED', 'This dispute is already resolved.');
            }
            [$to, $payment] = $map[$resolution];
            TaskStateMachine::apply((int)$d['request_id'], $to, $adminId, 'admin', ['dispute_id' => $disputeId, 'resolution' => $resolution]);
            PaymentService::transition((int)$d['request_id'], $payment, $adminId, 'admin');
            if ($to === 'COMPLETED') {
                Database::run('UPDATE service_assignments SET completed_at = ? WHERE id = ?', [Clock::sql(), $d['assignment_id']]);
            }
            Database::run("UPDATE service_disputes SET status='RESOLVED', resolution=?, resolution_note=?, resolved_by=?, resolved_at=?, open_key=NULL WHERE id=?", [$resolution, $note, $adminId, Clock::sql(), $disputeId]);
            AuditLogger::log($adminId, 'admin', 'DISPUTE_RESOLVED', 'service_dispute', $disputeId, ['resolution' => $resolution]);
            foreach ([(int)$d['client_id'], (int)$d['provider_id']] as $uid) {
                NotificationService::notify($uid, 'DISPUTE_RESOLVED', 'Dispute resolved', "An administrator resolved the dispute ($resolution).", (int)$d['request_id']);
            }
        });
    }

    /** @return array{path:string,mime:string,name:string} */
    public function submissionFileForDownload(int $fileId, int $userId, string $role): array
    {
        $f = Database::one('SELECT f.*, s.assignment_id, a.request_id FROM submission_files f JOIN task_submissions s ON s.id = f.submission_id JOIN service_assignments a ON a.id = s.assignment_id WHERE f.id = ?', [$fileId]);
        if ($f === null) {
            throw ApiException::notFound('File');
        }
        $this->row((int)$f['request_id'], $userId, $role); // participants only
        AuditLogger::log($userId, $role, 'FILE_DOWNLOADED', 'submission_file', $fileId);
        return ['path' => FileService::path('submission', $f['stored_name']), 'mime' => $f['mime_type'], 'name' => $f['original_name']];
    }

    // -------------------------------------------------------------------------------------------

    /** SUBMITTED is automatically moved to UNDER_REVIEW when the client acts on it. */
    private function ensureUnderReview(int $taskId, int $clientId): void
    {
        $st = Database::value('SELECT status FROM service_requests WHERE id = ? FOR UPDATE', [$taskId]);
        if ($st === 'SUBMITTED') {
            TaskStateMachine::apply($taskId, 'UNDER_REVIEW', $clientId, 'client');
        } elseif ($st !== 'UNDER_REVIEW') {
            throw ApiException::conflict('INVALID_STATE', "This action requires submitted work (current status: $st).");
        }
    }

    /** @return array<string,mixed> */
    private function row(int $taskId, int $userId, string $role): array
    {
        $r = Database::one(self::TASK_SQL . ' WHERE r.id = ?', [$taskId]);
        $ok = $r !== null && (($role === 'client' && (int)$r['client_id'] === $userId) || ($role === 'provider' && (int)$r['provider_id'] === $userId));
        if (!$ok) {
            throw ApiException::notFound('Task');
        }
        return $r;
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    private function summary(array $r): array
    {
        return [
            'id' => (int)$r['id'], 'title' => $r['title'], 'status' => $r['status'], 'category' => ['id' => (int)$r['category_id'], 'name' => $r['category_name']],
            'deadline' => Clock::iso($r['deadline']), 'compensation' => $r['compensation'],
            'client' => ['id' => (int)$r['client_id'], 'full_name' => $r['client_name']],
            'provider' => ['id' => (int)$r['provider_id'], 'full_name' => $r['provider_name']],
            'assignment_id' => (int)$r['assignment_id'], 'conversation_id' => $r['conversation_id'] === null ? null : (int)$r['conversation_id'],
            'assigned_at' => Clock::iso($r['assigned_at']), 'completed_at' => Clock::iso($r['completed_at']), 'updated_at' => Clock::iso($r['updated_at']),
        ];
    }

    /** @return string[] */
    private function allowedActions(string $status, string $role, bool $openDispute): array
    {
        $a = [];
        if ($role === 'provider' && $status === 'IN_PROGRESS') {
            $a[] = 'submit';
        }
        if ($role === 'client' && in_array($status, ['SUBMITTED', 'UNDER_REVIEW'], true)) {
            $a = array_merge($a, $status === 'SUBMITTED' ? ['start_review'] : [], ['approve', 'revision']);
        }
        if (in_array($status, TaskStateMachine::DISPUTABLE, true)) {
            $a[] = 'dispute';
        }
        return $a;
    }
}
