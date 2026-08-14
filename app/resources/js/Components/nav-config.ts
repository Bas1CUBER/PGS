import type { User } from '@/types';

export interface NavItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    gate?: keyof PageAccess;
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
 * Top-navbar menu groups — mirrors the legacy navbar.php structure
 * (dropdown menus across the top, no sidebar). Gated groups follow the
 * per-user page access rows; admins see everything.
 */
export function navGroupsFor(user: User, pageAccess: PageAccess): NavGroup[] {
    const can = (gate: keyof PageAccess): boolean => user.role === 'admin' || pageAccess[gate];

    const groups: NavGroup[] = [
        {
            title: 'Roadmaps',
            gate: 'roadmaps',
            items: [
                { title: 'Sector Roadmaps', href: '/sectors' },
                { title: 'Roadmaps', href: '/roadmaps' },
                { title: 'Deliverables', href: '/deliverables' },
            ],
        },
        {
            title: 'Scorecard',
            gate: 'scorecard',
            items: [{ title: 'Impact Scorecard', href: '/impact-scorecard' }],
        },
        {
            title: 'Performance Assessment',
            gate: 'performance_assessment',
            items: [
                { title: 'Operations Review', href: '/uploads/operations-review' },
                { title: 'Strategy Review', href: '/uploads/strategy-review' },
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
                { title: 'User Management', href: '/users' },
                { title: 'Deadlines', href: '/deadlines' },
                { title: 'Notices', href: '/notices' },
                { title: 'Backups', href: '/backups' },
                { title: 'Mailbox', href: '/mailbox' },
                { title: 'Audit Log', href: '/audit-logs' },
            ],
        });
    }

    return groups.filter((group) => group.gate === undefined || can(group.gate));
}

export function isRouteActive(href: string): boolean {
    return route().current() === href.replace(/^\//, '');
}
