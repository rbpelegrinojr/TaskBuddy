<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\InvitationService;
use TaskBuddy\Services\MatchingService;
use TaskBuddy\Services\RequestService;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

final class RequestController extends BaseController
{
    private RequestService $requests;

    public function __construct()
    {
        $this->requests = new RequestService();
    }

    public function create(Request $r, array $p): Response
    {
        return Response::created($this->requests->create(Auth::id(), $r->body));
    }

    public function index(Request $r, array $p): Response
    {
        [$page, $per] = $this->paging($r);
        $res = $this->requests->list(Auth::id(), Auth::role(), $page, $per, $this->statusFilter($r, array_keys(\TaskBuddy\Services\TaskStateMachine::TRANSITIONS)));
        return Response::ok($res['items'], $res['meta']);
    }

    public function show(Request $r, array $p): Response
    {
        return Response::ok($this->requests->get((int)$p['id'], Auth::id(), Auth::role()));
    }

    public function update(Request $r, array $p): Response
    {
        return Response::ok($this->requests->update(Auth::id(), (int)$p['id'], $r->body));
    }

    public function cancel(Request $r, array $p): Response
    {
        return Response::ok($this->requests->cancel(Auth::id(), (int)$p['id']));
    }

    public function matches(Request $r, array $p): Response
    {
        $id = (int)$p['id'];
        $this->requests->get($id, Auth::id(), 'client'); // ownership check (404 for others)
        [$page, $per] = $this->paging($r, 10);
        $res = (new MatchingService())->results($id, $page, $per);
        return Response::ok($res['items'], $res['meta']);
    }

    public function rematch(Request $r, array $p): Response
    {
        return Response::ok($this->requests->rematch(Auth::id(), (int)$p['id']));
    }

    public function invite(Request $r, array $p): Response
    {
        return Response::created((new InvitationService())->invite(Auth::id(), (int)$p['id'], $r->body));
    }

    public function attach(Request $r, array $p): Response
    {
        $files = $this->uploadedFiles($r, 'file');
        if (count($files) !== 1) {
            throw ApiException::validation(['file' => 'Exactly one file is required.']);
        }
        return Response::created($this->requests->addAttachment(Auth::id(), (int)$p['id'], $files[0]));
    }

    public function downloadAttachment(Request $r, array $p): Response
    {
        $f = $this->requests->attachmentForDownload((int)$p['id'], Auth::id(), Auth::role());
        return Response::file($f['path'], $f['mime'], $f['name']);
    }
}
