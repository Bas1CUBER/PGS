import { CheckCircle2, CircleDashed, CircleX } from 'lucide-react';
import { PgsStatCard } from '@/components/pgs-stat-card';

interface ProgressSummaryCardsProps {
    progressSummary: Record<string, number | undefined>;
}

export function ProgressSummaryCards({ progressSummary }: ProgressSummaryCardsProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <PgsStatCard
                label="Accomplished"
                value={progressSummary.Accomplished ?? 0}
                icon={<CheckCircle2 className="size-5" />}
                status="Complete"
                detail="Completed indicators"
                tone="green"
                compact
            />
            <PgsStatCard
                label="Ongoing"
                value={progressSummary.Ongoing ?? 0}
                icon={<CircleDashed className="size-5" />}
                status="Active"
                detail="Indicators in progress"
                tone="blue"
                compact
            />
            <PgsStatCard
                label="Not accomplished / started"
                value={progressSummary['Not Accomplished/Started'] ?? 0}
                icon={<CircleX className="size-5" />}
                status="Needs attention"
                detail="Indicators needing action"
                tone="red"
                compact
            />
        </div>
    );
}
