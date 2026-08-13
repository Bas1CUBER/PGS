<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeadlineControl;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class DeadlineService
{
    /**
     * Reject a submission when the actor's role deadline has passed.
     *
     * @throws ValidationException
     */
    public function enforce(User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $deadline = Cache::remember(
            "pgs_deadline_{$user->role->value}",
            60,
            fn (): ?DeadlineControl => DeadlineControl::query()->find($user->role->value),
        );

        if ($deadline !== null && ! $deadline->isOpen()) {
            throw ValidationException::withMessages([
                'deadline' => [$deadline->message ?? 'The submission deadline has passed.'],
            ]);
        }
    }
}
