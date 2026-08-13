<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->isAdmin() || $actor->id === $user->id;
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        if ($actor->id === $user->id) {
            return true;
        }

        return $actor->isAdmin();
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->isAdmin() && $actor->id !== $user->id;
    }

    public function changeRole(User $actor, User $user): bool
    {
        return $actor->isAdmin() && $actor->id !== $user->id;
    }

    public function toggleActive(User $actor, User $user): bool
    {
        return $actor->isAdmin() && $actor->id !== $user->id;
    }

    public function updateAccess(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    public function import(User $actor): bool
    {
        return $actor->isAdmin();
    }
}
