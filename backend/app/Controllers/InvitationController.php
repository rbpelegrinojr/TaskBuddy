<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\InvitationService;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

final class InvitationController extends BaseController
{
    private InvitationService $svc;

    public function __construct()
    {
        $this->svc = new InvitationService();
    }

    public function index(Request $r, array $p): Response
    {
        [$page, $per] = $this->paging($r);
        $rid = isset($r->query['request_id']) && is_numeric($r->query['request_id']) ? (int)$r->query['request_id'] : null;
        $res = $this->svc->list(Auth::id(), Auth::role(), $page, $per, $this->statusFilter($r, ['PENDING', 'ACCEPTED', 'DECLINED', 'EXPIRED', 'WITHDRAWN']), $rid);
        return Response::ok($res['items'], $res['meta']);
    }

    public function accept(Request $r, array $p): Response
    {
        return Response::ok($this->svc->accept(Auth::id(), (int)$p['id']));
    }

    public function decline(Request $r, array $p): Response
    {
        return Response::ok($this->svc->decline(Auth::id(), (int)$p['id']));
    }
}
