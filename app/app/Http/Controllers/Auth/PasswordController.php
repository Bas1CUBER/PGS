<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class PasswordController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

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

        // A credential rotation must kill sessions authenticated with the
        // old password on other devices (cookies signed with the old
        // password hash become invalid).
        Auth::logoutOtherDevices($request->string('password')->toString());

        // Server-side sessions survive logoutOtherDevices: purge them too,
        // except the current one.
        $currentSessionId = $request->session()->getId();
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        $this->audit->record(
            $user->id,
            'profile.password_changed',
            'users',
            (string) $user->id,
            request: $request,
        );

        return back();
    }
}
