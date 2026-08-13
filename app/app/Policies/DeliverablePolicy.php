<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Deliverable;
use App\Models\User;

class DeliverablePolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Deliverable $deliverable): bool
    {
        return $actor->isAdmin()
            || $actor->isFocal()
            || $actor->id === $deliverable->uploaded_by;
    }

    public function create(User $actor): bool
    {
        return true;
    }

    public function update(User $actor, Deliverable $deliverable): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isFocal()) {
            return true;
        }

        return $actor->id === $deliverable->uploaded_by;
    }

    public function delete(User $actor, Deliverable $deliverable): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->id === $deliverable->uploaded_by;
    }
}
