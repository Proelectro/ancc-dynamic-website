<?php

declare(strict_types=1);

// Tests use their own database (ancc_test by default) and ignore your local config.php.
putenv('ANCC_CONFIG=' . __DIR__ . '/no-config-file.php');
putenv('ANCC_ENV=test');
putenv('ANCC_AUTH_MODE=dev');
putenv('ANCC_JWT_SECRET=test-secret-that-is-at-least-32-bytes-long');
if (getenv('ANCC_DB_NAME') === false) {
    putenv('ANCC_DB_NAME=ancc_test');
}

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/TestClient.php';
require __DIR__ . '/ApiTestCase.php';

// Fresh schema once per run.
$migrator = new Ancc\Migrator(Ancc\Db::connect(Ancc\Config::load()), dirname(__DIR__) . '/migrations');
$migrator->dropAll();
$migrator->migrate();
