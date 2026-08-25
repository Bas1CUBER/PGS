import { Link } from '@inertiajs/react';
import { ChevronRight, Search } from 'lucide-react';
import { isRouteActive } from '@/components/nav-config';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { SearchPaletteItem } from '@/Layouts/components/command-palette-items';

interface CommandPaletteProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    items: SearchPaletteItem[];
    query: string;
    onQueryChange: (query: string) => void;
    currentUrl: string;
}

export function CommandPalette({
    open,
    onOpenChange,
    onClose,
    items,
    query,
    onQueryChange,
    currentUrl,
}: CommandPaletteProps) {
    const normalizedSearch = query.trim().toLowerCase();
    const filteredSearchItems =
        normalizedSearch === ''
            ? items
            : items.filter((item) =>
                  `${item.title} ${item.group}`.toLowerCase().includes(normalizedSearch),
              );
    const quickResults = filteredSearchItems.filter((item) => item.section === 'quick');
    const navigateResults = filteredSearchItems.filter((item) => item.section === 'navigate');

    const renderSearchItem = (item: SearchPaletteItem) => {
        const ItemIcon = item.icon;
        const active = isRouteActive(item.href, currentUrl);

        return (
            <Link
                key={`${item.group}-${item.href}`}
                href={item.href}
                onClick={onClose}
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
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-command-palette" showCloseButton={false}>
                <DialogHeader className="sr-only">
                    <DialogTitle>Search PGS</DialogTitle>
                    <DialogDescription>Find a page or workspace section.</DialogDescription>
                </DialogHeader>
                <div className="pgs-command-search">
                    <Search size={18} aria-hidden="true" />
                    <Input
                        autoFocus
                        value={query}
                        onChange={(event) => {
                            onQueryChange(event.target.value);
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
                        <span>Type to filter, Tab to navigate</span>
                    </span>
                </footer>
            </DialogContent>
        </Dialog>
    );
}
