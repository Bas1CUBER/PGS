<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class PasswordResetLinkController extends Controller
{
    public function __construct(
        private readonly PasswordResetCodeService $passwordResetCodes,
    ) {}

    /**
     * Display the password reset code request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset code request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ])->validate();

        $email = Str::lower(trim($request->string('email')->toString()));

        try {
            $issued = $this->passwordResetCodes->issue($email);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => ['We could not send a reset code right now. Please try again.'],
            ]);
        }

        $request->session()->put([
            'password_reset_email' => $email,
            'password_reset_verified_code_id' => null,
            'password_reset_verified_at' => null,
        ]);

        if (! $issued) {
            return redirect()->route('password.code')->with('status', 'If an account exists for that email, a reset code has been sent.');
        }

        return redirect()->route('password.code')->with('status', 'We sent a 6-digit reset code to your email.');
    }
}
