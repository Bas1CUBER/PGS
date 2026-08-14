import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { AlertTriangle, ChevronDown, Clock, Menu, X } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ModeToggle } from '@/components/mode-toggle';
import NotificationBell from '@/components/notification-bell';
import { navGroupsFor, isRouteActive } from '@/components/nav-config';
import type { PageProps } from '@/types';
import { cn } from '@/lib/utils';

function deadlineState(
    deadline: { enabled: boolean; end_time: string | null; message: string | null } | null,
) {
    if (deadline === null || !deadline.enabled || deadline.end_time === null) {
        return { active: false, warning: false } as const;
    }

    const hoursLeft = (new Date(deadline.end_time).getTime() - Date.now()) / 36e5;

    return {
        active: hoursLeft > 0,
        warning: hoursLeft > 0 && hoursLeft <= 72,
    } as const;
}

function quickLinks(user: { role: string }): { title: string; href: string }[] {
    // Legacy: employees and focals get a direct Survey link after the menus.
    return user.role === 'admin' ? [] : [{ title: 'Survey', href: '/surveys' }];
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, deadline, flash, pageAccess } = usePage<PageProps>().props;
    const user = auth.user;
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
        if (flash.warning) toast.warning(flash.warning);
        if (flash.info) toast.info(flash.info);
    }, [flash]);

    const deadlineInfo = useMemo(() => deadlineState(deadline), [deadline]);

    if (user === null) {
        // Authenticated layout is only rendered behind the auth middleware.
        return null;
    }

    const groups = navGroupsFor(user, pageAccess);
    const links = quickLinks(user);
    const initials = (user.name ?? user.email).slice(0, 2).toUpperCase();

    return (
        <div className="flex min-h-screen flex-col">
            {/* Legacy-style top navbar */}
            <header className="bg-primary sticky top-0 z-40 border-b">
                <div className="flex h-16 items-center gap-2 px-4 lg:px-6">
                    <Link href="/dashboard" className="flex shrink-0 items-center">
                        <img
                            src="/legacy-img/final_logo1.png"
                            alt="TRC DOH Logo"
                            className="h-12 w-auto"
                        />
                    </Link>

                    {/* Desktop dropdown menus */}
                    <nav
                        className="ml-6 hidden items-center gap-1 xl:flex"
                        aria-label="Main navigation"
                    >
                        {groups.map((group) => (
                            <DropdownMenu key={group.title}>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        className="text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground"
                                    >
                                        {group.title}
                                        <ChevronDown className="size-3.5 opacity-70" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" className="w-64">
                                    {group.items.map((item) => (
                                        <DropdownMenuItem
                                            key={item.href}
                                            asChild
                                            className={cn(
                                                isRouteActive(item.href) && 'bg-accent font-medium',
                                            )}
                                        >
                                            <Link href={item.href}>{item.title}</Link>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ))}

                        {/* Legacy: direct Survey link for employees and focals */}
                        {links.map((link) => (
                            <Button
                                key={link.href}
                                asChild
                                variant="ghost"
                                className={cn(
                                    'text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground',
                                    isRouteActive(link.href) && 'bg-primary-foreground/15',
                                )}
                            >
                                <Link href={link.href}>{link.title}</Link>
                            </Button>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-3">
                        {deadlineInfo.active && deadline?.end_time != null && (
                            <Badge
                                variant={deadlineInfo.warning ? 'warning' : 'outline'}
                                className={cn(
                                    'hidden items-center gap-1 sm:inline-flex',
                                    deadlineInfo.warning && 'animate-pulse',
                                )}
                            >
                                <Clock className="size-3" />
                                Deadline: {new Date(deadline.end_time).toLocaleString()}
                            </Badge>
                        )}

                        {header && <div className="hidden md:block">{header}</div>}

                        <ModeToggle />
                        <NotificationBell />

                        <Separator
                            orientation="vertical"
                            className="bg-primary-foreground/30 h-8"
                        />

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground gap-2 px-2"
                                >
                                    <Avatar className="size-8">
                                        <AvatarFallback className="bg-primary-foreground text-primary text-xs">
                                            {initials}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="hidden max-w-32 truncate text-sm font-medium md:inline">
                                        {user.name ?? user.email}
                                    </span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel>
                                    <div className="flex flex-col">
                                        <span className="truncate">{user.name ?? 'User'}</span>
                                        <span className="text-muted-foreground text-xs font-normal capitalize">
                                            {user.role}
                                            {user.office ? ` · ${user.office}` : ''}
                                        </span>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href="/profile">Change Password</Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="text-destructive w-full"
                                    >
                                        Logout
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {/* Mobile toggle */}
                        <Button
                            variant="ghost"
                            size="icon"
                            className="text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground xl:hidden"
                            onClick={() => {
                                setMobileOpen((open) => !open);
                            }}
                            aria-label="Toggle navigation menu"
                        >
                            {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </Button>
                    </div>
                </div>

                {/* Mobile collapsible navigation */}
                {mobileOpen && (
                    <div className="bg-primary max-h-[70vh] overflow-y-auto">
                        <nav
                            className="space-y-4 px-4 py-4 xl:hidden"
                            aria-label="Mobile navigation"
                        >
                            {links.map((link) => (
                                <Link
                                    key={link.href}
                                    href={link.href}
                                    onClick={() => {
                                        setMobileOpen(false);
                                    }}
                                    className={cn(
                                        'text-primary-foreground block py-1.5 text-sm font-medium hover:opacity-80',
                                        isRouteActive(link.href) && 'underline underline-offset-4',
                                    )}
                                >
                                    {link.title}
                                </Link>
                            ))}

                            {groups.map((group) => (
                                <div key={group.title}>
                                    <p className="text-primary-foreground/70 py-1 text-xs font-semibold tracking-wide uppercase">
                                        {group.title}
                                    </p>
                                    <div className="border-primary-foreground/20 space-y-0.5 border-l pl-3">
                                        {group.items.map((item) => (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                onClick={() => {
                                                    setMobileOpen(false);
                                                }}
                                                className="text-primary-foreground block py-1 text-sm hover:opacity-80"
                                            >
                                                {item.title}
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </nav>
                    </div>
                )}
            </header>

            {deadlineInfo.active && (
                <div
                    className={cn(
                        'flex items-center gap-2 border-b px-4 py-2 text-sm lg:px-6',
                        deadlineInfo.warning
                            ? 'border-warning/50 bg-warning/10 text-warning-foreground'
                            : 'border-border bg-muted text-muted-foreground',
                    )}
                >
                    <AlertTriangle className="size-4 shrink-0" />
                    <span>{deadline?.message}</span>
                    {deadline?.end_time != null && (
                        <span className="ml-auto shrink-0 font-medium">
                            {new Date(deadline.end_time).toLocaleString()}
                        </span>
                    )}
                </div>
            )}

            <main className="flex-1 p-4 lg:p-6">{children}</main>
        </div>
    );
}
