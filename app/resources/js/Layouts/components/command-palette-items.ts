import type { LucideIcon } from 'lucide-react';
import {
    Bell,
    ClipboardList,
    FileText,
    FolderTree,
    History,
    LayoutDashboard,
    Mail,
    UserCircle,
} from 'lucide-react';
import { NAV_ICONS, type NavGroup, type NavItem } from '@/components/nav-config';

export interface SearchPaletteItem extends NavItem {
    group: string;
    description: string;
    section: 'quick' | 'navigate';
    icon: LucideIcon;
}

const paletteUtilityIcons: Record<string, LucideIcon> = {
    Profile: UserCircle,
    Notifications: Bell,
    'Audit Log': History,
    Mailbox: Mail,
};

const paletteUtilityDescriptions: Record<string, string> = {
    Profile: 'Manage your account',
    Notifications: 'Review system notifications',
    'Audit Log': 'Review user activity',
    Mailbox: 'Open your messages',
};

export function buildSearchItems(
    links: NavItem[],
    utilityLinks: NavItem[],
    groups: NavGroup[],
): SearchPaletteItem[] {
    const navItems = groups.flatMap((group) =>
        group.items.map((item) => ({ ...item, group: group.title })),
    );
    const quickSearchItems: SearchPaletteItem[] = [
        {
            title: 'Dashboard',
            group: 'Workspace',
            href: '/dashboard',
            description: 'Open workspace dashboard',
            section: 'quick',
            icon: LayoutDashboard,
        },
        ...links.map((link) => ({
            ...link,
            group: 'Workspace',
            description: 'Open survey workspace',
            section: 'quick' as const,
            icon: ClipboardList,
        })),
        ...utilityLinks.map((item) => ({
            ...item,
            group: 'Workspace',
            description:
                paletteUtilityDescriptions[item.title] ?? `Open ${item.title.toLowerCase()}`,
            section: 'quick' as const,
            icon: paletteUtilityIcons[item.title] ?? FileText,
        })),
    ];
    const quickSearchHrefs = new Set(quickSearchItems.map((item) => item.href));

    return [
        ...quickSearchItems,
        ...navItems
            .filter((item) => !quickSearchHrefs.has(item.href))
            .map((item) => ({
                ...item,
                description: `Go to ${item.group.toLowerCase()}`,
                section: 'navigate' as const,
                icon: NAV_ICONS[item.group] ?? FolderTree,
            })),
    ];
}
