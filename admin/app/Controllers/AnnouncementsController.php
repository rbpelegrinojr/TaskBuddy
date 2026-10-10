<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Admin\Support\Pager;
use TaskBuddy\Services\AuditLogger;
use TaskBuddy\Services\NotificationService;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

final class AnnouncementsController extends Controller
{
    public function index(): Response
    {
        $pager = new Pager((int)Database::value('SELECT COUNT(*) FROM announcements'), $this->ctx->request->queryInt('page'));
        $rows = Database::all("SELECT a.id, a.title, a.body, a.audience, a.recipients, a.created_at, u.full_name FROM announcements a JOIN users u ON u.id = a.admin_id ORDER BY a.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset}");
        return $this->view('announcements', 'Announcements', ['rows' => $rows, 'pagerHtml' => $this->pagerHtml($pager, '/announcements', [])]);
    }

    public function send(): Response
    {
        $r = $this->ctx->request;
        $title = $r->postStr('title');
        $body = $r->postStr('body');
        $audience = $r->postStr('audience');
        $errors = [];
        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            $errors['title'] = 'Length must be between 3 and 150 characters.';
        }
        if (mb_strlen($body) < 3 || mb_strlen($body) > 500) {
            $errors['body'] = 'Length must be between 3 and 500 characters.';
        }
        if (!in_array($audience, ['all', 'clients', 'providers'], true)) {
            $errors['audience'] = 'Choose an audience.';
        }
        if ($errors) {
            throw ApiException::validation($errors);
        }
        $roles = ['all' => ['client', 'provider'], 'clients' => ['client'], 'providers' => ['provider']][$audience];
        $count = Database::transaction(function () use ($title, $body, $audience, $roles): int {
            $in = implode(',', array_fill(0, count($roles), '?'));
            $ids = array_column(Database::all("SELECT id FROM users WHERE status = 'active' AND role IN ($in)", $roles), 'id');
            $aid = Database::insert('INSERT INTO announcements (admin_id, title, body, audience, recipients) VALUES (?,?,?,?,?)', [$this->adminId(), $title, $body, $audience, count($ids)]);
            foreach ($ids as $uid) {
                NotificationService::notify((int)$uid, 'ANNOUNCEMENT', $title, $body);
            }
            AuditLogger::log($this->adminId(), 'admin', 'ANNOUNCEMENT_SENT', 'announcement', $aid, ['audience' => $audience, 'recipients' => count($ids)]);
            return count($ids);
        });
        return $this->redirect('/announcements', "Announcement sent to $count active user(s).");
    }
}
