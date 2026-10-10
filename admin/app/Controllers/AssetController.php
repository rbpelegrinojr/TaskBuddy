<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;

/** Serves the single stylesheet (kept outside the web root with the rest of the app code). */
final class AssetController extends Controller
{
    public function css(): Response
    {
        $css = (string)file_get_contents(dirname(__DIR__) . '/Assets/admin.css');
        return new Response(200, $css, ['Content-Type' => 'text/css; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
