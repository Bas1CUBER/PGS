import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArchiveRestore,
    BookOpen,
    Building2,
    CalendarClock,
    ChartNoAxesCombined,
    CheckSquare,
    ChevronRight,
    CircleHelp,
    CircleUserRound,
    ClipboardCheck,
    FileCheck2,
    GalleryHorizontalEnd,
    Gauge,
    KeyRound,
    Landmark,
    LayoutDashboard,
    LogOut,
    Megaphone,
    Menu,
    Network,
    RefreshCcw,
    Settings2,
    ShieldCheck,
    SquareKanban,
    Users,
    Workflow,
} from 'lucide-react';
import { useState, type MouseEvent } from 'react';
import { cn } from '@/lib/utils';
import type { User } from '@/types';
import { isRouteActive, type NavGroup, type NavItem } from './nav-config';

interface AuthenticatedSidebarProps {
    groups: NavGroup[];
    links: NavItem[];
    currentUrl: string;
    user: User;
    collapsed: boolean;
    onExpand: () => void;
    mobileOpen: boolean;
    onCloseMobile: () => void;
}

const groupIcons: Partial<Record<string, LucideIcon>> = {
    Roadmaps: Network,
    Scorecard: ChartNoAxesCombined,
    'Performance Assessment': ClipboardCheck,
    Cascading: Workflow,
    Governance: Landmark,
    Organization: Building2,
    About: BookOpen,
    Others: Settings2,
};

const itemIcons: { match: RegExp; icon: LucideIcon }[] = [
    { match: /dashboard/i, icon: LayoutDashboard },
    { match: /research|strategy|roadmap/i, icon: SquareKanban },
    { match: /impact|scorecard|indicator/i, icon: Gauge },
    { match: /review|assessment/i, icon: FileCheck2 },
    { match: /refresh/i, icon: RefreshCcw },
    { match: /communication|notice/i, icon: Megaphone },
    { match: /gallery/i, icon: GalleryHorizontalEnd },
    { match: /culture|governance/i, icon: ShieldCheck },
    { match: /user/i, icon: Users },
    { match: /backup/i, icon: ArchiveRestore },
    { match: /deadline/i, icon: CalendarClock },
    { match: /survey|check/i, icon: CheckSquare },
    { match: /profile|password/i, icon: KeyRound },
];

function iconForItem(item: NavItem): LucideIcon {
    return itemIcons.find(({ match }) => match.test(item.title))?.icon ?? CircleHelp;
}

function Avatar({ initials, size = 'small' }: { initials: string; size?: 'small' | 'medium' }) {
    return (
        <span className={`avatar avatar-${size}`} aria-hidden="true">
            <span>{initials}</span>
        </span>
    );
}

