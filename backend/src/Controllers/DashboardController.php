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

    /** Everything the dashboard needs in one request. Sections not built yet say which phase adds them. */
    public function show(Request $request): Response
    {
        $user = $this->app->requireUser($request);
        $missing = [];
        foreach (['phone' => 'phone', 'hostel' => 'hostel'] as $field => $label) {
            if (empty($user[$field])) {
                $missing[] = $label;
            }
        }
        $stub = static fn (string $phase) => ['available' => false, 'phase' => $phase];
        return Response::json([
            'profile' => Users::out($user),
            'profile_missing' => $missing,
            'payouts' => $stub('Soon'),
            'icpc' => $stub('Soon'),
        ]);
    }
}
