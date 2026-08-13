<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     *
     * @throws AuthenticationException
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => true, // User implements MustVerifyEmail unconditionally
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * @throws AuthenticationException
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     *
     * @throws AuthenticationException|ValidationException
     */
    public function destroy(Request $request): RedirectResponse
    {
        Validator::make($request->all(), [
            'password' => ['required', 'current_password'],
        ])->validate();

        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
