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
                ->get(['notice_id', 'title', 'description', 'created_at']),
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
        ];
    }

    private function pendingApprovalsCount(): int
    {
        return array_reduce(
            $this->pendingApprovalQueries(),
            static fn (int $carry, mixed $query): int => $carry + $query->count(),
            0,
        );
    }

    /**
     * @return list<array{page: string, time: string|null, user: string, module: string}>
     */
    private function pendingApprovals(int $limit): array
    {
        $items = [];

        foreach ($this->pendingApprovalQueries() as $label => $query) {
            foreach ($query->limit($limit)->get() as $row) {
                $items[] = [
                    'page' => $label,
                    'time' => $this->stringify($row->submitted_at ?? $row->uploaded_at ?? null),
                    'user' => $this->uploaderName($row->uploader_email ?? null),
                    'module' => $this->stringify($row->module ?? null) ?? '',
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['time'], (string) $a['time']));

        return array_slice($items, 0, $limit);
    }

    /**
     * Pending-approval sources mirroring the legacy admin dashboard.
     *
     * @return array<string, Builder>
     */
    private function pendingApprovalQueries(): array
    {
        $queries = [];

        $queries['Operations Review'] = DB::table('operations_review_uploads as o')
            ->join('users as u', 'o.employee_id', '=', 'u.id')
            ->where('o.status', 'Pending')
            ->select('o.uploaded_at', 'u.email as uploader_email');

        $queries['Strategy Review'] = DB::table('strategy_review_uploads as o')
            ->join('users as u', 'o.employee_id', '=', 'u.id')
            ->where('o.status', 'Pending')
            ->select('o.uploaded_at', 'u.email as uploader_email');

        $queries['Communication Plan'] = DB::table('communication_plan_uploads as o')
            ->join('users as u', 'o.employee_id', '=', 'u.id')
            ->where('o.status', 'Pending')
            ->select('o.uploaded_at', 'u.email as uploader_email');

        $queries['Cascading Activities'] = DB::table('cascading_activities as c')
            ->join('users as u', 'c.uploaded_by', '=', 'u.id')
            ->select('c.uploaded_at', 'u.email as uploader_email');

        $queries['Roadmap Changes'] = DB::table('progress_pending_changes as p')
            ->join('users as u', 'p.submitted_by', '=', 'u.id')
            ->where('p.decision', 'Pending')
            ->select('p.submitted_at', 'p.module', 'u.email as uploader_email');

        $queries['Governance: Culture'] = DB::table('governance_culture_uploads as g')
            ->join('users as u', 'g.employee_id', '=', 'u.id')
            ->where('g.status', 'In Progress')
            ->select('g.uploaded_at', 'u.email as uploader_email');

        $queries['Governance: Sharing'] = DB::table('governance_sharing_uploads as g')
            ->join('users as u', 'g.employee_id', '=', 'u.id')
            ->where('g.status', 'In Progress')
            ->select('g.uploaded_at', 'u.email as uploader_email');

        return $queries;
    }

    /**
     * Latest uploads across module upload tables.
     *
     * @return list<array{page: string, file: string, time: string|null, user: string}>
     */
    private function recentUploads(int $limit = 8): array
    {
        $items = [];

        $sources = [
            ['table' => 'operations_review_uploads', 'label' => 'Operations Review', 'file' => 'original_name', 'time' => 'uploaded_at', 'fk' => 'employee_id'],
            ['table' => 'strategy_review_uploads', 'label' => 'Strategy Review', 'file' => 'original_name', 'time' => 'uploaded_at', 'fk' => 'employee_id'],
            ['table' => 'communication_plan_uploads', 'label' => 'Communication Plan', 'file' => 'original_name', 'time' => 'uploaded_at', 'fk' => 'employee_id'],
            ['table' => 'resources_uploads', 'label' => 'Resources', 'file' => 'original_name', 'time' => 'uploaded_at', 'fk' => 'uploaded_by'],
        ];

        foreach ($sources as $source) {
            foreach (DB::table("{$source['table']} as t")
                ->join('users as u', "t.{$source['fk']}", '=', 'u.id')
                ->orderByDesc("t.{$source['time']}")
                ->limit($limit)
                ->get(["t.{$source['file']} as file", "t.{$source['time']} as time", 'u.email as uploader_email']) as $row) {
                $items[] = [
                    'page' => $source['label'],
                    'file' => $this->stringify($row->file ?? null) ?? '',
                    'time' => $this->stringify($row->time ?? null),
                    'user' => $this->uploaderName($row->uploader_email ?? null),
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['time'], (string) $a['time']));

        return array_slice($items, 0, $limit);
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
