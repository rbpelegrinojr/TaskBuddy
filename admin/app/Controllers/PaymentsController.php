<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Pager;
use TaskBuddy\Support\Database;

final class PaymentsController extends Controller
{
    public function index(): Response
    {
        $r = $this->ctx->request;
        $state = in_array($r->queryStr('state'), ['PENDING', 'COMPLETED', 'ON_HOLD', 'CANCELLED'], true) ? $r->queryStr('state') : '';
        $q = $r->queryStr('q');
        $where = ['1=1'];
        $p = [];
        if ($state !== '') {
            $where[] = 'sp.state = ?';
            $p[] = $state;
        }
        if ($q !== '') {
            $where[] = '(sp.reference LIKE ? OR sr.title LIKE ?)';
            $p[] = $this->like($q);
            $p[] = $this->like($q);
        }
        $w = implode(' AND ', $where);
        $from = "FROM simulated_payments sp JOIN service_requests sr ON sr.id = sp.request_id JOIN users c ON c.id = sp.client_id JOIN users pv ON pv.id = sp.provider_id WHERE $w";
        $pager = new Pager((int)Database::value("SELECT COUNT(*) $from", $p), $r->queryInt('page'));
        $rows = Database::all("SELECT sp.id, sp.reference, sp.request_id, sr.title, sp.amount, sp.state, sp.created_at, sp.completed_at, c.full_name AS client, pv.full_name AS provider $from ORDER BY sp.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}", $p);
        $totals = ['PENDING' => 0.0, 'COMPLETED' => 0.0, 'ON_HOLD' => 0.0, 'CANCELLED' => 0.0];
        foreach (Database::all('SELECT state, COALESCE(SUM(amount),0) AS t FROM simulated_payments GROUP BY state') as $t) {
            $totals[$t['state']] = (float)$t['t'];
        }
        $filters = ['q' => $q, 'state' => $state];
        return $this->view('payments', 'Simulated payment records', ['rows' => $rows, 'totals' => $totals, 'filters' => $filters, 'pagerHtml' => $this->pagerHtml($pager, '/payments', array_filter($filters))]);
    }
}
