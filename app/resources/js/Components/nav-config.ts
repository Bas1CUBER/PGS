import type { LucideIcon } from 'lucide-react';
import {
    Bell,
    Database,
    FileText,
    Home,
    LayoutDashboard,
    LayoutList,
    LogIn,
    Megaphone,
    ScrollText,
    Timer,
    Users,
} from 'lucide-react';
import type { User } from '@/types';

export interface NavItem {
    title: string;
    href: string;
    icon: LucideIcon;
}

export interface NavSection {
    title: string;
    items: NavItem[];
}

/**
 * Role-aware navigation — single source of truth for the sidebar.
 * Module entries for roadmaps/deliverables/scorecard land here in Phase 5-6
 * as their pages ship.
 */
export function navigationFor(user: User): NavSection[] {
    const sections: NavSection[] = [
        {
            title: 'Overview',
            items: [
                { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
                { title: 'Notifications', href: '/notifications', icon: Bell },
            ],
        },
        {
            title: 'Modules',
            items: [
                { title: 'Deliverables', href: '/deliverables', icon: FileText },
                { title: 'Roadmaps', href: '/roadmaps', icon: LayoutList },
                { title: 'Notices', href: '/notices', icon: Megaphone },
            ],
        },
        {
            title: 'Account',
            items: [{ title: 'Profile', href: '/profile', icon: Home }],
        },
    ];

    if (user.role === 'admin') {
        sections.push({
            title: 'Administration',
            items: [
                { title: 'User Management', href: '/users', icon: Users },
                { title: 'Deadlines', href: '/deadlines', icon: Timer },
                { title: 'Backups', href: '/backups', icon: Database },
                { title: 'Audit Log', href: '/audit-logs', icon: ScrollText },
            ],
        });
    }

    return sections;
}

export function guestNavItems(): NavItem[] {
    return [{ title: 'Sign in', href: '/login', icon: LogIn }];
}

export function isRouteActive(href: string): boolean {
    return route().current() === href.replace(/^\//, '');
}

export { FileText };
