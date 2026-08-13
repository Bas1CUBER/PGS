<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

final class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        int $userId,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null
                ? substr((string) $request->userAgent(), 0, 255)
                : null,
        ]);
    }
}
