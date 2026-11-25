<?php

declare(strict_types=1);

namespace Ancc\Controllers;

use Ancc\App;
use Ancc\Http\Request;
use Ancc\Http\Response;
use Ancc\Users;

final class DashboardController
{
    public function __construct(private App $app)
    {
    }

    /**
     * The profile part of the dashboard. Features show their own dashboard cards and fetch their own
     * data (see frontend/src/features), so new features don't need to change this endpoint.
     */
    public function show(Request $request): Response
    {
        $user = $this->app->requireUser($request);
        $missing = [];
        foreach (['phone' => 'phone', 'hostel' => 'hostel'] as $field => $label) {
            if (empty($user[$field])) {
                $missing[] = $label;
            }
        }
        return Response::json([
            'profile' => Users::out($user),
            'profile_missing' => $missing,
        ]);
    }
}
