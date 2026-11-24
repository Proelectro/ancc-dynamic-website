<?php

declare(strict_types=1);

namespace Ancc\Tests;

use Ancc\Http\Request;
use Ancc\Support\Kerberos;

final class UsersTest extends ApiTestCase
{
    public function testUpdateProfileAndDashboard(): void
    {
        $this->client->login('cs1220123');
        $d = $this->client->request('GET', '/api/v1/dashboard')->data();
        $this->assertSame(['phone', 'hostel'], $d['profile_missing']);

        $r = $this->client->request('PATCH', '/api/v1/users/me', [
            'phone' => '+91 98100 12345', 'hostel' => 'Girnar', 'codeforces' => 'tourist',
        ]);
        $this->assertSame(200, $r->status, $r->body);
        $this->assertTrue($r->data()['profile_complete']);
        $this->assertSame('tourist', $r->data()['codeforces']);

        $d = $this->client->request('GET', '/api/v1/dashboard')->data();
        $this->assertSame([], $d['profile_missing']);
        $this->assertSame(['available' => false, 'phase' => 'Phase 3'], $d['eca']);
    }

    public function testProfileValidationAndReadOnlyFields(): void
    {
        $this->client->login('cs1220123');
        $this->assertSame(422, $this->client->request('PATCH', '/api/v1/users/me', ['hostel' => 'Hogwarts'])->status);
        $this->assertSame(422, $this->client->request('PATCH', '/api/v1/users/me', ['phone' => '12'])->status);
        $this->assertSame(422, $this->client->request('PATCH', '/api/v1/users/me', ['alt_email' => 'nope'])->status);
        // IITD-owned fields are ignored.
        $r = $this->client->request('PATCH', '/api/v1/users/me', ['name' => 'Hacker', 'kerberos_id' => 'xx']);
        $this->assertNotSame('Hacker', $r->data()['name']);
        // Empty string clears a field.
        $this->client->request('PATCH', '/api/v1/users/me', ['codeforces' => 'abc']);
        $r = $this->client->request('PATCH', '/api/v1/users/me', ['codeforces' => '']);
        $this->assertNull($r->data()['codeforces']);
    }

    public function testMemberCannotUseAdmin(): void
    {
        $this->client->login('cs1220123');
        $this->assertSame(403, $this->client->request('GET', '/api/v1/admin/users')->status);
    }

    public function testAdminSearchFiltersAndRoles(): void
    {
        $target = $this->newClient()->login('ee1230002', [], 'Diya Patel');
        $this->client->login('cs1210007', ['super_admin'], 'Admin');

        $r = $this->client->request('GET', '/api/v1/admin/users', null, ['q' => 'diya']);
        $this->assertSame(1, $r->data()['total']);

        $r = $this->client->request('PUT', "/api/v1/admin/users/{$target['id']}/roles", ['roles' => ['coordinator']]);
        $this->assertSame(200, $r->status, $r->body);
        $this->assertSame(['coordinator'], $r->data()['roles']);

        $r = $this->client->request('GET', '/api/v1/admin/users', null, ['role' => 'coordinator']);
        $this->assertSame(['ee1230002'], array_column($r->data()['items'], 'kerberos_id'));

        $r = $this->client->request('GET', '/api/v1/admin/users', null, ['role' => 'member']);
        $this->assertSame([], array_column($r->data()['items'], 'kerberos_id'));

        $r = $this->client->request('PUT', "/api/v1/admin/users/{$target['id']}/roles", ['roles' => ['wizard']]);
        $this->assertSame(422, $r->status);

        $audit = self::$db->all("SELECT action, entity_id FROM audit_logs");
        $this->assertSame([['action' => 'role.set', 'entity_id' => $target['id']]], $audit);
    }

    public function testPaginationAndBadParams(): void
    {
        $this->client->login('cs1210007', ['super_admin']);
        foreach (range(1, 3) as $i) {
            $this->newClient()->login("ee123000$i");
        }
        $r = $this->client->request('GET', '/api/v1/admin/users', null, ['page_size' => '2', 'page' => '2']);
        $this->assertSame(4, $r->data()['total']);
        $this->assertCount(2, $r->data()['items']);
        $this->assertSame(422, $this->client->request('GET', '/api/v1/admin/users', null, ['page_size' => '500'])->status);
        $this->assertSame(404, $this->client->request('GET', '/api/v1/admin/users/not-a-uuid')->status);
    }

    public function testCoordinatorCannotGrantRolesOrDeactivate(): void
    {
        $target = $this->newClient()->login('ee1230002');
        $this->client->login('cs5210004', ['coordinator']);
        $this->assertSame(200, $this->client->request('GET', "/api/v1/admin/users/{$target['id']}")->status);
        $r = $this->client->request('PUT', "/api/v1/admin/users/{$target['id']}/roles", ['roles' => ['super_admin']]);
        $this->assertSame(403, $r->status);
        $r = $this->client->request('PATCH', "/api/v1/admin/users/{$target['id']}", ['is_active' => false]);
        $this->assertSame(403, $r->status);
    }

    public function testFinanceCanMarkBankDetailsButNotDeactivate(): void
    {
        $target = $this->newClient()->login('ee1230002');
        $this->client->login('ch1210006', ['finance']);
        $r = $this->client->request('PATCH', "/api/v1/admin/users/{$target['id']}", ['bank_details_on_file' => true]);
        $this->assertSame(200, $r->status, $r->body);
        $r = $this->client->request('PATCH', "/api/v1/admin/users/{$target['id']}", ['is_active' => false]);
        $this->assertSame(403, $r->status);
    }

    public function testDeactivatedUserIsLoggedOut(): void
    {
        $student = $this->newClient();
        $target = $student->login('ee1230002');
        $this->client->login('cs1210007', ['super_admin']);
        $r = $this->client->request('PATCH', "/api/v1/admin/users/{$target['id']}", ['is_active' => false]);
        $this->assertSame(200, $r->status, $r->body);
        $this->assertFalse($r->data()['is_active']);
        $this->assertSame(401, $student->request('GET', '/api/v1/auth/me')->status);
        $this->assertSame(401, $student->request('POST', '/api/v1/auth/refresh')->status);
    }

    public function testAdminCannotRemoveOwnSuperAdminOrDeactivateSelf(): void
    {
        $me = $this->client->login('cs1210007', ['super_admin']);
        $this->assertSame(400, $this->client->request('PUT', "/api/v1/admin/users/{$me['id']}/roles", ['roles' => []])->status);
        $this->assertSame(400, $this->client->request('PATCH', "/api/v1/admin/users/{$me['id']}", ['is_active' => false])->status);
    }

    public function testRequestStripsBasePath(): void
    {
        $_SERVER['REQUEST_URI'] = '/new/api/v1/auth/me?x=1';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertSame('/api/v1/auth/me', Request::fromGlobals('/new')->path);
        $this->assertSame('/new/api/v1/auth/me', Request::fromGlobals('')->path);
    }

    public function testYearOfStudy(): void
    {
        $nov2026 = new \DateTimeImmutable('2026-11-15');
        $this->assertSame(3, Kerberos::yearOfStudy(2024, $nov2026));
        $this->assertSame(1, Kerberos::yearOfStudy(2026, $nov2026));
        $this->assertSame(2, Kerberos::yearOfStudy(2024, new \DateTimeImmutable('2026-06-30')));
        $this->assertSame(['department' => 'Electrical Engineering', 'entry_year' => 2025], Kerberos::parse('EE1250002'));
    }
}
