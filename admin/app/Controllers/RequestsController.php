<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Pager;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

final class RequestsController extends Controller
{
    public const STATUSES = ['OPEN', 'PENDING_ACCEPTANCE', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'REVISION_REQUESTED', 'COMPLETED', 'CANCELLED', 'EXPIRED', 'DISPUTED'];
    private const ACTIVE = ['PENDING_ACCEPTANCE', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'REVISION_REQUESTED', 'DISPUTED'];

    public function index(): Response
    {
        return $this->listing('requests', 'Service requests', '/requests', self::STATUSES);
    }

    public function transactions(): Response
    {
        return $this->listing('transactions', 'Active service transactions', '/transactions', self::ACTIVE);
    }

    /** @param string[] $allowedStatuses */
    private function listing(string $template, string $title, string $path, array $allowedStatuses): Response
    {
        $r = $this->ctx->request;
        $q = $r->queryStr('q');
        $status = in_array($r->queryStr('status'), $allowedStatuses, true) ? $r->queryStr('status') : '';
        $category = $r->queryInt('category', 0, 0);
        $where = $template === 'transactions' ? ["r.status IN ('" . implode("','", self::ACTIVE) . "')"] : ['1=1'];
        $p = [];
        if ($q !== '') {
            $where[] = '(r.title LIKE ? OR c.full_name LIKE ? OR r.id = ?)';
            $p[] = $this->like($q);
            $p[] = $this->like($q);
            $p[] = ctype_digit($q) ? (int)$q : 0;
        }
        if ($status !== '') {
            $where[] = 'r.status = ?';
            $p[] = $status;
        }
        if ($category > 0) {
            $where[] = 'r.category_id = ?';
            $p[] = $category;
        }
        $w = implode(' AND ', $where);
        $from = "FROM service_requests r JOIN users c ON c.id = r.client_id JOIN service_categories cat ON cat.id = r.category_id
                 LEFT JOIN service_assignments a ON a.request_id = r.id LEFT JOIN users pv ON pv.id = a.provider_id
                 LEFT JOIN simulated_payments sp ON sp.request_id = r.id WHERE $w";
        $pager = new Pager((int)Database::value("SELECT COUNT(*) $from", $p), $r->queryInt('page'));
        $rows = Database::all(
            "SELECT r.id, r.title, r.status, r.deadline, r.compensation, r.created_at, c.full_name AS client, pv.full_name AS provider, cat.name AS category, sp.state AS payment_state $from
             ORDER BY r.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}",
            $p
        );
        $filters = ['q' => $q, 'status' => $status, 'category' => $category ?: ''];
        return $this->view($template, $title, [
            'rows' => $rows, 'filters' => $filters, 'statuses' => $allowedStatuses,
            'categories' => Database::all('SELECT id, name FROM service_categories ORDER BY name'),
            'pagerHtml' => $this->pagerHtml($pager, $path, array_filter($filters)), 'path' => $path,
        ]);
    }

    /** @param array<string,string> $params */
    public function show(array $params): Response
    {
        $id = (int)$params['id'];
        $req = Database::one('SELECT r.*, c.full_name AS client_name, c.email AS client_email, cat.name AS category FROM service_requests r JOIN users c ON c.id = r.client_id JOIN service_categories cat ON cat.id = r.category_id WHERE r.id = ?', [$id]);
        if ($req === null) {
            throw ApiException::notFound('Request');
        }
        $asg = Database::one('SELECT a.*, u.full_name AS provider_name, u.email AS provider_email FROM service_assignments a JOIN users u ON u.id = a.provider_id WHERE a.request_id = ?', [$id]);
        $subs = Database::all('SELECT s.id, s.note, s.created_at FROM task_submissions s JOIN service_assignments a ON a.id = s.assignment_id WHERE a.request_id = ? ORDER BY s.id', [$id]);
        $files = [];
        foreach (Database::all('SELECT f.submission_id, f.original_name, f.mime_type, f.size_bytes FROM submission_files f JOIN task_submissions s ON s.id = f.submission_id JOIN service_assignments a ON a.id = s.assignment_id WHERE a.request_id = ? ORDER BY f.id', [$id]) as $f) {
            $files[(int)$f['submission_id']][] = $f;
        }
        return $this->view('request_show', 'Request #' . $id, [
            'req' => $req, 'asg' => $asg,
            'skills' => Database::all('SELECT s.name, rs.is_mandatory FROM service_request_skills rs JOIN skills s ON s.id = rs.skill_id WHERE rs.request_id = ? ORDER BY s.name', [$id]),
            'invitations' => Database::all('SELECT i.id, i.status, i.created_at, i.expires_at, i.responded_at, u.full_name AS provider FROM service_invitations i JOIN users u ON u.id = i.provider_id WHERE i.request_id = ? ORDER BY i.id', [$id]),
            'results' => Database::all('SELECT id, created_at, eligible_count, candidates_evaluated, algorithm_version FROM matching_results WHERE request_id = ? ORDER BY id DESC', [$id]),
            'attachments' => Database::all('SELECT original_name, mime_type, size_bytes, created_at FROM service_request_attachments WHERE request_id = ? ORDER BY id', [$id]),
            'subs' => $subs, 'files' => $files,
            'revisions' => Database::all('SELECT v.reason, v.created_at FROM task_revisions v JOIN service_assignments a ON a.id = v.assignment_id WHERE a.request_id = ? ORDER BY v.id', [$id]),
            'dispute' => Database::one('SELECT d.id, d.status, d.reason, d.resolution FROM service_disputes d JOIN service_assignments a ON a.id = d.assignment_id WHERE a.request_id = ? ORDER BY d.id DESC LIMIT 1', [$id]),
            'payment' => Database::one('SELECT reference, amount, state, created_at, completed_at FROM simulated_payments WHERE request_id = ?', [$id]),
            'history' => Database::all("SELECT action, details_json, created_at FROM activity_logs WHERE entity_type = 'service_request' AND entity_id = ? ORDER BY id", [$id]),
        ]);
    }
}
