<?php

declare(strict_types=1);

namespace Ancc\Tests;

use Ancc\Auth\Jwt;
use Ancc\Config;

final class AuthTest extends ApiTestCase
{
    public function testConfigReportsDevMode(): void
    {
        $r = $this->client->request('GET', '/api/v1/auth/config');
        $this->assertSame(200, $r->status);
        $this->assertSame('dev', $r->data()['mode']);
        $this->assertContains('Girnar', $r->data()['hostels']);
    }

    public function testDevLoginCreatesUserAndSetsCookies(): void
    {
        $me = $this->client->login('cs1220123', [], 'Test Student');
        $this->assertSame('cs1220123', $me['kerberos_id']);
        $this->assertSame('Computer Science and Engineering', $me['department']);
        $this->assertSame([], $me['roles']);
        $this->assertFalse($me['profile_complete']);
        $this->assertArrayHasKey('ancc_access', $this->client->cookies);
        $this->assertArrayHasKey('ancc_refresh', $this->client->cookies);

        $r = $this->client->request('GET', '/api/v1/auth/me');
        $this->assertSame(200, $r->status);
        $this->assertSame('Test Student', $r->data()['name']);
    }

    public function testDevLoginKeepsExistingNameAndAddsRoles(): void
    {
        $this->client->login('cs1220123', [], 'Real Name');
        $me = $this->client->login('cs1220123', ['coordinator']);
        $this->assertSame('Real Name', $me['name']);
        $this->assertSame(['coordinator'], $me['roles']);
    }

    public function testDevLoginRejectsBadInput(): void
    {
        $r = $this->client->request('POST', '/api/v1/auth/dev-login', ['kerberos_id' => 'not-a-kerberos']);
        $this->assertSame(422, $r->status);
        $this->assertSame('Use your IITD kerberos ID, e.g. cs1220123', $r->data()['detail'][0]['msg']);

        $r = $this->client->request('POST', '/api/v1/auth/dev-login', ['kerberos_id' => 'cs1220123', 'roles' => ['wizard']]);
        $this->assertSame(422, $r->status);
        $this->assertSame('Unknown roles: wizard', $r->data()['detail'][0]['msg']);
    }

    public function testMeRequiresLogin(): void
    {
        $this->assertSame(401, $this->client->request('GET', '/api/v1/auth/me')->status);
    }

    public function testTamperedAccessTokenIsRejected(): void
    {
        $this->client->login('cs1220123');
        $this->client->cookies['ancc_access'] .= 'x';
        $this->assertSame(401, $this->client->request('GET', '/api/v1/auth/me')->status);
    }

    public function testRefreshRotatesAndOldTokenIsRevoked(): void
    {
        $this->client->login('cs1220123');
        $old = $this->client->cookies['ancc_refresh'];
        $r = $this->client->request('POST', '/api/v1/auth/refresh');
        $this->assertSame(200, $r->status);
        $this->assertSame('cs1220123', $r->data()['kerberos_id']);
        $this->assertNotSame($old, $this->client->cookies['ancc_refresh']);

        $this->client->cookies = ['ancc_refresh' => $old];
        $r = $this->client->request('POST', '/api/v1/auth/refresh');
        $this->assertSame(401, $r->status);
    }

    public function testLogoutClearsSession(): void
    {
        $this->client->login('cs1220123');
        $refresh = $this->client->cookies['ancc_refresh'];
        $r = $this->client->request('POST', '/api/v1/auth/logout');
        $this->assertSame(204, $r->status);
        $this->assertSame(401, $this->client->request('GET', '/api/v1/auth/me')->status);
        // The refresh token is revoked on the server too.
        $this->client->cookies = ['ancc_refresh' => $refresh];
        $this->assertSame(401, $this->client->request('POST', '/api/v1/auth/refresh')->status);
    }

    public function testCookiesAreScopedToBasePath(): void
    {
        $client = new TestClient($this->makeApp(['BASE_PATH' => '/new', 'COOKIE_SECURE' => true]));
        $r = $client->request('POST', '/api/v1/auth/dev-login', ['kerberos_id' => 'cs1220123']);
        $paths = [];
        foreach ($r->cookies as $c) {
            $paths[$c['name']] = $c['options']['path'];
            $this->assertTrue($c['options']['secure']);
            $this->assertTrue($c['options']['httponly']);
        }
        $this->assertSame(['ancc_access' => '/new', 'ancc_refresh' => '/new/api/v1/auth'], $paths);
    }

    public function testProductionRefusesDevLogin(): void
    {
        $this->expectException(\RuntimeException::class);
        Config::load(null, ['ENV' => 'production', 'AUTH_MODE' => 'dev', 'JWT_SECRET' => str_repeat('x', 40)]);
    }

    public function testStagingRequiresARealSecret(): void
    {
        $this->expectException(\RuntimeException::class);
        Config::load(null, ['ENV' => 'staging', 'JWT_SECRET' => Config::DEFAULT_SECRET]);
    }

    public function testIitdModeHasNoDevLoginAndRedirectsToIitd(): void
    {
        $client = new TestClient($this->makeApp(['AUTH_MODE' => 'iitd']));
        $r = $client->request('POST', '/api/v1/auth/dev-login', ['kerberos_id' => 'cs1220123']);
        $this->assertSame(404, $r->status);

        $r = $client->request('GET', '/api/v1/auth/login');
        $this->assertSame(302, $r->status);
        $this->assertStringStartsWith('https://oauth2.iitd.ac.in/authorize.php?', $r->headers['Location']);
        $this->assertStringContainsString('state=', $r->headers['Location']);
        $this->assertArrayHasKey('ancc_oauth_state', $client->cookies);
    }

    public function testCallbackRejectsForgedState(): void
    {
        $r = $this->client->request('GET', '/api/v1/auth/callback', null, ['code' => 'abc', 'state' => 'forged']);
        $this->assertSame(302, $r->status);
        $this->assertStringEndsWith('/login?error=iitd', $r->headers['Location']);
    }

    public function testJwtRejectsWrongTypeAndExpiry(): void
    {
        $secret = 'k';
        $token = Jwt::encode(['sub' => 'x', 'type' => 'state', 'exp' => time() + 60], $secret);
        $this->assertNull(Jwt::decode($token, $secret, 'access'));
        $this->assertNotNull(Jwt::decode($token, $secret, 'state'));
        $expired = Jwt::encode(['sub' => 'x', 'type' => 'access', 'exp' => time() - 1], $secret);
        $this->assertNull(Jwt::decode($expired, $secret, 'access'));
    }

    public function testUnknownRouteAndMethod(): void
    {
        $this->assertSame(404, $this->client->request('GET', '/api/v1/nope')->status);
        $this->assertSame(405, $this->client->request('DELETE', '/api/v1/auth/me')->status);
    }
}
