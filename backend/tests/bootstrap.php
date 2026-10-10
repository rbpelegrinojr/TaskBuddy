<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');
// Integration tests read their database settings from the environment (never from a committed file):
//   TB_TEST_DB_HOST, TB_TEST_DB_PORT, TB_TEST_DB_NAME, TB_TEST_DB_USER, TB_TEST_DB_PASS

// Test classes are autoloaded here as well so the suite also runs when PHPUnit comes from a system package
// (composer's autoload-dev map is only generated when dev dependencies are installed through composer).
spl_autoload_register(static function (string $class): void {
    $prefix = 'TaskBuddy\\Tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
