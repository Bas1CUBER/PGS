import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Bell, CheckCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { relativeInternalUrl } from '@/lib/relative-url';
import { usePendingAction } from '@/hooks/use-pending-action';

interface NotificationItem {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
}

interface NotificationsPageProps extends PageProps {
    notifications: {
        data: NotificationItem[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const typeColors: Record<string, string> = {
    upload: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    returned: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    edit: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    default: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

export default function NotificationsIndex({ notifications, unreadCount }: NotificationsPageProps) {
    const { isPending, start, finish } = usePendingAction();

    function markAllRead(): void {
        start('all');
        router.post(
            '/notifications/read-all',
            {},
            {
                onFinish: () => {
                    finish('all');
                },
            },
        );
    }

    function markRead(id: number): void {
        const action = `read:${String(id)}`;
        start(action);
        router.post(
            `/notifications/${String(id)}/read`,
            {},
            {
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Notifications</h2>}
        >
            <Head title="Notifications" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Bell className="text-muted-foreground size-5" />
                        <p className="text-muted-foreground text-sm">
                            {unreadCount > 0
                                ? `${String(unreadCount)} unread`
                                : 'You are all caught up'}
                        </p>
                    </div>

                    {unreadCount > 0 && (
                        <Button
                            size="sm"
                            variant="outline"
                            loading={isPending('all')}
                            loadingText="Marking"
                            onClick={markAllRead}
                        >
                            <CheckCheck className="size-4" />
                            Mark all as read
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Notifications</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {notifications.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
                                <Bell className="text-muted-foreground size-8" />
                                <p className="text-muted-foreground text-sm">
                                    No notifications yet.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {notifications.data.map((notification) => (
                                    <li
                                        key={notification.id}
                                        className={cn(
                                            'flex items-start gap-3 px-6 py-4',
                                            !notification.is_read && 'bg-accent/50',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                notification.is_read
                                                    ? 'bg-transparent'
                                                    : 'bg-primary',
                                            )}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        'px-2 py-0.5 text-xs',
                                                        typeColors[notification.type] ??
                                                            typeColors.default,
                                                    )}
                                                >
                                                    {notification.type}
                                                </Badge>
                                                <span className="text-muted-foreground text-xs">
                                                    {new Date(
                                                        notification.created_at,
                                                    ).toLocaleString()}
                                                </span>
                                            </div>
                                            <p className="mt-1 font-medium">{notification.title}</p>
                                            <p className="text-muted-foreground mt-0.5 text-sm">
                                                {notification.message}
                                            </p>
                                        </div>

                                        {!notification.is_read && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="shrink-0"
                                                loading={isPending(
                                                    `read:${String(notification.id)}`,
                                                )}
                                                loadingText="Marking"
                                                onClick={() => {
                                                    markRead(notification.id);
                                                }}
                                            >
                                                Mark read
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {notifications.links.length > 3 && (
                    <div className="flex justify-center gap-2">
                        {notifications.links.map((link, index) => (
                            <span key={index}>
                                {link.url ? (
                                    <Button
                                        asChild
                                        variant={link.active ? 'default' : 'ghost'}
                                        size="sm"
                                    >
                                        <Link href={relativeInternalUrl(link.url) ?? '#'}>
                                            {link.label.replace(/&laquo;|&raquo;/g, '')}
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button variant="ghost" size="sm" disabled>
                                        {link.label.replace(/&laquo;|&raquo;/g, '')}
                                    </Button>
                                )}
                            </span>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
