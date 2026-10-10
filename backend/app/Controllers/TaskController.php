<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\TaskService;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

final class TaskController extends BaseController
{
    private TaskService $tasks;

    public function __construct()
    {
        $this->tasks = new TaskService();
    }

    public function index(Request $r, array $p): Response
    {
        [$page, $per] = $this->paging($r);
        $res = $this->tasks->list(Auth::id(), Auth::role(), $page, $per, $this->statusFilter($r, array_keys(\TaskBuddy\Services\TaskStateMachine::TRANSITIONS)));
        return Response::ok($res['items'], $res['meta']);
    }

    public function show(Request $r, array $p): Response
    {
        return Response::ok($this->tasks->get((int)$p['id'], Auth::id(), Auth::role()));
    }

    public function submit(Request $r, array $p): Response
    {
        $note = isset($r->body['note']) && is_string($r->body['note']) ? $r->body['note'] : null;
        return Response::created($this->tasks->submit(Auth::id(), (int)$p['id'], $note, $this->uploadedFiles($r)));
    }

    public function startReview(Request $r, array $p): Response
    {
        return Response::ok($this->tasks->startReview(Auth::id(), (int)$p['id']));
    }

    public function approve(Request $r, array $p): Response
    {
        return Response::ok($this->tasks->approve(Auth::id(), (int)$p['id']));
    }

    public function revision(Request $r, array $p): Response
    {
        return Response::ok($this->tasks->requestRevision(Auth::id(), (int)$p['id'], $r->body['reason'] ?? null));
    }

    public function dispute(Request $r, array $p): Response
    {
        return Response::ok($this->tasks->dispute(Auth::id(), Auth::role(), (int)$p['id'], $r->body['reason'] ?? null));
    }

    public function downloadSubmissionFile(Request $r, array $p): Response
    {
        $f = $this->tasks->submissionFileForDownload((int)$p['id'], Auth::id(), Auth::role());
        return Response::file($f['path'], $f['mime'], $f['name']);
    }
}
