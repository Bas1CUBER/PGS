import { Head, Link, router } from '@inertiajs/react';
import type { PageProps } from '@/types';

interface NotificationItem {
    id: number;
    type: string;
    title: string;
    message: string;
    related_id: number | null;
    related_type: string | null;
    is_read: boolean;
    created_at: string;
}

interface NotificationPageProps extends PageProps {
    notifications: {
        data: NotificationItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    unreadCount: number;
}

const typeColors: Record<string, string> = {
    upload: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    returned: 'bg-amber-100 text-amber-800',
    edit: 'bg-gray-100 text-gray-800',
    default: 'bg-gray-100 text-gray-800',
};

export default function NotificationsIndex({ notifications, unreadCount }: NotificationPageProps) {
    return (
        <>
            <Head title="Notifications" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h2 className="text-xl font-semibold leading-tight text-gray-800">
                                Notifications
                            </h2>
                            <p className="mt-1 text-sm text-gray-600">
                                {unreadCount > 0
                                    ? `${unreadCount} unread`
                                    : 'You are all caught up'}
                            </p>
                        </div>

                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={() => router.post('/notifications/read-all')}
                                className="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700"
                            >
                                Mark all as read
                            </button>
                        )}
                    </div>

                    {notifications.data.length === 0 ? (
                        <div className="rounded-lg bg-white p-8 text-center shadow-sm">
                            <p className="text-gray-500">No notifications yet.</p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                            <ul className="divide-y divide-gray-200">
                                {notifications.data.map((notification) => (
                                    <li
                                        key={notification.id}
                                        className={`p-4 ${notification.is_read ? 'bg-white' : 'bg-blue-50/50'}`}
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    {!notification.is_read && (
                                                        <span className="h-2 w-2 shrink-0 rounded-full bg-blue-600" />
                                                    )}
                                                    <span
                                                        className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${typeColors[notification.type] ?? typeColors.default}`}
                                                    >
                                                        {notification.type}
                                                    </span>
                                                    <span className="text-sm text-gray-500">
                                                        {new Date(notification.created_at).toLocaleString()}
                                                    </span>
                                                </div>
                                                <p className="mt-1 font-medium text-gray-900">
                                                    {notification.title}
                                                </p>
                                                <p className="mt-0.5 text-sm text-gray-600">
                                                    {notification.message}
                                                </p>
                                            </div>

                                            {!notification.is_read && (
                                                <Link
                                                    href={`/notifications/${notification.id}/read`}
                                                    method="post"
                                                    as="button"
                                                    className="shrink-0 text-sm text-blue-600 hover:underline"
                                                >
                                                    Mark read
                                                </Link>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            {notifications.links.length > 3 && (
                                <div className="flex justify-center gap-2 border-t border-gray-200 p-4">
                                    {notifications.links.map((link, index) => (
                                        <span key={index}>
                                            {link.url ? (
                                                <Link
                                                    href={link.url}
                                                    className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-100'}`}
                                                >
                                                    {link.label.replace(/&laquo;|&raquo;/g, '')}
                                                </Link>
                                            ) : (
                                                <span className="rounded px-3 py-1 text-sm text-gray-400">
                                                    {link.label.replace(/&laquo;|&raquo;/g, '')}
                                                </span>
                                            )}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
