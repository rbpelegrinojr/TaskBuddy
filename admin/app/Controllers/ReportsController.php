<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Csv;
use TaskBuddy\Services\AuditLogger;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

final class ReportsController extends Controller
{
    /** @return array<string,array{title:string,headers:string[],sql:string}> */
    private function definitions(): array
    {
        return [
            'users' => ['title' => 'Users (no credentials)', 'headers' => ['id', 'full_name', 'email', 'role', 'status', 'created_at', 'last_login_at'],
                'sql' => "SELECT id, full_name, email, role, status, created_at, last_login_at FROM users WHERE role <> 'admin' ORDER BY id"],
            'requests' => ['title' => 'Service requests', 'headers' => ['id', 'title', 'category', 'client', 'provider', 'status', 'compensation', 'deadline', 'created_at'],
                'sql' => 'SELECT r.id, r.title, cat.name, c.full_name, pv.full_name, r.status, r.compensation, r.deadline, r.created_at FROM service_requests r JOIN service_categories cat ON cat.id = r.category_id JOIN users c ON c.id = r.client_id LEFT JOIN service_assignments a ON a.request_id = r.id LEFT JOIN users pv ON pv.id = a.provider_id ORDER BY r.id'],
            'matching' => ['title' => 'Matching results (ranked providers)', 'headers' => ['result_id', 'request_id', 'algorithm_version', 'rank', 'provider_id', 'final_score', 'category', 'skills', 'availability', 'budget', 'budget_status'],
                'sql' => 'SELECT m.id, m.request_id, m.algorithm_version, d.rank_position, d.provider_id, d.final_score, d.category_score, d.skills_score, d.availability_score, d.budget_score, d.budget_status FROM matching_results m JOIN matching_result_details d ON d.result_id = m.id ORDER BY m.id, d.rank_position'],
            'payments' => ['title' => 'Simulated payments (TEST DATA)', 'headers' => ['reference', 'request_id', 'client', 'provider', 'amount', 'state', 'simulated', 'created_at', 'completed_at'],
                'sql' => 'SELECT sp.reference, sp.request_id, c.full_name, pv.full_name, sp.amount, sp.state, sp.is_simulated, sp.created_at, sp.completed_at FROM simulated_payments sp JOIN users c ON c.id = sp.client_id JOIN users pv ON pv.id = sp.provider_id ORDER BY sp.id'],
            'logs' => ['title' => 'Activity log', 'headers' => ['id', 'created_at', 'actor_id', 'actor_role', 'action', 'entity_type', 'entity_id', 'ip_address'],
                'sql' => 'SELECT id, created_at, actor_id, actor_role, action, entity_type, entity_id, ip_address FROM activity_logs ORDER BY id'],
        ];
    }

    public function index(): Response
    {
        $one = static fn(string $sql): int => (int)Database::value($sql);
        $summary = [
            'Requests created' => $one('SELECT COUNT(*) FROM service_requests'),
            'Requests completed' => $one("SELECT COUNT(*) FROM service_requests WHERE status = 'COMPLETED'"),
            'Requests cancelled or expired' => $one("SELECT COUNT(*) FROM service_requests WHERE status IN ('CANCELLED','EXPIRED')"),
            'Matching runs' => $one('SELECT COUNT(*) FROM matching_results'),
            'Matching runs with no eligible provider' => $one('SELECT COUNT(*) FROM matching_results WHERE eligible_count = 0'),
            'Invitations sent' => $one('SELECT COUNT(*) FROM service_invitations'),
            'Invitations accepted' => $one("SELECT COUNT(*) FROM service_invitations WHERE status = 'ACCEPTED'"),
            'Invitations declined' => $one("SELECT COUNT(*) FROM service_invitations WHERE status = 'DECLINED'"),
            'Disputes opened' => $one('SELECT COUNT(*) FROM service_disputes'),
        ];
        $avg = Database::value('SELECT AVG(final_score) FROM matching_result_details WHERE rank_position = 1');
        $byCategory = Database::all("SELECT c.name, COUNT(r.id) AS requests, SUM(r.status = 'COMPLETED') AS completed FROM service_categories c LEFT JOIN service_requests r ON r.category_id = c.id GROUP BY c.id, c.name ORDER BY requests DESC, c.name");
        return $this->view('reports', 'Reports', ['summary' => $summary, 'avgTop' => $avg === null ? null : round((float)$avg, 2), 'byCategory' => $byCategory, 'defs' => $this->definitions()]);
    }

    public function export(): Response
    {
        $type = $this->ctx->request->queryStr('type');
        $defs = $this->definitions();
        if (!isset($defs[$type])) {
            throw ApiException::notFound('Report');
        }
        $d = $defs[$type];
        $stmt = Database::pdo()->prepare($d['sql']);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
        AuditLogger::log($this->adminId(), 'admin', 'REPORT_EXPORTED', 'report', null, ['type' => $type, 'rows' => count($rows)]);
        return $this->csv('taskbuddy-' . $type . '-' . gmdate('Ymd-His') . '.csv', Csv::build($d['headers'], $rows));
    }
}
