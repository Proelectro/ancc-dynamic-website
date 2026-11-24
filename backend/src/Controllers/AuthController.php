<?php

declare(strict_types=1);

namespace Ancc\Controllers;

use Ancc\App;
use Ancc\Auth\IitdOAuth;
use Ancc\Auth\Identity;
use Ancc\Auth\Session;
use Ancc\Http\HttpError;
use Ancc\Http\Request;
use Ancc\Http\Response;
use Ancc\Support\Kerberos;
use Ancc\Support\Lists;
use Ancc\Support\Validate;
use Ancc\Users;
use Throwable;

final class AuthController
{
    public function __construct(private App $app, private IitdOAuth $oauth)
    {
    }

    public function config(Request $request): Response
    {
        return Response::json(['mode' => $this->app->config->str('AUTH_MODE'), 'hostels' => Lists::HOSTELS]);
    }

    public function login(Request $request): Response
    {
        $frontend = $this->app->config->str('FRONTEND_URL');
        if ($this->app->config->isDevLogin()) {
            // There's no IITD server to talk to in dev; send people to the dev login form.
            return Response::redirect("$frontend/login");
        }
        [$state, $signed] = $this->app->session->newState();
        return Response::redirect($this->oauth->authorizeUrl($state))
            ->cookie(Session::STATE_COOKIE, $signed, $this->app->session->cookieOptions('/', 600));
    }

    public function callback(Request $request): Response
    {
        $frontend = $this->app->config->str('FRONTEND_URL');
        $fail = Response::redirect("$frontend/login?error=iitd");
        $code = $request->query['code'] ?? null;
        if ($code === null || !$this->app->session->stateMatches($request->cookie(Session::STATE_COOKIE), $request->query['state'] ?? null)) {
            return $fail;
        }
        try {
            $identity = $this->oauth->fetchIdentity($this->oauth->exchangeCode($code));
        } catch (Throwable $e) {
            error_log('IITD OAuth failed: ' . $e->getMessage());
            return $fail;
        }
        $user = $this->app->users->upsertFromIdentity($identity);
        if (!$user['is_active']) {
            return Response::redirect("$frontend/login?error=inactive");
        }
        $response = Response::redirect("$frontend/auth/callback");
        $this->app->session->start($user, $request, $response);
        $response->cookie(Session::STATE_COOKIE, '', $this->app->session->cookieOptions('/', -3600));
        return $response;
    }

    /** Dev-only login. The route is registered ONLY when AUTH_MODE=dev. */
    public function devLogin(Request $request): Response
    {
        $body = $request->json();
        $kid = is_string($body['kerberos_id'] ?? null) ? Kerberos::normalize($body['kerberos_id']) : '';
        $errors = [];
        if (!Kerberos::isValid($kid)) {
            $errors['kerberos_id'] = Kerberos::HELP;
        }
        [$roles, $roleError] = Validate::roles($body['roles'] ?? []);
        if ($roleError !== null) {
            $errors['roles'] = $roleError;
        }
        if ($errors) {
            throw HttpError::validation($errors);
        }
        $existing = $this->app->users->findByKerberos($kid);
        // Keep an existing user's name unless a new one is passed.
        $name = is_string($body['name'] ?? null) && trim($body['name']) !== ''
            ? trim($body['name'])
            : ($existing['name'] ?? "Dev User $kid");
        $user = $this->app->users->upsertFromIdentity(new Identity($kid, $name, "$kid@iitd.ac.in"));
        $this->app->users->update($user['id'], ['is_active' => 1]);
        $this->app->users->addRoles($user['id'], $roles);
        $user = $this->app->users->find($user['id']);

        $response = Response::json(Users::out($user));
        $this->app->session->start($user, $request, $response);
        return $response;
    }

    public function refresh(Request $request): Response
    {
        $raw = $request->cookie(Session::REFRESH_COOKIE);
        $response = Response::json(null);
        $user = $raw === null ? null : $this->app->session->rotate($raw, $request, $response);
        if ($user === null) {
            $error = Response::json(['detail' => 'Session expired, please log in again'], 401);
            $this->app->session->clear($error);
            return $error;
        }
        $response->body = (string) json_encode(Users::out($user), JSON_UNESCAPED_SLASHES);
        return $response;
    }

    public function logout(Request $request): Response
    {
        $this->app->session->revoke($request->cookie(Session::REFRESH_COOKIE));
        $response = Response::noContent();
        $this->app->session->clear($response);
        return $response;
    }

    public function me(Request $request): Response
    {
        return Response::json(Users::out($this->app->requireUser($request)));
    }
}
