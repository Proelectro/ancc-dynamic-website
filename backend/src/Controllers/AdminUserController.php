<?php

declare(strict_types=1);

namespace Ancc\Controllers;

use Ancc\App;
use Ancc\Http\HttpError;
use Ancc\Http\Request;
use Ancc\Http\Response;
use Ancc\Support\Roles;
use Ancc\Support\Validate;
use Ancc\Users;

final class AdminUserController
{
    public function __construct(private App $app)
    {
    }

    public function list(Request $request): Response
    {
        $this->app->requireRoles($request, Roles::ANY_ADMIN);
        $page = self::intParam($request->query['page'] ?? '1', 'page', 1, PHP_INT_MAX);
        $pageSize = self::intParam($request->query['page_size'] ?? '20', 'page_size', 1, 100);
        $result = $this->app->users->search(
            $request->query['q'] ?? null,
            $request->query['hostel'] ?? null,
            $request->query['role'] ?? null,
            $page,
            $pageSize,
        );
        return Response::json([
            'items' => array_map([Users::class, 'out'], $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function show(Request $request): Response
    {
        $this->app->requireRoles($request, Roles::ANY_ADMIN);
        return Response::json(Users::out($this->find($request->params['id'])));
    }

    /** Deactivate/reactivate (super_admin) or mark bank details on file (finance). */
    public function update(Request $request): Response
    {
        $actor = $this->app->requireRoles($request, [Roles::SUPER_ADMIN, Roles::FINANCE]);
        $user = $this->find($request->params['id']);
        $body = $request->json();
        $changes = [];
        foreach (['is_active', 'bank_details_on_file'] as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            if (!is_bool($body[$field])) {
                throw HttpError::validation([$field => 'Must be true or false']);
            }
            $changes[$field] = $body[$field];
        }
        if (array_key_exists('is_active', $changes) && !in_array(Roles::SUPER_ADMIN, $actor['roles'], true)) {
            throw new HttpError(403, 'Only super_admin can deactivate users');
        }
        if (($changes['is_active'] ?? true) === false && $user['id'] === $actor['id']) {
            throw new HttpError(400, "You can't deactivate yourself");
        }
        $before = [];
        foreach ($changes as $k => $_) {
            $before[$k] = (bool) $user[$k];
        }
        $this->app->db->transaction(function () use ($user, $changes, $actor, $before, $request): void {
            $this->app->users->update($user['id'], array_map(static fn (bool $v) => (int) $v, $changes));
            $this->app->audit->log($actor['id'], 'user.update', 'user', $user['id'], $before, $changes, $request->ip);
        });
        return Response::json(Users::out($this->find($user['id'])));
    }

    public function setRoles(Request $request): Response
    {
        $actor = $this->app->requireRoles($request, [Roles::SUPER_ADMIN]);
        [$roles, $error] = Validate::roles($request->json()['roles'] ?? []);
        if ($error !== null) {
            throw HttpError::validation(['roles' => $error]);
        }
        $user = $this->find($request->params['id']);
        if ($user['id'] === $actor['id'] && !in_array(Roles::SUPER_ADMIN, $roles, true)) {
            throw new HttpError(400, "You can't remove your own super_admin role");
        }
        $this->app->db->transaction(function () use ($user, $roles, $actor, $request): void {
            $this->app->users->setRoles($user['id'], $roles, $actor['id']);
            $this->app->audit->log($actor['id'], 'role.set', 'user', $user['id'],
                ['roles' => $user['roles']], ['roles' => $roles], $request->ip);
        });
        return Response::json(Users::out($this->find($user['id'])));
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        return $this->app->users->find($id) ?? throw new HttpError(404, 'User not found');
    }

    private static function intParam(string $value, string $name, int $min, int $max): int
    {
        if (!preg_match('/^\d+$/', $value) || (int) $value < $min || (int) $value > $max) {
            throw new HttpError(422, [['loc' => ['query', $name], 'msg' => "$name must be between $min and $max"]]);
        }
        return (int) $value;
    }
}
