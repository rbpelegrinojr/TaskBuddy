<?php
declare(strict_types=1);

/**
 * Applies database/migrations/*.sql in filename order and records them in schema_migrations.
 * Usage: php bin/migrate.php [--fresh]   (--fresh drops all tables first; refuses when APP_ENV=production)
 */

use TaskBuddy\Support\App;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Env;
use TaskBuddy\Support\Paths;

require __DIR__ . '/../vendor/autoload.php';
App::boot();

$pdo = Database::pdo();
if (in_array('--fresh', $argv, true)) {
    if (Env::get('APP_ENV') === 'production') {
        fwrite(STDERR, "Refusing --fresh in production.\n");
        exit(2);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $pdo->exec('DROP TABLE `' . $t . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$done = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(Paths::backend() . '/database/migrations/*.sql') ?: [];
sort($files);
$applied = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        continue;
    }
    $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($file));
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
    $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$name]);
    echo "Applied $name\n";
    $applied++;
}
echo $applied === 0 ? "Database is up to date.\n" : "Applied $applied migration(s).\n";
