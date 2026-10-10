<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use TaskBuddy\Admin\AdminApp;
use TaskBuddy\Admin\Http\Request;
use TaskBuddy\Admin\Http\Session;
use TaskBuddy\Support\Env;

// Optional URL prefix when mounted below a path (e.g. https://example.com/admin).
$base = rtrim((string)Env::get('ADMIN_BASE_PATH', ''), '/');
$request = Request::fromGlobals($base);
$session = Session::native($request->secure);
AdminApp::handle($request, $session, $base)->send();
