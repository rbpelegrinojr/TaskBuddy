<?php
declare(strict_types=1);

/**
 * Shared bootstrap: the dashboard reuses the backend autoloader, .env and business services
 * (matching, task state machine, payments, audit logging) so rules are never duplicated.
 */

$backend = getenv('TASKBUDDY_BACKEND_PATH') ?: dirname(__DIR__, 2) . '/backend';
$autoload = $backend . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Backend autoloader not found. Run "composer install" in the backend directory.');
}
require $autoload;

spl_autoload_register(static function (string $class): void {
    $prefix = 'TaskBuddy\\Admin\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

\TaskBuddy\Support\Env::load($backend . '/.env');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
