<?php

declare(strict_types=1);

namespace Ancc;

use Ancc\Auth\IitdOAuth;
use Ancc\Auth\Session;
use Ancc\Controllers\AdminUserController;
use Ancc\Controllers\AuthController;
use Ancc\Controllers\DashboardController;
use Ancc\Controllers\HealthController;
use Ancc\Controllers\UserController;
use Ancc\Http\HttpError;
use Ancc\Http\Request;
use Ancc\Http\Response;
use Ancc\Http\Router;
use Ancc\Support\Roles;
use Throwable;

/** Wires everything together and turns a Request into a Response. */
final class App
{
    public readonly Users $users;
    public readonly Session $session;
    public readonly Audit $audit;
    private Router $router;

    public readonly Db $db;

    public function __construct(public readonly Config $config, ?Db $db = null)
    {
        $this->db = $db ?? Db::connect($config); // connects only when a query runs
        $this->users = new Users($this->db);
        $this->audit = new Audit($this->db);
        $this->session = new Session($config, $this->db, $this->users);
        $this->router = $this->routes();
    }

    public static function fromConfig(?string $file = null): self
    {
        return new self(Config::load($file));
    }

    /** Front-controller entry point (public/index.php, or api.php on the server). */
    public static function main(?string $configFile = null): void
    {
        try {
            $app = self::fromConfig($configFile);
        } catch (Throwable $e) {
            error_log('ANCC config error: ' . $e->getMessage());
            Response::json(['detail' => 'Server misconfigured; see the error log'], 500)->send();
            return;
        }
        $app->handle(Request::fromGlobals($app->config->str('BASE_PATH')))->send();
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpError $e) {
            return Response::json(['detail' => $e->detail], $e->status);
        } catch (Throwable $e) {
            error_log('ANCC API error: ' . $e); // goes to Apache's error.log
            return Response::json(['detail' => 'Internal server error'], 500);
        }
    }

    // ---- helpers used by controllers -----------------------------------------------------

    /** @return array<string, mixed> */
    public function requireUser(Request $request): array
    {
        return $this->session->currentUser($request) ?? throw new HttpError(401, 'Not logged in');
    }

    /**
     * Returns the user if they have any of $roles (super_admin always passes); 403 otherwise.
     * @param list<string> $roles
     * @return array<string, mixed>
     */
    public function requireRoles(Request $request, array $roles): array
    {
        $user = $this->requireUser($request);
        if (!Roles::hasAny($user['roles'], $roles)) {
            throw new HttpError(403, "You don't have permission to do this");
        }
        return $user;
    }

    private function routes(): Router
    {
        $r = new Router();
        $auth = new AuthController($this, new IitdOAuth($this->config));
        $users = new UserController($this);
        $dashboard = new DashboardController($this);
        $admin = new AdminUserController($this);
        $health = new HealthController($this);

        $r->add('GET', '/api/v1/health', $health->health(...));

        $r->add('GET', '/api/v1/auth/config', $auth->config(...));
        $r->add('GET', '/api/v1/auth/login', $auth->login(...));
        $r->add('GET', '/api/v1/auth/callback', $auth->callback(...));
        if ($this->config->isDevLogin()) {
            // Never registered in IITD mode, so the endpoint simply doesn't exist in production.
            $r->add('POST', '/api/v1/auth/dev-login', $auth->devLogin(...));
        }
        $r->add('POST', '/api/v1/auth/refresh', $auth->refresh(...));
        $r->add('POST', '/api/v1/auth/logout', $auth->logout(...));
        $r->add('GET', '/api/v1/auth/me', $auth->me(...));

        $r->add('GET', '/api/v1/users/me', $users->me(...));
        $r->add('PATCH', '/api/v1/users/me', $users->updateMe(...));

        $r->add('GET', '/api/v1/dashboard', $dashboard->show(...));

        $r->add('GET', '/api/v1/admin/users', $admin->list(...));
        $r->add('GET', '/api/v1/admin/users/{id}', $admin->show(...));
        $r->add('PATCH', '/api/v1/admin/users/{id}', $admin->update(...));
        $r->add('PUT', '/api/v1/admin/users/{id}/roles', $admin->setRoles(...));

        foreach (self::featureModules() as $module) {
            $module->register($this, $r);
        }
        return $r;
    }

    /**
     * Every src/Features/<Name>/Module.php, in folder-name order. Folders starting with "_" are skipped.
     * @return list<FeatureModule>
     */
    public static function featureModules(): array
    {
        $files = glob(__DIR__ . '/Features/*/Module.php') ?: [];
        sort($files);
        $modules = [];
        foreach ($files as $file) {
            $name = basename(dirname($file));
            if (str_starts_with($name, '_')) {
                continue;
            }
            $class = "Ancc\\Features\\{$name}\\Module";
            $module = new $class();
            if (!$module instanceof FeatureModule) {
                throw new \LogicException("$class must implement Ancc\\FeatureModule");
            }
            $modules[] = $module;
        }
        return $modules;
    }
}
