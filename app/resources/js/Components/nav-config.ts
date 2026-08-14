import type { User } from '@/types';

export interface NavItem {
    title: string;
    href: string;
}

const commonUtilityLinks: NavItem[] = [
    { title: 'Profile', href: '/profile' },
    { title: 'Notifications', href: '/notifications' },
];

const adminUtilityLinks: NavItem[] = [
    { title: 'Audit Log', href: '/audit-logs' },
    { title: 'Mailbox', href: '/mailbox' },
];

export interface NavGroup {
    title: string;
    gate?: keyof PageAccess;
    href?: string;
    items: NavItem[];
}

export interface PageAccess {
    roadmaps: boolean;
    scorecard: boolean;
    performance_assessment: boolean;
    cascading: boolean;
    governance: boolean;
}

/**
 * Top-navbar menu groups — mirrors the legacy navbar.php structure exactly:
 * labels, submenu items, order, and per-role visibility.
 *
 * - Gated groups (Roadmaps, Scorecard, Performance Assessment, Cascading,
 *   Governance) are hidden unless the user's access matrix row enables them
 *   (no row → denied by default; admins must grant access).
 * - Organization and About are public for every logged-in user.
 * - Admins get the extra "Others" group; employees and focals get the
 *   direct Survey link (legacy behavior).
 */
export function navGroupsFor(user: User, pageAccess: PageAccess): NavGroup[] {
    const can = (gate: keyof PageAccess): boolean => user.role === 'admin' || pageAccess[gate];

    const groups: NavGroup[] = [
        {
            title: 'Roadmaps',
            gate: 'roadmaps',
            href: '/sectors',
            items: [
                { title: 'Collaborative Healthcare Management', href: '/sectors/collab' },
                { title: 'Research', href: '/sectors/research' },
                { title: 'Training', href: '/sectors/training' },
                { title: 'Culture of Organization', href: '/sectors/culture' },
                { title: 'Resilience', href: '/sectors/resilience' },
                { title: 'Technology', href: '/sectors/technology' },
                { title: 'Revenue', href: '/sectors/revenue' },
            ],
        },
        {
            title: 'Scorecard',
            gate: 'scorecard',
            items: [
                { title: 'Roadmap', href: '/roadmaps' },
                { title: 'Impact Indicator', href: '/impact-scorecard' },
            ],
        },
        {
            title: 'Performance Assessment',
            gate: 'performance_assessment',
            items: [
                { title: 'Operations Review', href: '/operations-review' },
                { title: 'Strategy Review', href: '/uploads/strategy-review' },
                { title: 'Strategy Review Form', href: '/strategy-review' },
                { title: 'Annex B — Strategy Map', href: '/annex/annex-b' },
                { title: 'Annex D — Performance Targets', href: '/annex/annex-d' },
                { title: 'Annex E — Quarterly Targets', href: '/annex/annex-e' },
                { title: 'Strategy Refresh', href: '/uploads/strategy-refresh' },
            ],
        },
        {
            title: 'Cascading',
            gate: 'cascading',
            items: [
                { title: 'Communication Plan', href: '/communication-plan' },
                { title: 'Cascading Activities', href: '/uploads/cascading-activities' },
                { title: 'Resources', href: '/uploads/resources' },
                { title: 'Gallery', href: '/gallery' },
            ],
        },
        {
            title: 'Governance',
            gate: 'governance',
            items: [
                { title: 'Governance Culture', href: '/uploads/governance-culture' },
                { title: 'Governance Sharing', href: '/uploads/governance-sharing' },
            ],
        },
        {
            title: 'Organization',
            items: [
                {
                    title: 'Office for Strategy Management',
                    href: '/content/office-for-strategy-management',
                },
                { title: 'PGS Core Team', href: '/content/pgs-core-team' },
                {
                    title: 'Multi-Sector Governance System',
                    href: '/content/multi-sector-governance',
                },
            ],
        },
        {
            title: 'About',
            items: [
                { title: 'Charter Statements', href: '/content/about-charter-statements' },
                { title: 'Strategic Position', href: '/content/about-strategic-position' },
                { title: 'Strategy Map', href: '/content/about-strategy-map' },
                { title: 'PGS Pathway', href: '/content/about-pgs-pathway' },
                { title: 'User Access', href: '/content/about-user-access' },
            ],
        },
    ];

    if (user.role === 'admin') {
        groups.push({
            title: 'Others',
            items: [
                { title: 'Deadline Controls', href: '/deadlines' },
                { title: 'Notice', href: '/notices' },
                { title: 'User Management', href: '/users' },
                { title: 'Backup and Restore', href: '/backups' },
                { title: 'Survey', href: '/surveys' },
                { title: 'OPCR', href: '/opcr' },
            ],
        });
    }

    return groups.filter((group) => group.gate === undefined || can(group.gate));
}

/**
 * Utility destinations available to the authenticated user.
 * Keep this list role-aware because it feeds both breadcrumbs and the
 * Ctrl+K palette; backend middleware remains the final authorization layer.
 */
export function utilityLinksFor(user: User): NavItem[] {
    return user.role === 'admin'
        ? [...commonUtilityLinks, ...adminUtilityLinks]
        : commonUtilityLinks;
}

export function isRouteActive(href: string, currentUrl?: string): boolean {
    if (currentUrl !== undefined) {
        const currentPath = currentUrl.split('?')[0].replace(/\/+$/, '') || '/';
        const targetPath = href.split('?')[0].replace(/\/+$/, '') || '/';

        return (
            currentPath === targetPath ||
            (targetPath !== '/' && currentPath.startsWith(`${targetPath}/`))
        );
    }

    return route().current() === href.replace(/^\//, '');
}
