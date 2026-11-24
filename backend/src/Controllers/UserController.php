<?php

declare(strict_types=1);

namespace Ancc\Controllers;

use Ancc\App;
use Ancc\Http\HttpError;
use Ancc\Http\Request;
use Ancc\Http\Response;
use Ancc\Support\Validate;
use Ancc\Users;

final class UserController
{
    public function __construct(private App $app)
    {
    }

    public function me(Request $request): Response
    {
        return Response::json(Users::out($this->app->requireUser($request)));
    }

    /** Members edit their own contact details. Name, kerberos ID, department and IITD email come from IITD. */
    public function updateMe(Request $request): Response
    {
        $user = $this->app->requireUser($request);
        [$clean, $errors] = Validate::profile($request->json());
        if ($errors) {
            throw HttpError::validation($errors);
        }
        $this->app->users->update($user['id'], $clean);
        return Response::json(Users::out($this->app->users->find($user['id'])));
    }
}
