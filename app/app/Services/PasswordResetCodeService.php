<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

final class PasswordResetCodeService
{
    public const CODE_LIFETIME_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const VERIFIED_LIFETIME_MINUTES = 15;

    public function __construct(
        private readonly PasswordResetCodeMailer $mailer,
    ) {}

    public function issue(string $email): bool
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $resetCode = DB::transaction(function () use ($email, $code): PasswordResetCode {
            PasswordResetCode::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->delete();

            return PasswordResetCode::query()->create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
            ]);
        });

        try {
            $this->mailer->send($email, $code);
        } catch (Throwable $exception) {
            $resetCode->delete();

            throw $exception;
        }

        return true;
    }

    public function verify(string $email, string $code): ?int
    {
        $resetCode = DB::transaction(function () use ($email, $code): ?PasswordResetCode {
            $record = PasswordResetCode::query()
                ->where('email', Str::lower(trim($email)))
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($record === null || $record->expires_at->isPast() || $record->attempts >= self::MAX_ATTEMPTS) {
                return null;
            }

            $record->increment('attempts');

            if (! Hash::check($code, $record->code_hash)) {
                return null;
            }

            $record->forceFill(['verified_at' => now()])->save();

            return $record;
        });

        return $resetCode?->getKey();
    }

    public function canChangePassword(string $email, int $codeId): bool
    {
        $record = PasswordResetCode::query()
            ->whereKey($codeId)
            ->where('email', Str::lower(trim($email)))
            ->whereNull('used_at')
            ->whereNotNull('verified_at')
            ->first();

        if ($record === null || $record->verified_at === null) {
            return false;
        }

        return $record->verified_at->addMinutes(self::VERIFIED_LIFETIME_MINUTES)->isFuture();
    }

    public function resetPassword(string $email, int $codeId, string $password): bool
    {
        $user = DB::transaction(function () use ($email, $codeId, $password): ?User {
            $record = PasswordResetCode::query()
                ->whereKey($codeId)
                ->where('email', Str::lower(trim($email)))
                ->whereNull('used_at')
                ->whereNotNull('verified_at')
                ->lockForUpdate()
                ->first();

            if ($record === null || $record->verified_at === null || $record->verified_at->addMinutes(self::VERIFIED_LIFETIME_MINUTES)->isPast()) {
                return null;
            }

            $user = User::query()->where('email', $record->email)->lockForUpdate()->first();

            if ($user === null) {
                return null;
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $record->forceFill(['used_at' => now()])->save();

            return $user;
        });

        if ($user === null) {
            return false;
        }

        // The password may have been reset precisely because a session was
        // hijacked: purge every existing server-side session for the account
        // (the database session driver makes this trivial).
        DB::table('sessions')->where('user_id', $user->id)->delete();

        event(new PasswordReset($user));

        return true;
    }
}
