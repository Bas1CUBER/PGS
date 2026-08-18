<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Response;

final class RegisteredUserController extends Controller
{
    /**
     * Registration is disabled — users are created by admins only.
     */
    public function create(): Response
    {
        abort(404);
    }

    public function store(): never
    {
        abort(404);
    }
}
