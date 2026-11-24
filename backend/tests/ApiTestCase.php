<?php

declare(strict_types=1);

namespace Ancc\Tests;

use Ancc\App;
use Ancc\Config;
use Ancc\Db;
use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase
{
    protected static ?Db $db = null;
    protected App $app;
    protected TestClient $client;

    protected function setUp(): void
    {
        self::$db ??= Db::connect(Config::load());
        $this->app = $this->makeApp();
        $this->client = new TestClient($this->app);
    }

    protected function tearDown(): void
    {
        $tables = self::$db->run('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        self::$db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $t) {
            if ($t !== 'schema_migrations') {
                self::$db->pdo()->exec("TRUNCATE TABLE `$t`");
            }
        }
        self::$db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @param array<string, mixed> $overrides */
    protected function makeApp(array $overrides = []): App
    {
        return new App(Config::load(null, $overrides), self::$db);
    }

    protected function newClient(): TestClient
    {
        return new TestClient($this->app);
    }
}
