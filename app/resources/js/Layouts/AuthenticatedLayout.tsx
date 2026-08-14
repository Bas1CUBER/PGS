import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    BarChart3,
    Bell,
    BookOpen,
    Building2,
    ChevronRight,
    ClipboardCheck,
    ClipboardList,
    FileText,
    FolderTree,
    GitBranch,
    History,
    Landmark,
    LayoutDashboard,
    Mail,
    Menu,
    Network,
    Search,
    SlidersHorizontal,
    UserCircle,
    type LucideIcon,
} from 'lucide-react';
import { type PropsWithChildren, type ReactNode, useCallback, useEffect, useState } from 'react';
import AuthenticatedSidebar from '@/components/authenticated-sidebar';
import {
    isRouteActive,
    navGroupsFor,
    utilityLinksFor,
    type NavGroup,
    type NavItem,
} from '@/components/nav-config';
import NotificationBell from '@/components/notification-bell';
import { useToast } from '@/components/pgs-toast';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    return user.role === 'admin' ? [] : [{ title: 'Survey', href: '/surveys' }];
}

interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface SearchPaletteItem extends NavItem {
    group: string;
    description: string;
    section: 'quick' | 'navigate';
    icon: LucideIcon;
}

const paletteGroupIcons: Record<string, LucideIcon> = {
    Roadmaps: Network,
    Scorecard: BarChart3,
    'Performance Assessment': ClipboardCheck,
    Cascading: GitBranch,
    Governance: Landmark,
    Organization: Building2,
    About: BookOpen,
    Others: SlidersHorizontal,
};

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

function normalizedPath(url: string): string {
    return url.split('?')[0].replace(/\/+$/, '') || '/';
}

type PageWidth = 'compact' | 'standard' | 'wide';

function pageWidthFor(path: string): PageWidth {
    // Compact: focused forms, small controls, and detail views.
    if (
        path === '/deadlines' ||
        path === '/profile' ||
        path === '/notifications' ||
        path === '/confirm-password' ||
        path === '/verify-email' ||
        path === '/mailbox' ||
        path.startsWith('/mailbox/') ||
        path === '/users/create' ||
        (path.startsWith('/users/') && path.endsWith('/edit')) ||
        path === '/deliverables/create' ||
        (path.startsWith('/deliverables/') && path.endsWith('/edit'))
    ) {
        return 'compact';
    }

    // Wide: dashboards, matrices, multi-column registers, and roadmap workspaces.
    if (
        path === '/dashboard' ||
        path === '/roadmaps' ||
        path === '/impact-scorecard' ||
        path === '/sectors' ||
        path.startsWith('/sectors/') ||
        path === '/operations-review' ||
        path === '/strategy-review' ||
        path === '/opcr' ||
        path.startsWith('/annex/') ||
        path === '/uploads/operations-review' ||
        path === '/uploads/strategy-review' ||
        path === '/uploads/strategy-refresh' ||
        path === '/uploads/governance-culture' ||
        path === '/uploads/governance-sharing'
    ) {
        return 'wide';
    }

    // Standard: ordinary tables, cards, forms, and content pages.
    return 'standard';
}

