import { Menu, Search } from 'lucide-react';
import NotificationBell from '@/components/notification-bell';
import { BreadcrumbNav } from '@/Layouts/components/breadcrumb-nav';
import type { BreadcrumbItem } from '@/Layouts/components/breadcrumbs';

interface AppHeaderProps {
    mobileOpen: boolean;
    sidebarCollapsed: boolean;
    onToggleSidebar: () => void;
    breadcrumbs: BreadcrumbItem[];
    onOpenSearch: () => void;
}

export function AppHeader({
    mobileOpen,
    sidebarCollapsed,
    onToggleSidebar,
    breadcrumbs,
    onOpenSearch,
}: AppHeaderProps) {
    return (
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
                    onClick={onToggleSidebar}
                >
                    <Menu size={20} />
                </button>
                <BreadcrumbNav breadcrumbs={breadcrumbs} />
            </div>

            <div className="navbar-actions">
                <div className="navbar-search">
                    <Search size={16} />
                    <button
                        type="button"
                        onClick={onOpenSearch}
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
    );
}