export default function AuthenticatedSidebar({
    groups,
    links,
    currentUrl,
    user,
    collapsed,
    onExpand,
    mobileOpen,
    onCloseMobile,
}: AuthenticatedSidebarProps) {
    const [profileOpen, setProfileOpen] = useState(false);
    const [tooltip, setTooltip] = useState<{ label: string; top: number } | null>(null);
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(
            groups.map((group) => [
                group.title,
                group.items.some((item) => isRouteActive(item.href, currentUrl)),
            ]),
        ),
    );
    const initials = (user.name ?? user.email).slice(0, 2).toUpperCase();

    function showTooltip(target: Element): void {
        const label = target.getAttribute('data-tooltip');

        if (!collapsed || label === null) return;

        const bounds = target.getBoundingClientRect();
        setTooltip({
            label,
            top: Math.round(bounds.top + bounds.height / 2),
        });
    }

    function closeTooltip(): void {
        setTooltip(null);
    }

    function handleNavigation(event: MouseEvent): void {
        closeTooltip();
        setProfileOpen(false);
        if (window.matchMedia('(max-width: 980px)').matches) onCloseMobile();
        // Let Inertia perform the navigation; this handler only mirrors the
        // reference shell's interaction state.
        if (event.currentTarget instanceof HTMLElement) event.currentTarget.blur();
    }

    return (
        <aside className={cn('sidebar', mobileOpen && 'is-open')} id="main-sidebar">
            {tooltip !== null && (
                <div
                    className="sidebar-tooltip"
                    data-tooltip-top={`${String(tooltip.top)}px`}
                    role="tooltip"
                >
                    {tooltip.label}
                </div>
            )}
            <div className="brand-lockup">
                <Link href="/dashboard" aria-label="PGS dashboard" className="pgs-brand-logo-link">
                    <img
                        src="/legacy-img/final_logo1.png"
                        alt="Performance Governance System - TRC DOH"
                        className="pgs-brand-logo"
                    />
                    <img
                        src="/legacy-img/pgs_logo_solo.png"
                        alt=""
                        aria-hidden="true"
                        className="pgs-brand-logo-solo"
                    />
                </Link>
                <button
                    className="icon-button sidebar-close"
                    type="button"
                    aria-label="Close sidebar"
                    onClick={onCloseMobile}
                >
                    <Menu size={18} />
                </button>
            </div>

            <nav className="sidebar-nav" aria-label="PGS sections">
                <Link
                    className={isRouteActive('/dashboard', currentUrl) ? 'active' : ''}
                    href="/dashboard"
                    aria-label="Dashboard"
                    data-tooltip={collapsed ? 'Dashboard' : undefined}
                    onMouseEnter={(event) => {
                        showTooltip(event.currentTarget);
                    }}
                    onMouseLeave={closeTooltip}
                    onFocus={(event) => {
                        showTooltip(event.currentTarget);
                    }}
                    onBlur={closeTooltip}
                    onClick={handleNavigation}
                >
                    <LayoutDashboard size={17} />
                    <span>Dashboard</span>
                </Link>

                {links.map((item) => {
                    const Icon = iconForItem(item);
                    const active = isRouteActive(item.href, currentUrl);

                    return (
                        <Link
                            className={active ? 'active' : ''}
                            href={item.href}
                            key={item.href}
                            aria-label={item.title}
                            data-tooltip={collapsed ? item.title : undefined}
                            onMouseEnter={(event) => {
                                showTooltip(event.currentTarget);
                            }}
                            onMouseLeave={closeTooltip}
                            onFocus={(event) => {
                                showTooltip(event.currentTarget);
                            }}
                            onBlur={closeTooltip}
                            onClick={handleNavigation}
                        >
                            <Icon size={17} />
                            <span>{item.title}</span>
                        </Link>
                    );
                })}

                {groups.map((group) => {
                    const GroupIcon = groupIcons[group.title] ?? CircleHelp;
                    const groupActive = group.items.some((item) =>
                        isRouteActive(item.href, currentUrl),
                    );
                    const groupOpen = openGroups[group.title] ?? groupActive;
                    const submenuId = `sidebar-submenu-${group.title
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')}`;

                    return (
                        <div className="sidebar-nav-group" key={group.title}>
                            <button
                                className={cn('sidebar-nav-group-trigger', groupActive && 'active')}
                                type="button"
                                aria-label={group.title}
                                aria-controls={submenuId}
                                aria-expanded={groupOpen}
                                data-tooltip={collapsed ? group.title : undefined}
                                onMouseEnter={(event) => {
                                    showTooltip(event.currentTarget);
                                }}
                                onMouseLeave={closeTooltip}
                                onFocus={(event) => {
                                    showTooltip(event.currentTarget);
                                }}
                                onBlur={closeTooltip}
                                onClick={() => {
                                    closeTooltip();
                                    if (collapsed && group.items.length > 0) onExpand();
                                    setOpenGroups(() =>
                                        Object.fromEntries(
                                            groups.map(({ title }) => [
                                                title,
                                                !groupOpen && title === group.title,
                                            ]),
                                        ),
                                    );
                                }}
                            >
                                <GroupIcon size={17} />
                                <span className="sidebar-nav-group-label">{group.title}</span>
                                <ChevronRight
                                    className={cn(
                                        'sidebar-nav-group-chevron',
                                        groupOpen && 'is-open',
                                    )}
                                    size={15}
                                    aria-hidden="true"
                                />
                            </button>

                            <div
                                className={cn('sidebar-submenu', groupOpen && 'is-open')}
                                id={submenuId}
                                aria-hidden={!groupOpen}
                                inert={!groupOpen}
                            >
                                <div className="sidebar-submenu-inner">
                                    {group.items.map((item) => {
                                        const active = isRouteActive(item.href, currentUrl);

                                        return (
                                            <Link
                                                className={cn(
                                                    'sidebar-submenu-link',
                                                    active && 'active',
                                                )}
                                                href={item.href}
                                                key={item.href}
                                                aria-label={item.title}
                                                data-tooltip={collapsed ? item.title : undefined}
                                                onMouseEnter={(event) => {
                                                    showTooltip(event.currentTarget);
                                                }}
                                                onMouseLeave={closeTooltip}
                                                onFocus={(event) => {
                                                    showTooltip(event.currentTarget);
                                                }}
                                                onBlur={closeTooltip}
                                                onClick={(event) => {
                                                    setOpenGroups((open) => ({
                                                        ...open,
                                                        [group.title]: true,
                                                    }));
                                                    handleNavigation(event);
                                                }}
                                            >
                                                <span>{item.title}</span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </nav>

            <div
                className="sidebar-profile"
                onClick={(event) => {
                    event.stopPropagation();
                }}
            >
                <button
                    className="sidebar-profile-trigger"
                    type="button"
                    aria-label="Profile menu"
                    aria-haspopup="menu"
                    aria-expanded={profileOpen}
                    aria-controls="sidebar-profile-menu"
                    data-tooltip={collapsed && !profileOpen ? 'Profile' : undefined}
                    onMouseEnter={(event) => {
                        showTooltip(event.currentTarget);
                    }}
                    onMouseLeave={closeTooltip}
                    onFocus={(event) => {
                        showTooltip(event.currentTarget);
                    }}
                    onBlur={closeTooltip}
                    onClick={() => {
                        closeTooltip();
                        setProfileOpen((open) => !open);
                    }}
                >
                    <Avatar initials={initials} />
                    <span>
                        <strong>{user.name ?? user.email}</strong>
                        <small>{user.role.charAt(0).toUpperCase() + user.role.slice(1)}</small>
                    </span>
                </button>

                {profileOpen && (
                    <div
                        className="sidebar-profile-card"
                        id="sidebar-profile-menu"
                        role="menu"
                        aria-label="Sidebar profile"
                        onKeyDown={(event) => {
                            if (event.key === 'Escape') setProfileOpen(false);
                        }}
                    >
                        <div className="profile-card-identity">
                            <Avatar initials={initials} size="medium" />
                            <span>
                                <strong>{user.name ?? user.email}</strong>
                                <small>{user.email}</small>
                            </span>
                        </div>
                        <div className="profile-card-divider" />
                        <Link
                            href="/profile"
                            role="menuitem"
                            onClick={() => {
                                setProfileOpen(false);
                            }}
                        >
                            <CircleUserRound size={15} />
                            Profile
                        </Link>
                        <div className="profile-card-divider" />
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="logout"
                            role="menuitem"
                            onClick={() => {
                                setProfileOpen(false);
                                onCloseMobile();
                            }}
                        >
                            <LogOut size={15} />
                            Log out
                        </Link>
                    </div>
                )}
            </div>
        </aside>
    );
}
