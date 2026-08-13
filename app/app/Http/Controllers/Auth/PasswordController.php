<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     *
     * @throws AuthenticationException
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ])->validate();

        $user->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return back();
    }
}
