<?php

declare(strict_types=1);

// TEMPLATE: copy this folder to src/Features/<YourFeature>/ (PascalCase, no leading "_"),
// then change the namespace below to Ancc\Features\<YourFeature>. It is picked up automatically.

namespace Ancc\Features\_Template;

use Ancc\App;
use Ancc\FeatureModule;
use Ancc\Http\Request;
use Ancc\Http\Response;
use Ancc\Http\Router;

final class Module implements FeatureModule
{
    public function register(App $app, Router $router): void
    {
        // Public endpoint.
        $router->add('GET', '/api/v1/template/hello', static fn (Request $r) => Response::json(['hello' => 'world']));

        // Logged-in users only.
        $router->add('GET', '/api/v1/template/me', static function (Request $r) use ($app): Response {
            $user = $app->requireUser($r);
            return Response::json(['kerberos_id' => $user['kerberos_id']]);
        });

        // Admins with a role (super_admin always passes). Log every admin write with $app->audit.
        $router->add('POST', '/api/v1/admin/template', static function (Request $r) use ($app): Response {
            $app->requireRoles($r, ['coordinator']);
            return Response::json(['ok' => true]);
        });

        // Bigger features: put handlers in a Controller.php next to this file
        // (class Ancc\Features\<YourFeature>\Controller) and register them like the core does in App.php.
    }
}
