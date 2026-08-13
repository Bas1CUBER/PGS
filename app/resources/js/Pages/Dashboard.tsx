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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Welcome back, {user.name ?? user.email}
                        </h1>
                        <p className="text-muted-foreground">
                            Performance Governance System - TRC DOH
                        </p>
                    </div>
                    <Badge variant="outline" className="capitalize">
                        {user.role}
                    </Badge>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(dashboard.stats).map(([key, value]) => {
                        const Icon = statIcons[key] ?? FileText;

                        return (
                            <Card key={key}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        {key.replace(/_/g, ' ')}
                                    </CardTitle>
                                    <Icon className="text-muted-foreground size-4" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{String(value)}</div>
                                </CardContent>
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
                                <CardContent className="p-0">
                                    {(dashboard.pending_approvals_list ?? []).length === 0 ? (
                                        <p className="text-muted-foreground px-6 pb-6 text-sm">
                                            Nothing pending. All caught up.
                                        </p>
                                    ) : (
                                        <ul className="divide-y">
                                            {(dashboard.pending_approvals_list ?? []).map(
                                                (item, i) => (
                                                    <li
                                                        key={i}
                                                        className="flex items-center justify-between gap-3 px-6 py-3"
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
                                <CardContent className="p-0">
                                    {(dashboard.recent_uploads ?? []).length === 0 ? (
                                        <p className="text-muted-foreground px-6 pb-6 text-sm">
                                            No uploads yet.
                                        </p>
                                    ) : (
                                        <ul className="divide-y">
                                            {(dashboard.recent_uploads ?? []).map((item, i) => (
                                                <li
                                                    key={i}
                                                    className="flex items-center justify-between gap-3 px-6 py-3"
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
                                <CardContent className="p-0">
                                    {(dashboard.deliverables ?? []).length === 0 ? (
                                        <p className="text-muted-foreground px-6 pb-6 text-sm">
                                            No deliverables yet.
                                        </p>
                                    ) : (
                                        <ul className="divide-y">
                                            {(dashboard.deliverables ?? []).map((item) => (
                                                <li
                                                    key={item.id}
                                                    className="flex items-center justify-between gap-3 px-6 py-3"
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
                                <CardContent className="p-0">
                                    {(dashboard.recent_notifications ?? []).length === 0 ? (
                                        <p className="text-muted-foreground px-6 pb-6 text-sm">
                                            No notifications yet.
                                        </p>
                                    ) : (
                                        <ul className="divide-y">
                                            {(dashboard.recent_notifications ?? []).map((item) => (
                                                <li key={item.id} className="px-6 py-3">
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

                {isAdmin && (dashboard.notices ?? []).length > 0 && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Latest notices</CardTitle>
                            <Button asChild variant="ghost" size="sm">
                                <Link href="/notices">View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            <ul className="divide-y">
                                {(dashboard.notices ?? []).map((notice) => (
                                    <li key={notice.notice_id} className="px-6 py-3">
                                        <p className="text-sm font-medium">
                                            {notice.title ?? 'Untitled'}
                                        </p>
                                        {notice.description !== null && (
                                            <p className="text-muted-foreground line-clamp-1 text-xs">
                                                {notice.description}
                                            </p>
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
