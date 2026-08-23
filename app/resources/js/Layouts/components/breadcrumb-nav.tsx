import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { BreadcrumbItem } from '@/Layouts/components/breadcrumbs';

export function BreadcrumbNav({ breadcrumbs }: { breadcrumbs: BreadcrumbItem[] }) {
    return (
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
    );
}
