import type { PageProps } from '@/types';

export interface RoadmapBlock {
    id: number;
    block_type: string;
    content: Record<string, unknown>;
}

export interface RoadmapItem {
    id: number;
    sub_label: string;
    sub_letter: string;
    page_slug: string;
    sort_order: number;
    blocks?: RoadmapBlock[];
}

export interface RoadmapTitleRow {
    id: number;
    title: string;
    sort_order: number;
    items: RoadmapItem[];
}

export interface RoadmapsPageProps extends PageProps {
    titles: RoadmapTitleRow[];
}