function breadcrumbItems(
    url: string,
    groups: NavGroup[],
    links: NavItem[],
    utilityLinks: NavItem[],
): BreadcrumbItem[] {
    const path = normalizedPath(url);

    if (path === '/dashboard') return [{ label: 'Dashboard' }];

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

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const page = usePage<PageProps>();
    const { auth, deadline, flash, pageAccess } = page.props;
    const user = auth.user;
    const { url } = page;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        if (typeof window === 'undefined') return false;

        try {
            return window.localStorage.getItem('pgs-sidebar-collapsed') === 'true';
        } catch {
            return false;
        }
    });
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const { showToast } = useToast();

    const toggleSidebar = useCallback(() => {
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 980px)').matches) {
            setMobileOpen((open) => !open);
            return;
        }

        setSidebarCollapsed((collapsed) => !collapsed);
    }, []);

    useEffect(() => {
        const handleShortcut = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                if (window.matchMedia('(max-width: 720px)').matches) return;

                event.preventDefault();
                setSearchOpen(true);
            }

            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'b') {
                event.preventDefault();
                toggleSidebar();
            }

            if (event.key === 'Escape') {
                setMobileOpen(false);
            }
        };

        window.addEventListener('keydown', handleShortcut);
        return () => {
            window.removeEventListener('keydown', handleShortcut);
        };
    }, [toggleSidebar]);

    useEffect(() => {
        try {
            window.localStorage.setItem('pgs-sidebar-collapsed', String(sidebarCollapsed));
        } catch {
            // Keep the in-memory preference when storage is unavailable.
        }
    }, [sidebarCollapsed]);

    useEffect(() => {
        if (flash.success) showToast('success', flash.success);
        if (flash.error) showToast('error', flash.error);
        if (flash.warning) showToast('warning', flash.warning);
        if (flash.info) showToast('info', flash.info);
    }, [flash, showToast]);

    if (user === null) return null;

    const groups = navGroupsFor(user, pageAccess);
    const links = quickLinks(user);
    const utilityLinks = utilityLinksFor(user);
    const breadcrumbs = page.props.breadcrumbs ?? breadcrumbItems(url, groups, links, utilityLinks);
    const pageWidth = pageWidthFor(normalizedPath(url));
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
    const searchItems: SearchPaletteItem[] = [
        ...quickSearchItems,
        ...navItems
            .filter((item) => !quickSearchHrefs.has(item.href))
            .map((item) => ({
                ...item,
                description: `Go to ${item.group.toLowerCase()}`,
                section: 'navigate' as const,
                icon: paletteGroupIcons[item.group] ?? FolderTree,
            })),
    ];
    const normalizedSearch = searchQuery.trim().toLowerCase();
    const filteredSearchItems =
        normalizedSearch === ''
            ? searchItems
            : searchItems.filter((item) =>
                  `${item.title} ${item.group}`.toLowerCase().includes(normalizedSearch),
              );
    const quickResults = filteredSearchItems.filter((item) => item.section === 'quick');
    const navigateResults = filteredSearchItems.filter((item) => item.section === 'navigate');
    const deadlineInfo = deadlineState(deadline);

    const renderSearchItem = (item: SearchPaletteItem) => {
        const ItemIcon = item.icon;
        const active = isRouteActive(item.href, url);

        return (
            <Link
                key={`${item.group}-${item.href}`}
                href={item.href}
                onClick={() => {
                    setSearchOpen(false);
                    setSearchQuery('');
                }}
                className={cn('pgs-command-item', active && 'is-active')}
                role="option"
                aria-selected={active}
            >
                <span className="pgs-command-icon" aria-hidden="true">
                    <ItemIcon size={17} strokeWidth={1.8} />
                </span>
                <span className="pgs-command-item-copy">
                    <strong>{item.title}</strong>
                    <small>{item.description}</small>
                </span>
                {item.section === 'quick' ? (
                    <span className="pgs-command-item-meta">{item.group}</span>
                ) : (
                    <span className="pgs-command-item-meta">
                        {item.group}
                        <ChevronRight size={16} aria-hidden="true" />
                    </span>
                )}
            </Link>
        );
    };

    return (
        <div className={cn('ui-kit', sidebarCollapsed && 'sidebar-collapsed')}>
            <button
                className={cn('mobile-scrim', mobileOpen && 'is-visible')}
                type="button"
                aria-label="Close sidebar"
                onClick={() => {
                    setMobileOpen(false);
                }}
            />

            <AuthenticatedSidebar
                groups={groups}
                links={links}
                currentUrl={url}
                user={user}
                collapsed={sidebarCollapsed}
                onExpand={() => {
                    setSidebarCollapsed(false);
                }}
                mobileOpen={mobileOpen}
                onCloseMobile={() => {
                    setMobileOpen(false);
                }}
            />

            <div className="app-column">
                <header className="navbar">
                    <div className="navbar-left">
                        <button
                            className="icon-button hamburger"
                            type="button"
                            aria-label={
                                mobileOpen
                                    ? 'Close sidebar'
                                    : sidebarCollapsed
                                      ? 'Expand sidebar'
                                      : 'Collapse sidebar'
                            }
                            aria-controls="main-sidebar"
                            aria-expanded={mobileOpen || !sidebarCollapsed}
                            onClick={toggleSidebar}
                        >
                            <Menu size={20} />
                        </button>
                        <nav className="breadcrumbs" aria-label="Breadcrumb">
                            {breadcrumbs.map((breadcrumb, index) => {
                                const isCurrent = index === breadcrumbs.length - 1;

                                return (
                                    <span className="breadcrumb-segment" key={breadcrumb.label}>
                                        {index > 0 && <ChevronRight size={14} aria-hidden="true" />}
                                        {breadcrumb.href !== undefined && !isCurrent ? (
                                            <Link href={breadcrumb.href}>
                                                <strong>{breadcrumb.label}</strong>
                                            </Link>
                                        ) : (
                                            <strong aria-current={isCurrent ? 'page' : undefined}>
                                                {breadcrumb.label}
                                            </strong>
                                        )}
                                    </span>
                                );
                            })}
                        </nav>
                    </div>

                    <div className="navbar-actions">
                        <div className="navbar-search">
                            <Search size={16} />
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchOpen(true);
                                }}
                            >
                                Search components...
                            </button>
                            <kbd className="navbar-shortcut" aria-label="Control K shortcut">
                                Ctrl K
                            </kbd>
                        </div>
                        <NotificationBell />
                    </div>
                </header>

                {deadlineInfo.active && (
                    <div
                        className={cn(
                            'flex items-center gap-2 border-b px-4 py-2 text-sm sm:px-6',
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

                <main className={cn('content-shell', `page-width-${pageWidth}`)}>
                    {header && <div className="mb-4">{header}</div>}
                    {children}
                </main>
            </div>

            <Dialog
                open={searchOpen}
                onOpenChange={(open) => {
                    setSearchOpen(open);
                    if (!open) setSearchQuery('');
                }}
            >
                <DialogContent className="pgs-command-palette" showCloseButton={false}>
                    <DialogHeader className="sr-only">
                        <DialogTitle>Search PGS</DialogTitle>
                        <DialogDescription>Find a page or workspace section.</DialogDescription>
                    </DialogHeader>
                    <div className="pgs-command-search">
                        <Search size={18} aria-hidden="true" />
                        <Input
                            autoFocus
                            value={searchQuery}
                            onChange={(event) => {
                                setSearchQuery(event.target.value);
                            }}
                            placeholder="Search pages and sections..."
                            className="pgs-command-input"
                        />
                        <kbd>Esc</kbd>
                    </div>
                    <div className="pgs-command-body">
                        {filteredSearchItems.length === 0 ? (
                            <p className="pgs-command-empty">No matching pages found.</p>
                        ) : (
                            <div role="listbox" aria-label="Search results">
                                {quickResults.length > 0 && (
                                    <section
                                        className="pgs-command-section"
                                        aria-labelledby="quick-actions"
                                    >
                                        <h3 id="quick-actions">Quick actions</h3>
                                        <div className="pgs-command-list">
                                            {quickResults.map(renderSearchItem)}
                                        </div>
                                    </section>
                                )}
                                {navigateResults.length > 0 && (
                                    <section
                                        className="pgs-command-section"
                                        aria-labelledby="navigate-pages"
                                    >
                                        <h3 id="navigate-pages">Navigate</h3>
                                        <div className="pgs-command-list">
                                            {navigateResults.map(renderSearchItem)}
                                        </div>
                                    </section>
                                )}
                            </div>
                        )}
                    </div>
                    <footer className="pgs-command-footer">
                        <span>
                            <kbd>Ctrl K</kbd>
                            <span>PGS command</span>
                        </span>
                        <span>
                            <kbd>Up</kbd>
                            <kbd>Down</kbd>
                            <span>Navigate</span>
                            <kbd>Enter</kbd>
                            <span>Select</span>
                        </span>
                    </footer>
                </DialogContent>
            </Dialog>
        </div>
    );
}
