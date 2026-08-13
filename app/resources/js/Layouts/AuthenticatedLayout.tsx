import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode, useEffect, useMemo } from 'react';
import { toast } from 'sonner';
import { AlertTriangle, Clock, LayoutDashboard, LogOut, User as UserIcon } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
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
import { navigationFor, isRouteActive } from '@/components/nav-config';
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

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, deadline, flash } = usePage().props;
    const user = auth.user;

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

    const sections = navigationFor(user);
    const initials = (user.name ?? user.email).slice(0, 2).toUpperCase();

    return (
        <SidebarProvider>
            <Sidebar collapsible="icon">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href="/dashboard">
                                    <div className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-lg">
                                        <LayoutDashboard className="size-4" />
                                    </div>
                                    <div className="grid flex-1 text-left text-sm leading-tight">
                                        <span className="truncate font-semibold">PGS</span>
                                        <span className="text-muted-foreground truncate text-xs">
                                            TRC DOH
                                        </span>
                                    </div>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    {sections.map((section) => (
                        <div key={section.title} className="px-2 py-1">
                            <p className="text-muted-foreground px-2 pb-1 text-xs font-medium">
                                {section.title}
                            </p>
                            <SidebarMenu>
                                {section.items.map((item) => (
                                    <SidebarMenuItem key={item.href}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isRouteActive(item.href)}
                                        >
                                            <Link href={item.href}>
                                                <item.icon />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </div>
                    ))}
                </SidebarContent>

                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/profile">
                                    <UserIcon />
                                    <span>Profile</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href="/logout" method="post" as="button" className="w-full">
                                    <LogOut />
                                    <span>Log out</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            </Sidebar>

            <div className="flex min-h-screen flex-1 flex-col">
                <header className="bg-background sticky top-0 z-40 flex h-16 items-center gap-3 border-b px-4 lg:px-6">
                    <SidebarTrigger />
                    <div className="flex flex-1 items-center gap-3">
                        {header && <div className="hidden md:block">{header}</div>}
                    </div>

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

                    <ModeToggle />
                    <NotificationBell />

                    <Separator orientation="vertical" className="h-8" />

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="gap-2 px-2">
                                <Avatar className="size-8">
                                    <AvatarFallback className="bg-primary text-primary-foreground text-xs">
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
                                <Link href="/profile">Profile</Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/notifications">Notifications</Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href="/logout" method="post" as="button" className="w-full">
                                    Log out
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
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
        </SidebarProvider>
    );
}
