<?php

// Apply database migrations:  php bin/migrate.php
// Start over (drops ALL tables first, e.g. to clear the old Python-era preview tables):
//                             php bin/migrate.php --reset

declare(strict_types=1);

require __DIR__ . '/_boot.php';

$db = Ancc\Db::connect(ancc_config());
$migrator = new Ancc\Migrator($db, dirname(__DIR__) . '/migrations');
if (in_array('--reset', $argv, true)) {
    echo "Dropping all tables...\n";
    $migrator->dropAll();
}
try {
    $migrator->migrate(static function (string $msg): void {
        echo $msg, "\n";
    });
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
