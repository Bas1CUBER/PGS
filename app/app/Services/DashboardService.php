<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\Notice;
use App\Models\Notification;
use App\Models\User;
use App\Modules\UploadModuleRegistry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DashboardService
{
    /**
     * Build the dashboard payload for a user, role-aware.
     *
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        return match ($user->role) {
            Role::Admin => $this->admin(),
            Role::Focal => $this->focal(),
            Role::Employee => $this->employee($user),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function admin(): array
    {
        $users = DB::table('users');
        $deliverables = DB::table('p_deliverables');

        return [
            'stats' => [
                'users_total' => (clone $users)->count(),
                'users_active' => (clone $users)->where('is_active', true)->count(),
                'deliverables_total' => (clone $deliverables)->count(),
                'notices_total' => Notice::query()->count(),
                'pending_approvals' => $this->pendingApprovalsCount(),
            ],
            'recent_uploads' => $this->recentUploads(),
            'pending_approvals_list' => $this->pendingApprovals(10),
            'notices' => Notice::query()
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['notice_id', 'title', 'description', 'image', 'video', 'created_at'])
                ->map(fn (Notice $notice): array => $this->presentNotice($notice))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function focal(): array
    {
        return [
            'stats' => [
                'deliverables_total' => DB::table('p_deliverables')->count(),
                'pending_approvals' => $this->pendingApprovalsCount(),
                'users_total' => DB::table('users')->where('is_active', true)->count(),
            ],
            'recent_uploads' => $this->recentUploads(),
            'pending_approvals_list' => $this->pendingApprovals(10),
            'notices' => $this->latestNotices(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employee(User $user): array
    {
        $deliverables = DB::table('p_deliverables')->where('uploaded_by', $user->id);

        return [
            'stats' => [
                'deliverables_total' => (clone $deliverables)->count(),
                'deliverables_accomplished' => (clone $deliverables)->where('status', 'Accomplished')->count(),
                'deliverables_ongoing' => (clone $deliverables)->where('status', 'Ongoing')->count(),
                'unread_notifications' => Notification::query()
                    ->where('user_id', $user->id)
                    ->where('is_read', false)
                    ->count(),
            ],
            'deliverables' => (clone $deliverables)
                ->orderByDesc('target_date')
                ->limit(8)
                ->get(['id', 'title', 'division', 'target_date', 'actual_date', 'status']),
            'recent_notifications' => Notification::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'type', 'title', 'message', 'is_read', 'created_at']),
            'notices' => $this->latestNotices(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function latestNotices(): array
    {
        $notices = Notice::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['notice_id', 'title', 'description', 'image', 'video', 'created_at'])
            ->map(fn (Notice $notice): array => $this->presentNotice($notice))
            ->values()
            ->all();

        return array_values($notices);
    }

    /** @return array<string, mixed> */
    private function presentNotice(Notice $notice): array
    {
        return [
            'notice_id' => $notice->notice_id,
            'title' => $notice->title,
            'description' => $notice->description,
            'created_at' => $notice->created_at,
            'image_url' => $notice->image !== null ? route('notices.media', [$notice, 'image'], absolute: false) : null,
            'video_url' => $notice->video !== null ? route('notices.media', [$notice, 'video'], absolute: false) : null,
        ];
    }

    private function pendingApprovalsCount(): int
    {
        return $this->pendingApprovalUnionQuery()->count();
    }

    /**
     * @return list<array{page: string, time: string|null, user: string, module: string}>
     */
    private function pendingApprovals(int $limit): array
    {
        $rows = $this->pendingApprovalUnionQuery()
            ->orderByDesc('time')
            ->limit($limit)
            ->get();

        /** @var list<array{page: string, time: string|null, user: string, module: string}> */
        return $rows->map(fn (object $row): array => [
            'page' => $this->stringify($row->page) ?? '',
            'time' => $this->stringify($row->time),
            'user' => $this->uploaderName($row->uploader_email),
            'module' => $this->stringify($row->module) ?? '',
        ])->values()->all();
    }

    private function pendingApprovalUnionQuery(): Builder
    {
        // Generated from the registry so new modules cannot be forgotten.
        // Only reviewable (has_status) tables belong in this queue —
        // cascading activities have no status and would inflate the count
        // forever. The "awaiting review" status is Pending, or the first
        // status value for vocabularies that start elsewhere (governance).
        $union = null;

        foreach (UploadModuleRegistry::modules() as $module) {
            if (! $module['has_status']) {
                continue;
            }

            $statusValues = $module['status_values'] ?? [];
            $awaiting = in_array('Pending', $statusValues, true) ? 'Pending' : ($statusValues[0] ?? null);
            if ($awaiting === null) {
                continue;
            }

            $branch = DB::table($module['table'].' as t')
                ->join('users as u', 't.'.$module['uploader_fk'], '=', 'u.id')
                ->where('t.status', $awaiting)
                ->select(
                    DB::raw("'".str_replace("'", "''", $module['label'])."' as page"),
                    DB::raw('t.uploaded_at as time'),
                    DB::raw('u.email as uploader_email'),
                    DB::raw('null as module'),
                );

            $union = $union === null ? $branch : $union->unionAll($branch);
        }

        // Roadmap pending changes remain a hand-written branch: they are not
        // an upload module.
        $pendingChanges = DB::table('progress_pending_changes as p')
            ->join('users as u', 'p.submitted_by', '=', 'u.id')
            ->where('p.decision', 'Pending')
            ->select(DB::raw("'Roadmap Changes' as page"), DB::raw('p.submitted_at as time'), DB::raw('u.email as uploader_email'), 'p.module');

        if ($union === null) {
            return $pendingChanges;
        }

        return $union->unionAll($pendingChanges);
    }

    /**
     * Latest uploads across ALL module upload tables (registry-driven).
     *
     * @return list<array{page: string, file: string, time: string|null, user: string}>
     */
    private function recentUploads(int $limit = 8): array
    {
        /** @var list<array{page: string, file: string, time: string|null, user: string}> */
        return $this->recentUploadsUnion()
            ->orderByDesc('time')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'page' => $this->stringify($row->page) ?? '',
                'file' => $this->stringify($row->file) ?? '',
                'time' => $this->stringify($row->time),
                'user' => $this->uploaderName($row->uploader_email),
            ])->values()->all();
    }

    private function recentUploadsUnion(): Builder
    {
        $union = null;

        foreach (UploadModuleRegistry::modules() as $module) {
            $branch = DB::table($module['table'].' as t')
                ->join('users as u', 't.'.$module['uploader_fk'], '=', 'u.id')
                ->select(
                    DB::raw("'".str_replace("'", "''", $module['label'])."' as page"),
                    DB::raw('t.original_name as file'),
                    DB::raw('t.uploaded_at as time'),
                    DB::raw('u.email as uploader_email'),
                );

            $union = $union === null ? $branch : $union->unionAll($branch);
        }

        if ($union === null) {
            throw new \LogicException('Upload module registry returned no modules.');
        }

        return $union;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function uploaderName(mixed $email): string
    {
        $parts = explode('@', $this->stringify($email) ?? '');

        return strtoupper($parts[0]);
    }
}
