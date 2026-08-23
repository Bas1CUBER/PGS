import { usePage } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode, useCallback, useEffect, useState } from 'react';
import AuthenticatedSidebar from '@/components/authenticated-sidebar';
import { navGroupsFor, utilityLinksFor } from '@/components/nav-config';
import { useToast } from '@/components/pgs-toast';
import { AppHeader } from '@/Layouts/components/app-header';
import { breadcrumbItems } from '@/Layouts/components/breadcrumbs';
import { CommandPalette } from '@/Layouts/components/command-palette';
import { buildSearchItems } from '@/Layouts/components/command-palette-items';
import { DeadlineBanner } from '@/Layouts/components/deadline-banner';
import { normalizedPath, pageWidthFor } from '@/Layouts/components/page-width';
import { quickLinks } from '@/Layouts/components/quick-links';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

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
            return;
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
    const searchItems = buildSearchItems(links, utilityLinks, groups);

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
                <AppHeader
                    mobileOpen={mobileOpen}
                    sidebarCollapsed={sidebarCollapsed}
                    onToggleSidebar={toggleSidebar}
                    breadcrumbs={breadcrumbs}
                    onOpenSearch={() => {
                        setSearchOpen(true);
                    }}
                />

                <DeadlineBanner deadline={deadline} />

                <main className={cn('content-shell', `page-width-${pageWidth}`)}>
                    {header && <div className="mb-4">{header}</div>}
                    {children}
                </main>
            </div>

            <CommandPalette
                open={searchOpen}
                onOpenChange={(open) => {
                    setSearchOpen(open);
                    if (!open) setSearchQuery('');
                }}
                onClose={() => {
                    setSearchOpen(false);
                    setSearchQuery('');
                }}
                items={searchItems}
                query={searchQuery}
                onQueryChange={setSearchQuery}
                currentUrl={url}
            />
        </div>
    );
}
