import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const statusStyles: Record<string, string> = {
    Approved: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    Returned: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    'In Progress': 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
};

interface StatusBadgeProps {
    status: string | null;
}

export function StatusBadge({ status }: StatusBadgeProps) {
    return (
        <Badge variant="outline" className={cn(statusStyles[status ?? ''] ?? '')}>
            {status ?? '—'}
        </Badge>
    );
}
