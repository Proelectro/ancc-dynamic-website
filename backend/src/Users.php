<?php

declare(strict_types=1);

namespace Ancc;

use Ancc\Auth\Identity;
use Ancc\Support\Kerberos;
use Ancc\Support\Uuid;

/** Database access for users and their roles. Rows are plain arrays with a `roles` list added. */
final class Users
{
    public function __construct(private Db $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        if (!Uuid::isValid($id)) {
            return null;
        }
        return $this->withRoles($this->db->one('SELECT * FROM users WHERE id = ?', [$id]));
    }

    /** @return array<string, mixed>|null */
    public function findByKerberos(string $kerberosId): ?array
    {
        $row = $this->db->one('SELECT * FROM users WHERE kerberos_id = ?', [Kerberos::normalize($kerberosId)]);
        return $this->withRoles($row);
    }

    /**
     * First login creates the user (this is sign-up); later logins refresh IITD-owned fields.
     * @return array<string, mixed>
     */
    public function upsertFromIdentity(Identity $ident): array
    {
        $kid = Kerberos::normalize($ident->kerberosId);
        $parsed = Kerberos::parse($kid);
        $department = $ident->department ?? $parsed['department'];
        $year = Kerberos::yearOfStudy($parsed['entry_year']);
        $now = Db::now();
        $existing = $this->db->one('SELECT id FROM users WHERE kerberos_id = ?', [$kid]);
        if ($existing === null) {
            $this->db->run(
                'INSERT INTO users (id, kerberos_id, name, iitd_email, department, year_of_study,
                    last_login_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [Uuid::v4(), $kid, $ident->name, $ident->email, $department, $year, $now, $now, $now]
            );
        } else {
            $this->db->run(
                'UPDATE users SET name = ?, iitd_email = ?, department = ?, year_of_study = ?,
                    last_login_at = ?, updated_at = ? WHERE id = ?',
                [$ident->name, $ident->email, $department, $year, $now, $now, $existing['id']]
            );
        }
        return $this->findByKerberos($kid) ?? throw new \LogicException('user vanished');
    }

    /** @param list<string> $roles */
    public function addRoles(string $userId, array $roles, ?string $grantedBy = null): void
    {
        foreach ($roles as $role) {
            $this->db->run(
                'INSERT IGNORE INTO user_roles (id, user_id, role, granted_by, granted_at) VALUES (?, ?, ?, ?, ?)',
                [Uuid::v4(), $userId, $role, $grantedBy, Db::now()]
            );
        }
    }

    /** @param list<string> $roles */
    public function setRoles(string $userId, array $roles, string $grantedBy): void
    {
        $this->db->transaction(function () use ($userId, $roles, $grantedBy): void {
            if ($roles) {
                $marks = implode(',', array_fill(0, count($roles), '?'));
                $this->db->run("DELETE FROM user_roles WHERE user_id = ? AND role NOT IN ($marks)", [$userId, ...$roles]);
            } else {
                $this->db->run('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
            }
            $this->addRoles($userId, $roles, $grantedBy);
        });
    }

    /** @param array<string, mixed> $fields column => value (columns are whitelisted by callers) */
    public function update(string $userId, array $fields): void
    {
        if (!$fields) {
            return;
        }
        $sets = implode(', ', array_map(static fn ($k) => "`$k` = ?", array_keys($fields)));
        $this->db->run("UPDATE users SET $sets, updated_at = ? WHERE id = ?", [...array_values($fields), Db::now(), $userId]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(?string $q, ?string $hostel, ?string $role, int $page, int $pageSize): array
    {
        $where = [];
        $params = [];
        if ($q !== null && trim($q) !== '') {
            $like = '%' . strtolower(trim($q)) . '%';
            $where[] = '(LOWER(u.name) LIKE ? OR u.kerberos_id LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($hostel !== null && $hostel !== '') {
            $where[] = 'u.hostel = ?';
            $params[] = $hostel;
        }
        if ($role === 'member') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM user_roles r WHERE r.user_id = u.id)';
        } elseif ($role !== null && $role !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles r WHERE r.user_id = u.id AND r.role = ?)';
            $params[] = $role;
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM users u $sqlWhere", $params);
        $offset = ($page - 1) * $pageSize;
        $rows = $this->db->all(
            "SELECT u.* FROM users u $sqlWhere ORDER BY u.name LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn ($r) => $this->withRoles($r), $rows), 'total' => $total];
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    private function withRoles(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $roles = $this->db->run('SELECT role FROM user_roles WHERE user_id = ? ORDER BY role', [$row['id']])
            ->fetchAll(\PDO::FETCH_COLUMN);
        $row['roles'] = array_values(array_map('strval', $roles));
        return $row;
    }

    /**
     * The public JSON shape of a user (same as the old UserOut schema).
     * @param array<string, mixed> $u
     * @return array<string, mixed>
     */
    public static function out(array $u): array
    {
        return [
            'id' => $u['id'],
            'kerberos_id' => $u['kerberos_id'],
            'name' => $u['name'],
            'iitd_email' => $u['iitd_email'],
            'department' => $u['department'],
            'year_of_study' => $u['year_of_study'] === null ? null : (int) $u['year_of_study'],
            'phone' => $u['phone'],
            'alt_email' => $u['alt_email'],
            'hostel' => $u['hostel'],
            'codeforces' => $u['codeforces'],
            'codechef' => $u['codechef'],
            'avatar_url' => $u['avatar_url'],
            'is_active' => (bool) $u['is_active'],
            'profile_complete' => !empty($u['phone']) && !empty($u['hostel']),
            'roles' => $u['roles'],
            'last_login_at' => Db::iso($u['last_login_at']),
        ];
    }
}
