import { Bell } from 'lucide-react';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';

interface NotificationItem {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
}

const typeColors: Record<string, string> = {
    upload: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    returned: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    edit: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    default: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

export default function NotificationBell() {
    const { unreadCount } = usePage().props;
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [open, setOpen] = useState(false);

    async function fetchNotifications(): Promise<void> {
        try {
            const response = await fetch('/notifications/feed', {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;
            const payload = (await response.json()) as { data: NotificationItem[] };
            setNotifications(payload.data);
        } catch {
            // Non-critical: badge still works via the shared unreadCount prop.
        }
    }

    function handleOpenChange(next: boolean): void {
        setOpen(next);

        if (next && notifications.length === 0) {
            void fetchNotifications();
        }
    }

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative" aria-label="Notifications">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <Badge
                            className={cn(
                                'absolute -top-1 -right-1 h-5 min-w-5 justify-center rounded-full px-1 text-xs',
                            )}
                            variant="destructive"
                        >
                            {unreadCount > 99 ? '99+' : String(unreadCount)}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    Notifications
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            className="text-xs font-normal text-blue-600 hover:underline dark:text-blue-300"
                            onClick={() => {
                                router.post('/notifications/read-all');
                                setOpen(false);
                            }}
                        >
                            Mark all read
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {notifications.length === 0 ? (
                    <div className="text-muted-foreground px-3 py-6 text-center text-sm">
                        <Link
                            href="/notifications"
                            className="text-blue-600 hover:underline dark:text-blue-300"
                        >
                            Open notifications
                        </Link>
                    </div>
                ) : (
                    notifications.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            asChild
                            className={cn(
                                'flex-col items-start gap-1 py-2',
                                !notification.is_read && 'bg-accent',
                            )}
                        >
                            <Link
                                href={`/notifications/${String(notification.id)}/read`}
                                method="post"
                                as="button"
                                className="w-full text-left"
                            >
                                <span className="flex items-center gap-2">
                                    <span
                                        className={cn(
                                            'inline-block rounded-full px-2 py-0.5 text-xs font-medium',
                                            typeColors[notification.type] ?? typeColors.default,
                                        )}
                                    >
                                        {notification.type}
                                    </span>
                                    <span className="text-sm font-medium">
                                        {notification.title}
                                    </span>
                                </span>
                                <span className="text-muted-foreground line-clamp-2 text-xs">
                                    {notification.message}
                                </span>
                            </Link>
                        </DropdownMenuItem>
                    ))
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/notifications" className="justify-center text-sm">
                        View all
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
