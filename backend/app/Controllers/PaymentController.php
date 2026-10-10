<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\PaymentService;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

final class PaymentController extends BaseController
{
    public function index(Request $r, array $p): Response
    {
        [$page, $per] = $this->paging($r);
        $res = (new PaymentService())->list(Auth::id(), Auth::role(), $page, $per, $this->statusFilter($r, ['PENDING', 'COMPLETED', 'CANCELLED', 'ON_HOLD']));
        return Response::ok($res['items'], $res['meta']);
    }

    public function show(Request $r, array $p): Response
    {
        return Response::ok((new PaymentService())->get(Auth::id(), (int)$p['id']));
    }
}
