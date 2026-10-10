<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use Throwable;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Logger;
use TaskBuddy\Support\Validator;

final class RequestService
{
    public function __construct(private readonly MatchingService $matching = new MatchingService())
    {
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function create(int $clientId, array $in): array
    {
        $f = $this->validate($in, true);
        $id = Database::transaction(function () use ($clientId, $f): int {
            $id = Database::insert(
                'INSERT INTO service_requests (client_id, category_id, title, description, requirements, deadline, compensation, estimated_hours, requires_exclusive, status) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$clientId, $f['category_id'], $f['title'], $f['description'], $f['requirements'], Clock::sql($f['deadline']), $f['compensation'], $f['estimated_hours'], (int)$f['requires_exclusive'], 'OPEN']
            );
            $this->saveSkills($id, $f['skills']);
            AuditLogger::log($clientId, 'client', 'REQUEST_CREATED', 'service_request', $id, ['category_id' => $f['category_id']]);
            return $id;
        });
        return ['request' => $this->get($id, $clientId, 'client'), 'matching' => $this->safeMatch($id, $clientId)];
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function update(int $clientId, int $id, array $in): array
    {
        $changed = false;
        Database::transaction(function () use ($clientId, $id, $in, &$changed): void {
            $row = $this->lockOwned($id, $clientId);
            if ($row['status'] !== 'OPEN') {
                throw ApiException::conflict('INVALID_STATE', 'Only OPEN requests can be edited.');
            }
            $f = $this->validate($in + $this->existingInput($id, $row), false);
            Database::run(
                'UPDATE service_requests SET category_id=?, title=?, description=?, requirements=?, deadline=?, compensation=?, estimated_hours=?, requires_exclusive=? WHERE id=?',
                [$f['category_id'], $f['title'], $f['description'], $f['requirements'], Clock::sql($f['deadline']), $f['compensation'], $f['estimated_hours'], (int)$f['requires_exclusive'], $id]
            );
            $this->saveSkills($id, $f['skills']);
            $changed = array_key_exists('category_id', $in) || array_key_exists('skills', $in) || array_key_exists('deadline', $in)
                || array_key_exists('compensation', $in) || array_key_exists('estimated_hours', $in) || array_key_exists('requires_exclusive', $in);
            AuditLogger::log($clientId, 'client', 'REQUEST_UPDATED', 'service_request', $id);
        });
        $matching = $changed ? $this->safeMatch($id, $clientId) : null;
        return ['request' => $this->get($id, $clientId, 'client'), 'matching' => $matching];
    }

    /** @return array<string,mixed> */
    public function cancel(int $clientId, int $id): array
    {
        Database::transaction(function () use ($clientId, $id): void {
            $this->lockOwned($id, $clientId);
            $pending = Database::one("SELECT id, provider_id FROM service_invitations WHERE request_id = ? AND status = 'PENDING' FOR UPDATE", [$id]);
            TaskStateMachine::apply($id, 'CANCELLED', $clientId, 'client');
            if ($pending !== null) {
                Database::run("UPDATE service_invitations SET status='WITHDRAWN', pending_key=NULL, responded_at=? WHERE id=?", [Clock::sql(), $pending['id']]);
                NotificationService::notify((int)$pending['provider_id'], 'INVITATION_DECLINED', 'Invitation withdrawn', 'The client cancelled the request you were invited to.', $id);
            }
        });
        return $this->get($id, $clientId, 'client');
    }

    /** Explicit re-run of matching (client). @return array<string,mixed> */
    public function rematch(int $clientId, int $id): array
    {
        $this->lockOwnedNoTx($id, $clientId);
        $resultId = $this->matching->run($id, $clientId);
        return ['result_id' => $resultId];
    }

    /** @return array<string,mixed> */
    public function get(int $id, int $userId, string $role): array
    {
        $r = $this->row($id);
        $this->assertCanView($r, $userId, $role);
        return $this->format($r, $userId, $role);
    }

    /**
     * Client: own requests. Provider: scope=opportunities (OPEN requests where the provider is in the latest match result).
     * @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function list(int $userId, string $role, int $page, int $perPage, ?string $status): array
    {
        (new InvitationService())->expireStale();
        $off = ($page - 1) * $perPage;
        if ($role === 'client') {
            $where = 'r.client_id = ?' . ($status ? ' AND r.status = ?' : '');
            $params = $status ? [$userId, $status] : [$userId];
        } else {
            $where = "r.status = 'OPEN' AND EXISTS (SELECT 1 FROM matching_result_details d WHERE d.provider_id = ? AND d.result_id = (SELECT MAX(m.id) FROM matching_results m WHERE m.request_id = r.id))";
            $params = [$userId];
        }
        $total = (int)Database::value("SELECT COUNT(*) FROM service_requests r WHERE $where", $params);
        $rows = Database::all("SELECT r.id FROM service_requests r WHERE $where ORDER BY r.id DESC LIMIT $perPage OFFSET $off", $params);
        $items = array_map(fn($x) => $this->format($this->row((int)$x['id']), $userId, $role, false), $rows);
        return ['items' => $items, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'has_more' => $page * $perPage < $total]];
    }

    /** @param array<string,mixed> $file @return array<string,mixed> */
    public function addAttachment(int $clientId, int $id, array $file): array
    {
        $row = $this->row($id);
        if ((int)$row['client_id'] !== $clientId) {
            throw ApiException::notFound('Request');
        }
        if (in_array($row['status'], ['COMPLETED', 'CANCELLED', 'EXPIRED'], true)) {
            throw ApiException::conflict('INVALID_STATE', 'Attachments cannot be added to a closed request.');
        }
        if ((int)Database::value('SELECT COUNT(*) FROM service_request_attachments WHERE request_id = ?', [$id]) >= 10) {
            throw ApiException::conflict('TOO_MANY_FILES', 'A request can have at most 10 attachments.');
        }
        $m = FileService::store($file, 'request');
        try {
            $aid = Database::insert(
                'INSERT INTO service_request_attachments (request_id, uploaded_by, original_name, stored_name, mime_type, size_bytes, sha256) VALUES (?,?,?,?,?,?,?)',
                [$id, $clientId, $m['original_name'], $m['stored_name'], $m['mime_type'], $m['size_bytes'], $m['sha256']]
            );
        } catch (Throwable $e) {
            FileService::delete('request', $m['stored_name']);
            throw $e;
        }
        AuditLogger::log($clientId, 'client', 'REQUEST_ATTACHMENT_ADDED', 'service_request', $id, ['attachment_id' => $aid]);
        return ['id' => $aid, 'original_name' => $m['original_name'], 'mime_type' => $m['mime_type'], 'size_bytes' => $m['size_bytes']];
    }

    /** @return array{path:string,mime:string,name:string} */
    public function attachmentForDownload(int $attachmentId, int $userId, string $role): array
    {
        $a = Database::one('SELECT * FROM service_request_attachments WHERE id = ?', [$attachmentId]);
        if ($a === null) {
            throw ApiException::notFound('Attachment');
        }
        $this->assertCanView($this->row((int)$a['request_id']), $userId, $role, true);
        AuditLogger::log($userId, $role, 'FILE_DOWNLOADED', 'request_attachment', $attachmentId);
        return ['path' => FileService::path('request', $a['stored_name']), 'mime' => $a['mime_type'], 'name' => $a['original_name']];
    }

    // ---------------------------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function row(int $id): array
    {
        $r = Database::one('SELECT r.*, c.name AS category_name, u.full_name AS client_name FROM service_requests r
            JOIN service_categories c ON c.id = r.category_id JOIN users u ON u.id = r.client_id WHERE r.id = ?', [$id]);
        if ($r === null) {
            throw ApiException::notFound('Request');
        }
        return $r;
    }

    /** @param array<string,mixed> $r */
    private function assertCanView(array $r, int $userId, string $role, bool $forFile = false): void
    {
        if ($role === 'client') {
            if ((int)$r['client_id'] !== $userId) {
                throw ApiException::notFound('Request');
            }
            return;
        }
        if ($role !== 'provider') {
            throw ApiException::forbidden();
        }
        $id = (int)$r['id'];
        $linked = Database::value('SELECT 1 FROM service_assignments WHERE request_id = ? AND provider_id = ?', [$id, $userId])
            ?? Database::value('SELECT 1 FROM service_invitations WHERE request_id = ? AND provider_id = ?', [$id, $userId]);
        if ($linked === null && !$forFile && in_array($r['status'], ['OPEN', 'PENDING_ACCEPTANCE'], true)) {
            $linked = $this->matching->isRecommended($id, $userId);
        }
        if ($linked === null) {
            throw ApiException::notFound('Request');
        }
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    private function format(array $r, int $userId, string $role, bool $detail = true): array
    {
        $id = (int)$r['id'];
        $skills = Database::all('SELECT s.id, s.name, rs.is_mandatory FROM service_request_skills rs JOIN skills s ON s.id = rs.skill_id WHERE rs.request_id = ? ORDER BY s.name', [$id]);
        $out = [
            'id' => $id,
            'client' => ['id' => (int)$r['client_id'], 'full_name' => $r['client_name']],
            'category' => ['id' => (int)$r['category_id'], 'name' => $r['category_name']],
            'title' => $r['title'], 'description' => $r['description'], 'requirements' => $r['requirements'],
            'deadline' => Clock::iso($r['deadline']), 'compensation' => $r['compensation'],
            'estimated_hours' => $r['estimated_hours'] === null ? null : (float)$r['estimated_hours'],
            'requires_exclusive' => (bool)$r['requires_exclusive'], 'status' => $r['status'],
            'skills' => array_map(static fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'is_mandatory' => (bool)$s['is_mandatory']], $skills),
            'created_at' => Clock::iso($r['created_at']), 'updated_at' => Clock::iso($r['updated_at']),
        ];
        if ($role === 'provider') {
            $resultId = $this->matching->latestResultId($id);
            $m = $resultId === null ? null : Database::one('SELECT final_score, budget_status, explanation_json FROM matching_result_details WHERE result_id = ? AND provider_id = ?', [$resultId, $userId]);
            $out['my_match'] = $m === null ? null : ['final_score' => (float)$m['final_score'], 'budget_status' => $m['budget_status'], 'summary' => json_decode($m['explanation_json'], true)['summary'] ?? null];
        }
        if (!$detail) {
            return $out;
        }
        $att = Database::all('SELECT id, original_name, mime_type, size_bytes, created_at FROM service_request_attachments WHERE request_id = ? ORDER BY id', [$id]);
        $out['attachments'] = array_map(static fn($a) => ['id' => (int)$a['id'], 'original_name' => $a['original_name'], 'mime_type' => $a['mime_type'],
            'size_bytes' => (int)$a['size_bytes'], 'created_at' => Clock::iso($a['created_at']), 'download_url' => '/api/attachments/' . (int)$a['id'] . '/download'], $att);
        $inv = Database::one("SELECT id, provider_id, status, expires_at FROM service_invitations WHERE request_id = ? AND status = 'PENDING'", [$id]);
        $out['pending_invitation'] = $inv === null ? null : ['id' => (int)$inv['id'], 'provider_id' => (int)$inv['provider_id'], 'status' => $inv['status'], 'expires_at' => Clock::iso($inv['expires_at'])];
        $asg = Database::one('SELECT a.id, a.provider_id, a.assigned_at, u.full_name FROM service_assignments a JOIN users u ON u.id = a.provider_id WHERE a.request_id = ?', [$id]);
        $out['assignment'] = $asg === null ? null : ['id' => (int)$asg['id'], 'provider' => ['id' => (int)$asg['provider_id'], 'full_name' => $asg['full_name']], 'assigned_at' => Clock::iso($asg['assigned_at'])];
        return $out;
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    private function validate(array $in, bool $creating): array
    {
        $v = new Validator($in);
        $f = [
            'title' => $v->string('title', true, 3, 200),
            'description' => $v->string('description', true, 10, 5000),
            'requirements' => $v->string('requirements', false, 0, 3000),
            'category_id' => $v->int('category_id', true, 1),
            'deadline' => $v->futureDateTime('deadline'),
            'compensation' => $v->money('compensation'),
            'estimated_hours' => $v->decimal('estimated_hours', false, 0.25, 2000),
            'requires_exclusive' => $v->bool('requires_exclusive', false),
            'skills' => [],
        ];
        if ($f['category_id'] !== null && Database::value('SELECT id FROM service_categories WHERE id = ? AND is_active = 1', [$f['category_id']]) === null) {
            $v->error('category_id', 'Unknown or inactive category.');
        }
        $skills = $in['skills'] ?? [];
        if (!is_array($skills) || count($skills) > 20) {
            $v->error('skills', 'Must be a list of at most 20 {skill_id, is_mandatory} objects.');
        } else {
            $seen = [];
            foreach ($skills as $i => $s) {
                $sid = is_array($s) ? ($s['skill_id'] ?? null) : null;
                if (!is_int($sid) || $sid < 1 || isset($seen[$sid]) || Database::value('SELECT id FROM skills WHERE id = ? AND is_active = 1', [$sid]) === null) {
                    $v->error("skills.$i", 'Requires a unique, valid skill_id.');
                    continue;
                }
                $seen[$sid] = true;
                $f['skills'][] = ['skill_id' => $sid, 'is_mandatory' => !empty($s['is_mandatory']) && $s['is_mandatory'] !== 'false'];
            }
        }
        $v->fail();
        return $f;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function existingInput(int $id, array $row): array
    {
        $skills = Database::all('SELECT skill_id, is_mandatory FROM service_request_skills WHERE request_id = ?', [$id]);
        return [
            'title' => $row['title'], 'description' => $row['description'], 'requirements' => $row['requirements'], 'category_id' => (int)$row['category_id'],
            'deadline' => Clock::iso($row['deadline']), 'compensation' => $row['compensation'], 'estimated_hours' => $row['estimated_hours'],
            'requires_exclusive' => (bool)$row['requires_exclusive'],
            'skills' => array_map(static fn($s) => ['skill_id' => (int)$s['skill_id'], 'is_mandatory' => (bool)$s['is_mandatory']], $skills),
        ];
    }

    /** @param array<int,array<string,mixed>> $skills */
    private function saveSkills(int $id, array $skills): void
    {
        Database::run('DELETE FROM service_request_skills WHERE request_id = ?', [$id]);
        foreach ($skills as $s) {
            Database::run('INSERT INTO service_request_skills (request_id, skill_id, is_mandatory) VALUES (?,?,?)', [$id, $s['skill_id'], (int)$s['is_mandatory']]);
        }
    }

    /** @return array<string,mixed> */
    private function lockOwned(int $id, int $clientId): array
    {
        $r = Database::one('SELECT * FROM service_requests WHERE id = ? FOR UPDATE', [$id]);
        if ($r === null || (int)$r['client_id'] !== $clientId) {
            throw ApiException::notFound('Request');
        }
        return $r;
    }

    private function lockOwnedNoTx(int $id, int $clientId): void
    {
        $r = Database::one('SELECT client_id FROM service_requests WHERE id = ?', [$id]);
        if ($r === null || (int)$r['client_id'] !== $clientId) {
            throw ApiException::notFound('Request');
        }
    }

    /** Matching failure must not lose the request. @return array<string,mixed> */
    private function safeMatch(int $id, int $clientId): array
    {
        try {
            $rid = $this->matching->run($id, $clientId);
            $count = (int)Database::value('SELECT eligible_count FROM matching_results WHERE id = ?', [$rid]);
            return ['status' => 'COMPLETED', 'result_id' => $rid, 'eligible_count' => $count, 'no_match' => $count === 0];
        } catch (Throwable $e) {
            Logger::error('Matching failed', ['request_id' => $id, 'error' => $e->getMessage()]);
            return ['status' => 'FAILED', 'result_id' => null, 'message' => 'Matching could not be completed. Use the retry action.'];
        }
    }
}
