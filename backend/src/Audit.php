<?php

declare(strict_types=1);

namespace Ancc;

use Ancc\Support\Uuid;

/** Every admin write is recorded with who did it and what changed. */
final class Audit
{
    public function __construct(private Db $db)
    {
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function log(
        ?string $actorId,
        string $action,
        string $entityType,
        string $entityId,
        ?array $before = null,
        ?array $after = null,
        ?string $ip = null,
    ): void {
        $this->db->run(
            'INSERT INTO audit_logs (id, actor_id, action, entity_type, entity_id, `before`, `after`, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Uuid::v4(), $actorId, $action, $entityType, $entityId,
                $before === null ? null : json_encode($before),
                $after === null ? null : json_encode($after),
                $ip, Db::now(),
            ]
        );
    }
}
