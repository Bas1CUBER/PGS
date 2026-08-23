import type { PageProps } from '@/types';

export interface SectorDetailPageProps extends PageProps {
    module: {
        slug: string;
        label: string;
        pillar: string;
        pillar_label: string;
        logo: string;
        table: string;
        columns: string[];
        year_columns: string[];
        editable: string[];
    };
    columns: string[];
    rows: {
        data: SectorDetailRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    stats: {
        ongoing: number;
        completed: number;
        presented: number;
        published: number;
    } | null;
    lockColumn: string | null;
    canManage: boolean;
}

export type SectorDetailRow = Record<string, string | null>;

export type ResearchStatKey = keyof NonNullable<SectorDetailPageProps['stats']>;
