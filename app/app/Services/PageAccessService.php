<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class PageAccessService
{
    /** @var list<string> */
    public const MODULES = [
        'roadmaps',
        'scorecard',
        'performance_assessment',
        'cascading',
        'governance',
    ];

    public function can(?User $user, string $module): bool
    {
        if (! $user instanceof User || ! in_array($module, self::MODULES, true)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->all($user)[$module] ?? false;
    }

    public function hasMatrix(User $user): bool
    {
        return $user->pageAccess()->exists();
    }

    /**
     * @return array<string, bool>
     */
    public function all(User $user): array
    {
        if ($user->isAdmin()) {
            return array_fill_keys(self::MODULES, true);
        }

        return Cache::remember(
            "pgs_access_{$user->id}",
            60,
            function () use ($user): array {
                $row = $user->pageAccess()->first();
                $access = array_fill_keys(self::MODULES, false);

                if ($row !== null) {
                    foreach (self::MODULES as $module) {
                        $access[$module] = $row->{$module} ?? false;
                    }
                }

                return $access;
            },
        );
    }

    public function forget(int $userId): void
    {
        Cache::forget("pgs_access_{$userId}");
    }
}
