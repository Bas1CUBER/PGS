import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { relativeInternalUrl } from '@/lib/relative-url';

interface PaginationLinksProps {
    links: { url: string | null; label: string; active: boolean }[];
}

export function PaginationLinks({ links }: PaginationLinksProps) {
    return (
        <div className="flex justify-center gap-2">
            {links.map((link, index) => (
                <span key={index}>
                    {link.url ? (
                        <Button asChild variant={link.active ? 'default' : 'ghost'} size="sm">
                            <Link href={relativeInternalUrl(link.url) ?? '#'}>
                                {link.label.replace(/&laquo;|&raquo;/g, '')}
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="ghost" size="sm" disabled>
                            {link.label.replace(/&laquo;|&raquo;/g, '')}
                        </Button>
                    )}
                </span>
            ))}
        </div>
    );
}
