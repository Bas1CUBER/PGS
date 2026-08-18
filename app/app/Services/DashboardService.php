<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\Notice;
use App\Models\Notification;
use App\Models\User;
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
        $a = DB::table('operations_review_uploads as o')
            ->join('users as u', 'o.employee_id', '=', 'u.id')
            ->where('o.status', 'Pending')
            ->select(DB::raw("'Operations Review' as page"), 'o.uploaded_at as time', 'u.email as uploader_email', DB::raw('null as module'));

        $b = DB::table('strategy_review_uploads as o')
            ->join('users as u', 'o.employee_id', '=', 'u.id')
            ->where('o.status', 'Pending')
            ->select(DB::raw("'Strategy Review' as page"), 'o.uploaded_at as time', 'u.email as uploader_email', DB::raw('null as module'));

        $c = DB::table('communication_plan_uploads as o')
            ->join('users as u', 'o.employee_id', '=', 'u.id')
            ->where('o.status', 'Pending')
            ->select(DB::raw("'Communication Plan' as page"), 'o.uploaded_at as time', 'u.email as uploader_email', DB::raw('null as module'));

        $d = DB::table('cascading_activities as c')
            ->join('users as u', 'c.uploaded_by', '=', 'u.id')
            ->select(DB::raw("'Cascading Activities' as page"), 'c.uploaded_at as time', 'u.email as uploader_email', DB::raw('null as module'));

        $e = DB::table('progress_pending_changes as p')
            ->join('users as u', 'p.submitted_by', '=', 'u.id')
            ->where('p.decision', 'Pending')
            ->select(DB::raw("'Roadmap Changes' as page"), 'p.submitted_at as time', 'u.email as uploader_email', 'p.module');

        $f = DB::table('governance_culture_uploads as g')
            ->join('users as u', 'g.employee_id', '=', 'u.id')
            ->where('g.status', 'In Progress')
            ->select(DB::raw("'Governance: Culture' as page"), 'g.uploaded_at as time', 'u.email as uploader_email', DB::raw('null as module'));

        $g = DB::table('governance_sharing_uploads as g')
            ->join('users as u', 'g.employee_id', '=', 'u.id')
            ->where('g.status', 'In Progress')
            ->select(DB::raw("'Governance: Sharing' as page"), 'g.uploaded_at as time', 'u.email as uploader_email', DB::raw('null as module'));

        return $a->unionAll($b)->unionAll($c)->unionAll($d)->unionAll($e)->unionAll($f)->unionAll($g);
    }

    /**
     * Latest uploads across module upload tables.
     *
     * @return list<array{page: string, file: string, time: string|null, user: string}>
     */
    private function recentUploads(int $limit = 8): array
    {
        $a = DB::table('operations_review_uploads as t')
            ->join('users as u', 't.employee_id', '=', 'u.id')
            ->select(DB::raw("'Operations Review' as page"), 't.original_name as file', 't.uploaded_at as time', 'u.email as uploader_email');

        $b = DB::table('strategy_review_uploads as t')
            ->join('users as u', 't.employee_id', '=', 'u.id')
            ->select(DB::raw("'Strategy Review' as page"), 't.original_name as file', 't.uploaded_at as time', 'u.email as uploader_email');

        $c = DB::table('communication_plan_uploads as t')
            ->join('users as u', 't.employee_id', '=', 'u.id')
            ->select(DB::raw("'Communication Plan' as page"), 't.original_name as file', 't.uploaded_at as time', 'u.email as uploader_email');

        $d = DB::table('resources_uploads as t')
            ->join('users as u', 't.uploaded_by', '=', 'u.id')
            ->select(DB::raw("'Resources' as page"), 't.original_name as file', 't.uploaded_at as time', 'u.email as uploader_email');

        $rows = $a->unionAll($b)->unionAll($c)->unionAll($d)
            ->orderByDesc('time')
            ->limit($limit)
            ->get();

        /** @var list<array{page: string, file: string, time: string|null, user: string}> */
        return $rows->map(fn (object $row): array => [
            'page' => $this->stringify($row->page) ?? '',
            'file' => $this->stringify($row->file) ?? '',
            'time' => $this->stringify($row->time),
            'user' => $this->uploaderName($row->uploader_email),
        ])->values()->all();
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
