<?php

declare(strict_types=1);

namespace Ancc;

use PDO;

/** Thin PDO wrapper that connects on first use. All times are stored and read as UTC. */
final class Db
{
    private ?PDO $pdo = null;

    /** @param \Closure(): PDO $connector */
    public function __construct(private \Closure $connector)
    {
    }

    public static function fromPdo(PDO $pdo): self
    {
        return new self(static fn () => $pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= ($this->connector)();
    }

    public static function connect(Config $c): self
    {
        return new self(static fn () => self::open($c));
    }

    private static function open(Config $c): PDO
    {
        $dsn = $c->str('DB_SOCKET') !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $c->str('DB_SOCKET'), $c->str('DB_NAME'))
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c->str('DB_HOST'), $c->int('DB_PORT'), $c->str('DB_NAME'));
        $pdo = new PDO($dsn, $c->str('DB_USER'), $c->str('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

    /** @param array<string|int, mixed> $params */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string|int, mixed> $params */
    public function scalar(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /** @template T @param callable():T $fn @return T */
    public function transaction(callable $fn): mixed
    {
        if ($this->pdo()->inTransaction()) {
            return $fn(); // already inside a transaction: join it
        }
        $this->pdo()->beginTransaction();
        try {
            $result = $fn();
            $this->pdo()->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo()->rollBack();
            throw $e;
        }
    }

    /** Current UTC time in MySQL DATETIME(6) format. */
    public static function now(string $modify = ''): string
    {
        $dt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($modify !== '') {
            $dt = $dt->modify($modify);
        }
        return $dt->format('Y-m-d H:i:s.u');
    }

    /** MySQL UTC datetime → ISO 8601 for JSON. */
    public static function iso(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP');
    }
}
