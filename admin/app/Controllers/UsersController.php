<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Pager;
use TaskBuddy\Services\AuditLogger;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;

final class UsersController extends Controller
{
    public function index(): Response
    {
        $r = $this->ctx->request;
        $q = $r->queryStr('q');
        $role = in_array($r->queryStr('role'), ['client', 'provider'], true) ? $r->queryStr('role') : '';
        $status = in_array($r->queryStr('status'), ['active', 'suspended'], true) ? $r->queryStr('status') : '';
        $where = ["u.role <> 'admin'"];
        $p = [];
        if ($q !== '') {
            $where[] = '(u.email LIKE ? OR u.full_name LIKE ?)';
            $p[] = $this->like($q);
            $p[] = $this->like($q);
        }
        if ($role !== '') {
            $where[] = 'u.role = ?';
            $p[] = $role;
        }
        if ($status !== '') {
            $where[] = 'u.status = ?';
            $p[] = $status;
        }
        $w = implode(' AND ', $where);
        $pager = new Pager((int)Database::value("SELECT COUNT(*) FROM users u WHERE $w", $p), $r->queryInt('page'));
        $rows = Database::all("SELECT u.id, u.email, u.full_name, u.role, u.status, u.created_at, u.last_login_at FROM users u WHERE $w ORDER BY u.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}", $p);
        $filters = ['q' => $q, 'role' => $role, 'status' => $status];
        return $this->view('users', 'Users', ['rows' => $rows, 'filters' => $filters, 'pagerHtml' => $this->pagerHtml($pager, '/users', array_filter($filters)), 'returnTo' => $this->path('/users', array_filter($filters + ['page' => $pager->page]))]);
    }

    public function providers(): Response
    {
        $r = $this->ctx->request;
        $q = $r->queryStr('q');
        $ready = $r->queryStr('ready');
        $where = ["u.role = 'provider'"];
        $p = [];
        if ($q !== '') {
            $where[] = '(u.email LIKE ? OR u.full_name LIKE ?)';
            $p[] = $this->like($q);
            $p[] = $this->like($q);
        }
        $complete = "(COALESCE(TRIM(pr.headline),'') <> '' AND COALESCE(TRIM(pr.experience_summary),'') <> '' AND pr.min_rate IS NOT NULL AND (SELECT COUNT(*) FROM provider_categories pc WHERE pc.provider_id = u.id) > 0)";
        $avail = '(SELECT COUNT(*) FROM provider_availability pa WHERE pa.provider_id = u.id) > 0';
        if ($ready === 'yes') {
            $where[] = "u.status = 'active' AND $complete AND $avail";
        } elseif ($ready === 'no') {
            $where[] = "NOT (u.status = 'active' AND $complete AND $avail)";
        }
        $w = implode(' AND ', $where);
        $from = "FROM users u LEFT JOIN user_profiles pr ON pr.user_id = u.id WHERE $w";
        $pager = new Pager((int)Database::value("SELECT COUNT(*) $from", $p), $r->queryInt('page'));
        $rows = Database::all(
            "SELECT u.id, u.email, u.full_name, u.status, pr.headline, pr.min_rate, pr.years_experience,
                    (SELECT COUNT(*) FROM provider_categories pc WHERE pc.provider_id = u.id) AS categories,
                    (SELECT COUNT(*) FROM provider_skills ps WHERE ps.provider_id = u.id) AS skills,
                    (SELECT COUNT(*) FROM provider_availability pa WHERE pa.provider_id = u.id) AS slots,
                    (SELECT COUNT(*) FROM service_assignments a JOIN service_requests sr ON sr.id = a.request_id WHERE a.provider_id = u.id AND sr.status = 'COMPLETED') AS completed,
                    ($complete) AS profile_complete
             $from ORDER BY u.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}",
            $p
        );
        $filters = ['q' => $q, 'ready' => $ready];
        return $this->view('providers', 'Providers', ['rows' => $rows, 'filters' => $filters, 'pagerHtml' => $this->pagerHtml($pager, '/providers', array_filter($filters)), 'returnTo' => $this->path('/providers', array_filter($filters + ['page' => $pager->page]))]);
    }

    /** @param array<string,string> $params */
    public function setStatus(array $params): Response
    {
        $id = (int)$params['id'];
        $to = $this->ctx->request->postStr('status');
        if (!in_array($to, ['active', 'suspended'], true)) {
            throw ApiException::validation(['status' => 'Must be active or suspended.']);
        }
        Database::transaction(function () use ($id, $to): void {
            $u = Database::one('SELECT id, role, status FROM users WHERE id = ? FOR UPDATE', [$id]);
            if ($u === null) {
                throw ApiException::notFound('User');
            }
            if ($u['role'] === 'admin' || $id === $this->adminId()) {
                throw ApiException::forbidden('Administrator accounts cannot be changed here.');
            }
            if ($u['status'] === $to) {
                return;
            }
            Database::run('UPDATE users SET status = ? WHERE id = ?', [$to, $id]);
            if ($to === 'suspended') {
                Database::run('UPDATE mobile_auth_tokens SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [Clock::sql(), $id]);
            }
            AuditLogger::log($this->adminId(), 'admin', $to === 'suspended' ? 'USER_SUSPENDED' : 'USER_REACTIVATED', 'user', $id, ['previous' => $u['status']]);
        });
        return $this->redirectBack('/users', 'User status updated.');
    }
}
