<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC');
// Integration tests read their database settings from the environment (never from a committed file):
//   TB_TEST_DB_HOST, TB_TEST_DB_PORT, TB_TEST_DB_NAME, TB_TEST_DB_USER, TB_TEST_DB_PASS
