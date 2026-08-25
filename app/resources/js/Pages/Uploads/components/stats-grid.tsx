import { CircleCheck, FileText, Hourglass, Image as ImageIcon, List, Undo2 } from 'lucide-react';
import { PgsStatCard, type PgsStatTone } from '@/components/pgs-stat-card';
import type { GovernanceStatKey, GovernanceStats } from './types';

const governanceStatDefinitions: {
    key: GovernanceStatKey;
    label: string;
    status: string;
    detail: string;
    tone: PgsStatTone;
    icon: typeof List;
}[] = [
    {
        key: 'total',
        label: 'Total',
        status: 'All files',
        detail: 'All module files',
        tone: 'blue',
        icon: List,
    },
    {
        key: 'pdf',
        label: 'PDFs',
        status: 'PDF files',
        detail: 'Uploaded documents',
        tone: 'violet',
        icon: FileText,
    },
    {
        key: 'image',
        label: 'Images',
        status: 'Image files',
        detail: 'Uploaded images',
        tone: 'amber',
        icon: ImageIcon,
    },
    {
        key: 'approved',
        label: 'Approved',
        status: 'Approved',
        detail: 'Ready for use',
        tone: 'green',
        icon: CircleCheck,
    },
    {
        key: 'in_progress',
        label: 'In Progress',
        status: 'In progress',
        detail: 'Awaiting completion',
        tone: 'blue',
        icon: Hourglass,
    },
    {
        key: 'returned',
        label: 'Returned',
        status: 'Returned',
        detail: 'Needs attention',
        tone: 'red',
        icon: Undo2,
    },
];

interface StatsGridProps {
    stats: GovernanceStats;
}

export function StatsGrid({ stats }: StatsGridProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            {governanceStatDefinitions.map((stat) => {
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
