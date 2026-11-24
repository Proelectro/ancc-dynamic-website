<?php

declare(strict_types=1);

namespace Ancc\Controllers;

use Ancc\App;
use Ancc\Http\Request;
use Ancc\Http\Response;
use Throwable;

final class HealthController
{
    public function __construct(private App $app)
    {
    }

    public function health(Request $request): Response
    {
        try {
            $this->app->db->scalar('SELECT 1');
            $db = 'ok';
        } catch (Throwable $e) {
            $db = 'error: ' . (new \ReflectionClass($e))->getShortName();
        }
        return Response::json([
            'status' => $db === 'ok' ? 'ok' : 'degraded',
            'db' => $db,
            'env' => $this->app->config->str('ENV'),
            'auth_mode' => $this->app->config->str('AUTH_MODE'),
            'php' => PHP_VERSION,
        ]);
    }
}
