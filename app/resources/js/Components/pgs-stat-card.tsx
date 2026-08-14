import type { ReactNode } from 'react';

import { Card, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type PgsStatTone = 'blue' | 'green' | 'violet' | 'amber' | 'red';

interface PgsStatCardProps {
    label: string;
    value: string | number;
    icon: ReactNode;
    tone?: PgsStatTone;
    status?: string;
    detail?: string;
    className?: string;
    compact?: boolean;
}

const sparkHeights = [35, 52, 42, 68, 57, 78];

export function PgsStatCard({
    label,
    value,
    icon,
    tone = 'blue',
    status,
    detail,
    className,
    compact = false,
}: PgsStatCardProps) {
    return (
        <Card
            className={cn('pgs-stat-card', compact && 'pgs-stat-card-compact', className)}
            data-stat-tone={tone}
        >
            <div className="pgs-stat-header">
                <div className="pgs-stat-icon" aria-hidden="true">
                    {icon}
                </div>
                {status !== undefined && <span className="pgs-stat-trend">{status}</span>}
            </div>

            <div className="pgs-stat-value">
                <CardTitle>{label}</CardTitle>
                <strong>{value}</strong>
            </div>

            <div className="pgs-stat-footer">
                <span>{detail ?? 'Current total'}</span>
                <span className="pgs-stat-spark" aria-hidden="true">
                    {sparkHeights.map((_, index) => (
                        <i key={index} />
                    ))}
                </span>
            </div>
        </Card>
    );
}
