<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
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

        // The last active administrator can never self-delete: user
        // administration (and the CSV import path) would be bricked.
        if ($user->role === Role::Admin) {
            $otherActiveAdmins = User::query()
                ->where('role', Role::Admin)
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->exists();

            abort_unless($otherActiveAdmins, 403, 'The last active administrator account cannot delete itself.');
        }

        // Log out BEFORE deleting: calling logout afterwards would cycle the
        // remember token and save() the already-deleted model, which Eloquent
        // turns into a re-INSERT of the account.
        Auth::logout();

        $userId = $user->id;
        DB::transaction(function () use ($request, $user, $userId): void {
            // Recorded before the delete: the FK still requires the user
            // row to exist when the log entry is inserted.
            app(AuditLogService::class)->record($userId, 'profile.deleted', 'users', (string) $userId, request: $request);
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
