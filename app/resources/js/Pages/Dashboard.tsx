import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Bell, CheckCircle2, Clock, FileText, Timer, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface DashboardData {
    stats: Record<string, number>;
    recent_uploads?: { page: string; file: string; time: string | null; user: string }[];
    pending_approvals_list?: { page: string; time: string | null; user: string }[];
    notices?: {
        notice_id: number;
        title: string | null;
        description: string | null;
        created_at: string;
        image_url: string | null;
        video_url: string | null;
    }[];
    deliverables?: {
        id: number;
        title: string | null;
        division: string | null;
        target_date: string | null;
        status: string | null;
    }[];
    recent_notifications?: {
        id: number;
        type: string;
        title: string;
        message: string;
        is_read: boolean;
        created_at: string;
    }[];
}

interface DashboardPageProps extends PageProps {
    dashboard: DashboardData;
}

const statIcons: Record<string, typeof Users> = {
    users_total: Users,
    users_active: Users,
    deliverables_total: FileText,
    notices_total: Bell,
    pending_approvals: Clock,
    unread_notifications: Bell,
    deliverables_accomplished: CheckCircle2,
    deliverables_ongoing: Timer,
};

const statDetails: Record<string, { trend: string; detail: string }> = {
    users_total: { trend: 'Total', detail: 'Registered accounts' },
    users_active: { trend: 'Active', detail: 'Currently enabled' },
    deliverables_total: { trend: 'Total', detail: 'Across all modules' },
    notices_total: { trend: 'Published', detail: 'Published notices' },
    pending_approvals: { trend: 'Pending', detail: 'Awaiting review' },
    unread_notifications: { trend: 'Unread', detail: 'Needs attention' },
    deliverables_accomplished: { trend: 'Complete', detail: 'Successfully delivered' },
    deliverables_ongoing: { trend: 'Ongoing', detail: 'Currently in progress' },
};

const statTones: Record<string, 'blue' | 'green' | 'violet' | 'amber' | 'red'> = {
    users_total: 'blue',
    users_active: 'green',
    deliverables_total: 'violet',
    notices_total: 'amber',
    pending_approvals: 'red',
    unread_notifications: 'blue',
    deliverables_accomplished: 'green',
    deliverables_ongoing: 'amber',
};

const statSparkHeights = [35, 52, 42, 68, 57, 78];

function formatStatLabel(key: string): string {
    const label = key.replace(/_/g, ' ');

    return label.charAt(0).toUpperCase() + label.slice(1);
}

