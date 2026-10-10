<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Pager;
use TaskBuddy\Services\AuditLogger;
use TaskBuddy\Services\TaskService;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

final class DisputesController extends Controller
{
    public function index(): Response
    {
        $r = $this->ctx->request;
        $status = in_array($r->queryStr('status'), ['OPEN', 'RESOLVED'], true) ? $r->queryStr('status') : '';
        $w = $status !== '' ? 'WHERE d.status = ?' : '';
        $p = $status !== '' ? [$status] : [];
        $from = "FROM service_disputes d JOIN service_assignments a ON a.id = d.assignment_id JOIN service_requests sr ON sr.id = a.request_id JOIN users o ON o.id = d.opened_by $w";
        $pager = new Pager((int)Database::value("SELECT COUNT(*) $from", $p), $r->queryInt('page'));
        $rows = Database::all("SELECT d.id, d.status, d.reason, d.resolution, d.created_at, sr.id AS request_id, sr.title, o.full_name AS opened_by $from ORDER BY (d.status = 'OPEN') DESC, d.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}", $p);
        return $this->view('disputes', 'Disputes', ['rows' => $rows, 'status' => $status, 'pagerHtml' => $this->pagerHtml($pager, '/disputes', array_filter(['status' => $status]))]);
    }

    /** @param array<string,string> $params */
    public function show(array $params): Response
    {
        $id = (int)$params['id'];
        $d = Database::one(
            'SELECT d.*, a.request_id, sr.title, sr.status AS request_status, sr.compensation, cu.full_name AS client, pu.full_name AS provider, o.full_name AS opener, rb.full_name AS resolver
             FROM service_disputes d JOIN service_assignments a ON a.id = d.assignment_id JOIN service_requests sr ON sr.id = a.request_id
             JOIN users cu ON cu.id = a.client_id JOIN users pu ON pu.id = a.provider_id JOIN users o ON o.id = d.opened_by LEFT JOIN users rb ON rb.id = d.resolved_by WHERE d.id = ?',
            [$id]
        );
        if ($d === null) {
            throw ApiException::notFound('Dispute');
        }
        $payment = Database::one('SELECT reference, amount, state FROM simulated_payments WHERE request_id = ?', [$d['request_id']]);
        $messages = Database::all('SELECT m.body, m.created_at, u.full_name FROM messages m JOIN conversations c ON c.id = m.conversation_id JOIN service_assignments sa ON sa.id = c.assignment_id JOIN users u ON u.id = m.sender_id WHERE sa.request_id = ? ORDER BY m.id DESC LIMIT 20', [$d['request_id']]);
        return $this->view('dispute_show', 'Dispute #' . $id, ['d' => $d, 'payment' => $payment, 'messages' => array_reverse($messages)]);
    }

    /** @param array<string,string> $params */
    public function resolve(array $params): Response
    {
        $id = (int)$params['id'];
        $r = $this->ctx->request;
        $note = mb_substr($r->postStr('note'), 0, 1000);
        if (mb_strlen($note) < 5) {
            throw ApiException::validation(['note' => 'Please record a resolution note (at least 5 characters).']);
        }
        (new TaskService())->resolveDispute($this->adminId(), $id, $r->postStr('resolution'), $note);
        return $this->redirect('/disputes/' . $id, 'Dispute resolved.');
    }

    public function reports(): Response
    {
        $r = $this->ctx->request;
        $status = in_array($r->queryStr('status'), ['OPEN', 'REVIEWED', 'DISMISSED'], true) ? $r->queryStr('status') : '';
        $w = $status !== '' ? 'WHERE ur.status = ?' : '';
        $p = $status !== '' ? [$status] : [];
        $from = "FROM user_reports ur JOIN users rp ON rp.id = ur.reporter_id JOIN users rd ON rd.id = ur.reported_id $w";
        $pager = new Pager((int)Database::value("SELECT COUNT(*) $from", $p), $r->queryInt('page'));
        $rows = Database::all("SELECT ur.id, ur.reason, ur.status, ur.admin_note, ur.created_at, rp.full_name AS reporter, rd.id AS reported_id, rd.full_name AS reported, rd.status AS reported_status $from ORDER BY (ur.status = 'OPEN') DESC, ur.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}", $p);
        return $this->view('reports_moderation', 'User reports', ['rows' => $rows, 'status' => $status, 'pagerHtml' => $this->pagerHtml($pager, '/moderation', array_filter(['status' => $status]))]);
    }

    /** @param array<string,string> $params */
    public function reviewReport(array $params): Response
    {
        $id = (int)$params['id'];
        $r = $this->ctx->request;
        $to = $r->postStr('status');
        if (!in_array($to, ['REVIEWED', 'DISMISSED'], true)) {
            throw ApiException::validation(['status' => 'Must be REVIEWED or DISMISSED.']);
        }
        $n = Database::run('UPDATE user_reports SET status = ?, admin_note = ? WHERE id = ?', [$to, mb_substr($r->postStr('admin_note'), 0, 1000) ?: null, $id]);
        if ($n === 0 && Database::one('SELECT id FROM user_reports WHERE id = ?', [$id]) === null) {
            throw ApiException::notFound('Report');
        }
        AuditLogger::log($this->adminId(), 'admin', 'USER_REPORT_' . $to, 'user_report', $id);
        return $this->redirect('/moderation', 'Report updated.');
    }
}
