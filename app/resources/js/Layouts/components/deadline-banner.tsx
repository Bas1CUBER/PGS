import { AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { DeadlineState } from '@/types';

function deadlineState(deadline: DeadlineState | null) {
    if (deadline === null || !deadline.enabled || deadline.end_time === null) {
        return { active: false, warning: false } as const;
    }

    const hoursLeft = (new Date(deadline.end_time).getTime() - Date.now()) / 36e5;

    return {
        active: hoursLeft > 0,
        warning: hoursLeft > 0 && hoursLeft <= 72,
    } as const;
}

export function DeadlineBanner({ deadline }: { deadline: DeadlineState | null }) {
    const deadlineInfo = deadlineState(deadline);

    if (!deadlineInfo.active) return null;

    return (
        <div
            className={cn(
                'flex items-center gap-2 border-b px-4 py-2 text-sm sm:px-6',
                deadlineInfo.warning
                    ? 'border-warning/50 bg-warning/10 text-warning-foreground'
                    : 'border-border bg-muted text-muted-foreground',
            )}
        >
            <AlertTriangle className="size-4 shrink-0" />
            <span>{deadline?.message}</span>
            {deadline?.end_time != null && (
                <span className="ml-auto shrink-0 font-medium">
                    {new Date(deadline.end_time).toLocaleString()}
                </span>
            )}
        </div>
    );
}
