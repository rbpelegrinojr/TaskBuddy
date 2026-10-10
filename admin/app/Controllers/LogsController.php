<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Pager;
use TaskBuddy\Support\Database;

final class LogsController extends Controller
{
    public function index(): Response
    {
        $r = $this->ctx->request;
        $action = $r->queryStr('action');
        $q = $r->queryStr('q');
        $where = ['1=1'];
        $p = [];
        if ($action !== '') {
            $where[] = 'a.action = ?';
            $p[] = $action;
        }
        if ($q !== '') {
            $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR a.entity_type LIKE ? OR a.ip_address LIKE ?)';
            $like = $this->like($q);
            array_push($p, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $from = "FROM activity_logs a LEFT JOIN users u ON u.id = a.actor_id WHERE $w";
        $pager = new Pager((int)Database::value("SELECT COUNT(*) $from", $p), $r->queryInt('page'), 30);
        $rows = Database::all("SELECT a.id, a.action, a.actor_role, a.entity_type, a.entity_id, a.details_json, a.ip_address, a.created_at, u.full_name $from ORDER BY a.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}", $p);
        $actions = array_column(Database::all('SELECT DISTINCT action FROM activity_logs ORDER BY action'), 'action');
        $filters = ['action' => $action, 'q' => $q];
        return $this->view('logs', 'Activity logs', ['rows' => $rows, 'actions' => $actions, 'filters' => $filters, 'pagerHtml' => $this->pagerHtml($pager, '/logs', array_filter($filters))]);
    }
}
