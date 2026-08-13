import type { LucideIcon } from 'lucide-react';
import { Bell, FileText, Home, LayoutDashboard, LogIn, Users } from 'lucide-react';
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
                { title: 'Profile', href: '/profile', icon: Home },
            ],
        },
    ];

    if (user.role === 'admin') {
        sections.push({
            title: 'Administration',
            items: [{ title: 'User Management', href: '/users', icon: Users }],
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
