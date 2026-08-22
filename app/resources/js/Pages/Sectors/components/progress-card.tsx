import { ListChecks } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { SectorProgress } from './types';

const statusStyles: Record<string, string> = {
    Completed: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Ongoing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    Pending: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

interface ProgressCardProps {
    progress: SectorProgress[];
}

export function ProgressCard({ progress }: ProgressCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ListChecks className="size-4" />
                    Progress
                </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                {progress.length === 0 ? (
                    <p className="text-muted-foreground px-6 pb-6 text-sm">
                        No progress entries.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {progress.map((entry) => (
                            <li
                                key={entry.id}
                                className="flex items-center justify-between gap-3 px-6 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {entry.category} - {entry.year}
                                        {entry.month !== '' ? ` (${entry.month})` : ''}
                                    </p>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {entry.remarks ?? entry.description ?? ''}
                                    </p>
                                </div>
                                <Badge
                                    variant="outline"
                                    className={cn(statusStyles[entry.status] ?? '')}
                                >
                                    {entry.status}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
