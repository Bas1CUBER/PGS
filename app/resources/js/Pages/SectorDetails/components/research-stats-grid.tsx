import { Activity, BookOpen, CircleCheck, Presentation } from 'lucide-react';
import { PgsStatCard, type PgsStatTone } from '@/components/pgs-stat-card';
import type { ResearchStatKey, SectorDetailPageProps } from './types';

type SectorDetailStats = NonNullable<SectorDetailPageProps['stats']>;

const researchStatDefinitions: {
    key: ResearchStatKey;
    label: string;
    status: string;
    detail: string;
    tone: PgsStatTone;
    icon: typeof Activity;
}[] = [
    {
        key: 'ongoing',
        label: 'On-going',
        status: 'In progress',
        detail: 'Active research work',
        tone: 'blue',
        icon: Activity,
    },
    {
        key: 'completed',
        label: 'Completed',
        status: 'Submitted',
        detail: 'Submitted outputs',
        tone: 'green',
        icon: CircleCheck,
    },
    {
        key: 'presented',
        label: 'Presented',
        status: 'Presented',
        detail: 'Shared research outputs',
        tone: 'violet',
        icon: Presentation,
    },
    {
        key: 'published',
        label: 'Published',
        status: 'Published',
        detail: 'Published outputs',
        tone: 'amber',
        icon: BookOpen,
    },
];

interface ResearchStatsGridProps {
    stats: SectorDetailStats;
}

export function ResearchStatsGrid({ stats }: ResearchStatsGridProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {researchStatDefinitions.map((stat) => {
                const Icon = stat.icon;

                return (
                    <PgsStatCard
                        key={stat.key}
                        compact
                        label={stat.label}
                        value={stats[stat.key]}
                        icon={<Icon className="size-5" />}
                        status={stat.status}
                        detail={stat.detail}
                        tone={stat.tone}
                    />
                );
            })}
        </div>
    );
}
