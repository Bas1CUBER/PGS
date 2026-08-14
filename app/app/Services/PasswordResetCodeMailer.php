<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\PasswordResetCodeMail;
use Illuminate\Support\Facades\Mail;

final class PasswordResetCodeMailer
{
    public function send(string $email, string $code): void
    {
        Mail::to($email)->send(new PasswordResetCodeMail($code));
    }
}
