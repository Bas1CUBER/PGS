import { Button } from '@/components/ui/button';
import { relativeInternalUrl } from '@/lib/relative-url';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationProps {
    links: PaginationLink[];
}

export function Pagination({ links }: PaginationProps) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex justify-center gap-2">
            {links.map((link, index) => (
                <span key={index}>
                    {link.url ? (
                        <Button asChild variant={link.active ? 'default' : 'ghost'} size="sm">
                            <a href={relativeInternalUrl(link.url) ?? '#'}>
                                {link.label.replace(/&laquo;|&raquo;/g, '')}
                            </a>
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
