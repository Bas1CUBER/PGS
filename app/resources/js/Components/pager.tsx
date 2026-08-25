import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { relativeInternalUrl } from '@/lib/relative-url';

interface PagerLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PagerProps {
    links: PagerLink[];
}

export function Pager({ links }: PagerProps): React.ReactNode {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-2">
            {links.map((link, index) => (
                <span key={index}>
                    {link.url ? (
                        <Button
                            asChild
                            variant={link.active ? 'default' : 'ghost'}
                            size="sm"
                        >
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

export default Pager;