export default function Dashboard({ dashboard }: DashboardPageProps) {
    const { auth } = usePage().props;
    const user = auth.user;

    if (user === null) {
        return null;
    }

    const isAdmin = user.role === 'admin';
    const isFocal = user.role === 'focal';

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Dashboard</h2>}
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                <div className="pgs-dashboard-hero relative overflow-hidden rounded-xl p-6 text-white sm:p-8">
                    <div className="pointer-events-none absolute -top-20 -right-16 size-64 rounded-full bg-white/10 blur-2xl" />
                    <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-medium text-blue-100">
                                {new Date().toLocaleDateString(undefined, {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                })}
                            </p>
                            <h1 className="mt-1 text-2xl font-bold">
                                Welcome back, {user.name ?? user.email}
                            </h1>
                            <p className="mt-1 text-sm text-blue-100">
                                Performance Governance System — TRC DOH
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Badge className="bg-white/15 text-white capitalize ring-1 ring-white/30 backdrop-blur">
                                {user.role}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(dashboard.stats).map(([key, value]) => {
                        const Icon = statIcons[key] ?? FileText;
                        const details = statDetails[key] ?? {
                            trend: 'Current',
                            detail: 'Current total',
                        };

                        return (
                            <Card
                                key={key}
                                className="pgs-stat-card"
                                data-stat-tone={statTones[key] ?? 'blue'}
                            >
                                <div className="pgs-stat-header">
                                    <span className="pgs-stat-icon" aria-hidden="true">
                                        <Icon size={18} />
                                    </span>
                                    <span className="pgs-stat-trend">{details.trend}</span>
                                </div>
                                <div className="pgs-stat-value">
                                    <CardTitle>{formatStatLabel(key)}</CardTitle>
                                    <strong>{String(value)}</strong>
                                </div>
                                <div className="pgs-stat-footer">
                                    <span>{details.detail}</span>
                                    <span className="pgs-stat-spark" aria-hidden="true">
                                        {statSparkHeights.map((_, index) => (
                                            <i key={`${key}-spark-${String(index)}`} />
                                        ))}
                                    </span>
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {isAdmin || isFocal ? (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Pending approvals</CardTitle>
                                    <CardDescription>
                                        Awaiting review across modules
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    {(dashboard.pending_approvals_list ?? []).length === 0 ? (
                                        <p className="pgs-empty-state text-muted-foreground px-0 pb-6 text-sm">
                                            Nothing pending. All caught up.
                                        </p>
                                    ) : (
                                        <ul className="pgs-dashboard-list">
                                            {(dashboard.pending_approvals_list ?? []).map(
                                                (item, i) => (
                                                    <li
                                                        key={i}
                                                        className="flex items-center justify-between gap-3 py-3"
                                                    >
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-medium">
                                                                {item.page}
                                                            </p>
                                                            <p className="text-muted-foreground text-xs">
                                                                {item.user}
                                                            </p>
                                                        </div>
                                                        <span className="text-muted-foreground shrink-0 text-xs">
                                                            {item.time !== null
                                                                ? new Date(
                                                                      item.time,
                                                                  ).toLocaleString()
                                                                : '-'}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Recent uploads</CardTitle>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    {(dashboard.recent_uploads ?? []).length === 0 ? (
                                        <p className="pgs-empty-state text-muted-foreground px-0 pb-6 text-sm">
                                            No uploads yet.
                                        </p>
                                    ) : (
                                        <ul className="pgs-dashboard-list">
                                            {(dashboard.recent_uploads ?? []).map((item, i) => (
                                                <li
                                                    key={i}
                                                    className="flex items-center justify-between gap-3 py-3"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {item.file}
                                                        </p>
                                                        <p className="text-muted-foreground text-xs">
                                                            {item.page} - {item.user}
                                                        </p>
                                                    </div>
                                                    <span className="text-muted-foreground shrink-0 text-xs">
                                                        {item.time !== null
                                                            ? new Date(item.time).toLocaleString()
                                                            : '-'}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    ) : (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>My deliverables</CardTitle>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    {(dashboard.deliverables ?? []).length === 0 ? (
                                        <p className="pgs-empty-state text-muted-foreground px-0 pb-6 text-sm">
                                            No deliverables yet.
                                        </p>
                                    ) : (
                                        <ul className="pgs-dashboard-list">
                                            {(dashboard.deliverables ?? []).map((item) => (
                                                <li
                                                    key={item.id}
                                                    className="flex items-center justify-between gap-3 py-3"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {item.title ??
                                                                `Deliverable #${String(item.id)}`}
                                                        </p>
                                                        <p className="text-muted-foreground text-xs">
                                                            {item.division ?? '-'} - due{' '}
                                                            {item.target_date ?? '-'}
                                                        </p>
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            item.status === 'Accomplished' &&
                                                                'text-green-700 dark:text-green-400',
                                                            item.status === 'Ongoing' &&
                                                                'text-blue-700 dark:text-blue-400',
                                                        )}
                                                    >
                                                        {item.status ?? '-'}
                                                    </Badge>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Recent notifications</CardTitle>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    {(dashboard.recent_notifications ?? []).length === 0 ? (
                                        <p className="pgs-empty-state text-muted-foreground px-0 pb-6 text-sm">
                                            No notifications yet.
                                        </p>
                                    ) : (
                                        <ul className="pgs-dashboard-list">
                                            {(dashboard.recent_notifications ?? []).map((item) => (
                                                <li key={item.id} className="py-3">
                                                    <p className="text-sm font-medium">
                                                        <span className="text-muted-foreground capitalize">
                                                            [{item.type}]{' '}
                                                        </span>
                                                        {item.title}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {item.message}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    )}
                </div>

                {(dashboard.notices ?? []).length > 0 && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Latest notices</CardTitle>
                            <Button asChild variant="ghost" size="sm">
                                <Link href="/notices">View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <ul className="pgs-dashboard-list">
                                {(dashboard.notices ?? []).map((notice) => (
                                    <li key={notice.notice_id} className="py-3">
                                        <p className="text-sm font-medium">
                                            {notice.title ?? 'Untitled'}
                                        </p>
                                        {notice.description !== null && (
                                            <p className="text-muted-foreground line-clamp-1 text-xs">
                                                {notice.description}
                                            </p>
                                        )}
                                        {(notice.image_url !== null ||
                                            notice.video_url !== null) && (
                                            <div className="mt-2 flex gap-2">
                                                {notice.image_url !== null && (
                                                    <img
                                                        src={notice.image_url}
                                                        alt={notice.title ?? 'Notice image'}
                                                        className="size-12 rounded object-cover"
                                                    />
                                                )}
                                                {notice.video_url !== null && (
                                                    <video
                                                        src={notice.video_url}
                                                        className="size-12 rounded object-cover"
                                                    />
                                                )}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
