<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class PasswordResetCodeController extends Controller
{
    public function __construct(
        private readonly PasswordResetCodeService $passwordResetCodes,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/VerifyResetCode', [
            'email' => $this->maskEmail($email),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('password.request');
        }

        Validator::make($request->all(), [
            'code' => ['required', 'digits:6'],
        ])->validate();

        $verifiedCodeId = $this->passwordResetCodes->verify($email, $request->string('code')->toString());

        if (! is_int($verifiedCodeId)) {
            throw ValidationException::withMessages([
                'code' => ['That code is invalid or expired. Request a new code and try again.'],
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put([
            'password_reset_email' => $email,
            'password_reset_verified_code_id' => $verifiedCodeId,
            'password_reset_verified_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('password.change');
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('password.request');
        }

        try {
            $issued = $this->passwordResetCodes->issue($email);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'code' => ['We could not send a new code right now. Please try again.'],
            ]);
        }

        return back()->with('status', $issued
            ? 'A new 6-digit reset code was sent.'
            : 'If an account exists for that email, a new reset code has been sent.');
    }

    public function change(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');
        $codeId = $request->session()->get('password_reset_verified_code_id');

        if (! is_string($email) || ! is_int($codeId) && ! is_numeric($codeId)) {
            return redirect()->route('password.code');
        }

        if (! $this->passwordResetCodes->canChangePassword($email, (int) $codeId)) {
            $request->session()->forget(['password_reset_verified_code_id', 'password_reset_verified_at']);

            return redirect()->route('password.code')->withErrors([
                'code' => 'Your verification expired. Request a new code.',
            ]);
        }

        return Inertia::render('Auth/ChangePassword', [
            'email' => $this->maskEmail($email),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $email = $request->session()->get('password_reset_email');
        $codeId = $request->session()->get('password_reset_verified_code_id');

        if (! is_string($email) || ! is_int($codeId) && ! is_numeric($codeId)) {
            return redirect()->route('password.code');
        }

        Validator::make($request->all(), [
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ])->validate();

        if (! $this->passwordResetCodes->resetPassword($email, (int) $codeId, $request->string('password')->toString())) {
            throw ValidationException::withMessages([
                'password' => ['Your reset session expired. Request a new code and try again.'],
            ]);
        }

        $request->session()->forget([
            'password_reset_email',
            'password_reset_verified_code_id',
            'password_reset_verified_at',
        ]);

        return redirect()->route('login')->with('status', 'Your password has been updated. You can sign in now.');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return $email;
        }

        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('*', max(1, mb_strlen($local) - 1)).'@'.$domain;
    }
}
