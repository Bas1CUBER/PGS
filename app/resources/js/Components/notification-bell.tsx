import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCircle2, FileEdit, LoaderCircle, Users } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { urls } from '@/lib/urls';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';

interface NotificationItem {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
}

function NotificationIcon({ type }: { type: string }) {
    if (type === 'approved') return <CheckCircle2 size={16} />;
    if (type === 'edit') return <FileEdit size={16} />;
    return <Users size={16} />;
}

export default function NotificationBell() {
    const { unreadCount } = usePage<PageProps>().props;
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [open, setOpen] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const { isPending, start, finish } = usePendingAction();

    async function fetchNotifications(): Promise<void> {
        try {
            const response = await fetch(urls.notifications.feed, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;
            const payload = (await response.json()) as { data: NotificationItem[] };
            setNotifications(payload.data);
            setLoaded(true);
        } catch {
            // Non-critical: the indicator still reflects the shared unread count.
        }
    }

    function toggleOpen(): void {
        const next = !open;
        setOpen(next);
        if (next && !loaded) void fetchNotifications();
    }

    function markAllRead(): void {
        start('all');
        router.post(
            urls.notifications.readAll,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    finish('all');
                },
            },
        );
        setNotifications((items) => items.map((item) => ({ ...item, is_read: true })));
    }

    function openNotification(notification: NotificationItem): void {
        if (!notification.is_read) {
            const action = `read:${String(notification.id)}`;
            start(action);
            router.post(
                urls.notifications.read(String(notification.id)),
                {},
                {
                    preserveScroll: true,
                    onFinish: () => {
                        finish(action);
                    },
                },
            );
            setNotifications((items) =>
                items.map((item) =>
                    item.id === notification.id ? { ...item, is_read: true } : item,
                ),
            );
        }
        setOpen(false);
    }

    return (
        <div
            className="navbar-notification-wrap"
            onClick={(event) => {
                event.stopPropagation();
            }}
        >
            <button
                className={cn('icon-button notification-button', open && 'active')}
                type="button"
                aria-label={`${String(unreadCount)} notifications`}
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-controls="navbar-notifications"
                onClick={toggleOpen}
            >
                <Bell size={18} />
                {unreadCount > 0 && <i aria-hidden="true" />}
            </button>

            {open && (
                <section
                    className="navbar-notification-menu"
                    id="navbar-notifications"
                    role="dialog"
                    aria-label="Notifications"
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') setOpen(false);
                    }}
                >
                    <header>
                        <div>
                            <strong>Notifications</strong>
                            <small>
                                {unreadCount > 0
                                    ? `${String(unreadCount)} unread updates`
                                    : "You're all caught up"}
                            </small>
                        </div>
                        {unreadCount > 0 && (
                            <button
                                className="loading-button"
                                type="button"
                                disabled={isPending('all')}
                                aria-busy={isPending('all') || undefined}
                                data-loading={isPending('all') || undefined}
                                aria-label={
                                    isPending('all') ? 'Marking notifications as read' : undefined
                                }
                                onClick={markAllRead}
                            >
                                <span
                                    className="loading-button-content"
                                    aria-hidden={isPending('all') || undefined}
                                >
                                    Mark all read
                                </span>
                                {isPending('all') ? (
                                    <span className="loading-button-status" aria-hidden="true">
                                        <LoaderCircle className="loading-button-spinner" />
                                        Marking
                                    </span>
                                ) : null}
                            </button>
                        )}
                    </header>
                    <div className="navbar-notification-list">
                        {notifications.length === 0 ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setOpen(false);
                                }}
                            >
                                <span className="navbar-notification-icon">
                                    <Bell size={16} />
                                </span>
                                <span>
                                    <strong>
                                        {loaded ? 'No new notifications' : 'Loading notifications'}
                                    </strong>
                                    <small>
                                        {loaded
                                            ? 'You are all caught up.'
                                            : 'Checking your latest updates.'}
                                    </small>
                                </span>
                            </button>
                        ) : (
                            notifications.map((notification) => (
                                <button
                                    className={notification.is_read ? '' : 'is-unread'}
                                    type="button"
                                    disabled={isPending(`read:${String(notification.id)}`)}
                                    aria-busy={
                                        isPending(`read:${String(notification.id)}`) || undefined
                                    }
                                    key={notification.id}
                                    onClick={() => {
                                        openNotification(notification);
                                    }}
                                >
                                    <span className="navbar-notification-icon">
                                        {isPending(`read:${String(notification.id)}`) ? (
                                            <LoaderCircle className="loading-button-spinner" />
                                        ) : (
                                            <NotificationIcon type={notification.type} />
                                        )}
                                    </span>
                                    <span>
                                        <strong>{notification.title}</strong>
                                        <small>{notification.message}</small>
                                    </span>
                                </button>
                            ))
                        )}
                    </div>
                    <footer>
                        <Link
                            href={urls.notifications.index}
                            onClick={() => {
                                setOpen(false);
                            }}
                        >
                            View all notifications
                        </Link>
                    </footer>
                </section>
            )}
        </div>
    );
}
