<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\NotificationService;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

final class NotificationController extends BaseController
{
    public function index(Request $r, array $p): Response
    {
        [$page, $per] = $this->paging($r);
        $res = (new NotificationService())->list(Auth::id(), $page, $per, ($r->query['unread'] ?? '') === '1');
        return Response::ok($res['items'], $res['meta']);
    }

    public function markRead(Request $r, array $p): Response
    {
        (new NotificationService())->markRead(Auth::id(), (int)$p['id']);
        return Response::ok(['read' => true]);
    }

    public function markAllRead(Request $r, array $p): Response
    {
        return Response::ok(['marked_read' => (new NotificationService())->markAllRead(Auth::id())]);
    }
}
