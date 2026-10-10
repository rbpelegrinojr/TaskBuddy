<?php
declare(strict_types=1);

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\App;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;

require __DIR__ . '/../vendor/autoload.php';

App::boot();
try {
    $request = Request::fromGlobals();
} catch (ApiException $e) {
    $response = Response::error($e->status, $e->errorCode, $e->getMessage());
    $response->send();
    exit;
}
App::handle($request)->send();
