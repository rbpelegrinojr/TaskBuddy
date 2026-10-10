<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\MessageService;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

final class MessageController extends BaseController
{
    private MessageService $svc;

    public function __construct()
    {
        $this->svc = new MessageService();
    }

    public function conversations(Request $r, array $p): Response
    {
        return Response::ok($this->svc->conversations(Auth::id()));
    }

    public function messages(Request $r, array $p): Response
    {
        $after = isset($r->query['after_id']) && is_numeric($r->query['after_id']) ? (int)$r->query['after_id'] : null;
        $before = isset($r->query['before_id']) && is_numeric($r->query['before_id']) ? (int)$r->query['before_id'] : null;
        $res = $this->svc->messages((int)$p['id'], Auth::id(), $after, $before, $r->queryInt('limit', 30, 1, 100));
        return Response::ok($res['items'], $res['meta']);
    }

    public function send(Request $r, array $p): Response
    {
        return Response::created($this->svc->send((int)$p['id'], Auth::id(), $r->body));
    }

    public function read(Request $r, array $p): Response
    {
        return Response::ok(['marked_read' => $this->svc->markRead((int)$p['id'], Auth::id())]);
    }
}
