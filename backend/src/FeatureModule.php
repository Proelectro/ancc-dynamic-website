<?php

declare(strict_types=1);

namespace Ancc;

use Ancc\Http\Router;

/**
 * A feature plugs itself into the app: put it in src/Features/<Name>/Module.php as class
 * Ancc\Features\<Name>\Module implementing this interface. App finds it automatically, so adding a
 * feature never means editing a shared file. Folders starting with "_" (like _Template) are skipped.
 */
interface FeatureModule
{
    /** Add this feature's API routes, e.g. $router->add('GET', '/api/v1/events', $controller->list(...)). */
    public function register(App $app, Router $router): void;
}
