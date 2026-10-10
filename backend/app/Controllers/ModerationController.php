<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\ModerationService;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

final class ModerationController extends BaseController
{
    public function block(Request $r, array $p): Response
    {
        (new ModerationService())->block(Auth::id(), (int)$p['id']);
        return Response::ok(['blocked' => true]);
    }

    public function unblock(Request $r, array $p): Response
    {
        (new ModerationService())->unblock(Auth::id(), (int)$p['id']);
        return Response::ok(['blocked' => false]);
    }

    public function report(Request $r, array $p): Response
    {
        return Response::created((new ModerationService())->report(Auth::id(), $r->body));
    }
}
