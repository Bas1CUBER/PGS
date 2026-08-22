import { BarChart3 } from 'lucide-react';
import { PgsStatCard } from '@/components/pgs-stat-card';
import { contentText, contentTone } from './lib';
import type { RoadmapBlock } from './types';

interface RoadmapStatPreviewProps {
    block: RoadmapBlock;
}

export function RoadmapStatPreview({ block }: RoadmapStatPreviewProps) {
    if (block.block_type !== 'dashboard_stat') return null;

    return (
        <PgsStatCard
            compact
            label={contentText(block.content, 'label', 'Untitled stat')}
            value={contentText(block.content, 'value', '0')}
            icon={<BarChart3 className="size-5" />}
            status="Configured"
            detail="Roadmap page builder"
            tone={contentTone(block.content)}
        />
    );
}
