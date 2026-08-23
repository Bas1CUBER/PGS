import { isRouteActive, type NavGroup, type NavItem } from '@/components/nav-config';
import { normalizedPath } from '@/Layouts/components/page-width';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

export function breadcrumbItems(
    url: string,
    groups: NavGroup[],
    links: NavItem[],
    utilityLinks: NavItem[],
): BreadcrumbItem[] {
    const path = normalizedPath(url);

    if (path === '/dashboard') return [{ label: 'Dashboard' }];

    const uploadBreadcrumbs: Record<string, { label: string; rootHref?: string } | undefined> = {
        '/operations-review/upload': {
            label: 'Operations Review',
            rootHref: '/operations-review',
        },
        '/strategy-review/upload': {
            label: 'Strategy Review',
            rootHref: '/strategy-review',
        },
        '/communication-plan/upload': {
            label: 'Communication Plan',
            rootHref: '/communication-plan',
        },
        '/strategy-refresh/upload': { label: 'Strategy Refresh' },
        '/cascading-activities/upload': { label: 'Cascading Activities' },
        '/resources/upload': { label: 'Resources' },
        '/governance-culture/upload': { label: 'Governance Culture' },
        '/governance-sharing/upload': { label: 'Governance Sharing' },
    };
    const uploadBreadcrumb = uploadBreadcrumbs[path];

    if (uploadBreadcrumb !== undefined) {
        const group = groups.find(({ items }) =>
            items.some(
                (item) =>
                    item.href === path ||
                    (uploadBreadcrumb.rootHref !== undefined &&
                        item.href === uploadBreadcrumb.rootHref),
            ),
        );

        return [
            ...(group === undefined
                ? []
                : [
                      {
                          label: group.title,
                          href: group.href,
                      },
                  ]),
            {
                label: uploadBreadcrumb.label,
                href: uploadBreadcrumb.rootHref,
            },
            { label: 'Upload register' },
        ];
    }

    const groupedMatches = groups
        .flatMap((group) => group.items.map((item) => ({ group, item })))
        .filter(({ item }) => isRouteActive(item.href, url))
        .sort((left, right) => right.item.href.length - left.item.href.length);

    if (groupedMatches.length > 0) {
        const groupedMatch = groupedMatches[0];
        const groupTarget = groupedMatch.group.href ?? groupedMatch.group.items[0].href;
        const groupHref = normalizedPath(groupTarget) !== path ? groupTarget : undefined;

        return [
            { label: groupedMatch.group.title, href: groupHref },
            { label: groupedMatch.item.title },
        ];
    }

    const directMatches = [...links, ...utilityLinks]
        .filter((item) => isRouteActive(item.href, url))
        .sort((left, right) => right.href.length - left.href.length);

    if (directMatches.length > 0) {
        const directMatch = directMatches[0];

        return [{ label: directMatch.title }];
    }

    const fallbackSegment = path.slice(path.lastIndexOf('/') + 1);
    const fallbackLabel =
        fallbackSegment === ''
            ? 'Page'
            : fallbackSegment
                  .replace(/[-_]+/g, ' ')
                  .replace(/\b\w/g, (letter) => letter.toUpperCase());

    return [{ label: fallbackLabel }];
}
