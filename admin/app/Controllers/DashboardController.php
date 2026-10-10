<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Charts;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $one = static fn(string $sql, array $p = []): int => (int)Database::value($sql, $p);
        $now = Clock::sql();
        $stats = [
            'Total users' => $one("SELECT COUNT(*) FROM users WHERE role <> 'admin'"),
            'Clients' => $one("SELECT COUNT(*) FROM users WHERE role = 'client'"),
            'Providers' => $one("SELECT COUNT(*) FROM users WHERE role = 'provider'"),
            'Active providers' => $one("SELECT COUNT(*) FROM users WHERE role = 'provider' AND status = 'active'"),
            'Open requests' => $one("SELECT COUNT(*) FROM service_requests WHERE status = 'OPEN'"),
            'Pending invitations' => $one("SELECT COUNT(*) FROM service_invitations WHERE status = 'PENDING' AND expires_at > ?", [$now]),
            'In-progress tasks' => $one("SELECT COUNT(*) FROM service_requests WHERE status IN ('IN_PROGRESS','SUBMITTED','UNDER_REVIEW','REVISION_REQUESTED')"),
            'Completed tasks' => $one("SELECT COUNT(*) FROM service_requests WHERE status = 'COMPLETED'"),
            'Disputed tasks' => $one("SELECT COUNT(*) FROM service_requests WHERE status = 'DISPUTED'"),
        ];
        $payments = ['PENDING' => 0.0, 'COMPLETED' => 0.0, 'ON_HOLD' => 0.0, 'CANCELLED' => 0.0];
        foreach (Database::all('SELECT state, COALESCE(SUM(amount),0) AS total FROM simulated_payments GROUP BY state') as $r) {
            $payments[$r['state']] = (float)$r['total'];
        }
        $byStatus = [];
        foreach (Database::all('SELECT status, COUNT(*) AS c FROM service_requests GROUP BY status ORDER BY c DESC') as $r) {
            $byStatus[str_replace('_', ' ', $r['status'])] = (int)$r['c'];
        }
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $days[Clock::now()->modify("-$i days")->format('Y-m-d')] = 0;
        }
        foreach (Database::all('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM service_requests WHERE created_at >= ? GROUP BY DATE(created_at)', [Clock::sql(Clock::now()->modify('-13 days')->setTime(0, 0))]) as $r) {
            if (isset($days[$r['d']])) {
                $days[$r['d']] = (int)$r['c'];
            }
        }
        $recent = Database::all('SELECT a.id, a.action, a.entity_type, a.entity_id, a.created_at, u.full_name FROM activity_logs a LEFT JOIN users u ON u.id = a.actor_id ORDER BY a.id DESC LIMIT 8');
        return $this->view('dashboard', 'Overview', [
            'stats' => $stats,
            'payments' => $payments,
            'chartStatus' => Charts::bars($byStatus, 'Service requests by status'),
            'chartDays' => Charts::columns($days, 'Service requests created in the last 14 days'),
            'chartPayments' => Charts::bars(array_map(static fn($v) => round($v, 2), $payments), 'Simulated payment totals by state'),
            'recent' => $recent,
            'openDisputes' => $one("SELECT COUNT(*) FROM service_disputes WHERE status = 'OPEN'"),
            'openReports' => $one("SELECT COUNT(*) FROM user_reports WHERE status = 'OPEN'"),
        ]);
    }
}
