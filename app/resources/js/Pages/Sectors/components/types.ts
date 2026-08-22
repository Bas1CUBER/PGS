import type { PageProps } from '@/types';

export interface SectorRow {
    id: number;
    category: string;
    year: number;
    description: string;
}

export interface SectorProgress {
    id: number;
    category: string;
    year: number;
    month: string;
    status: string;
    remarks: string | null;
    description: string | null;
}

export interface PendingDecisionTarget {
    id: number;
    decision: 'Approved' | 'Rejected';
    category: string;
    year: number;
    description: string | null;
}

export interface SectorDetailLink {
    slug: string;
    label: string;
}

export interface SectorShowPageProps extends PageProps {
    module: {
        slug: string;
        label: string;
        logo: string;
        table: string;
        progress_table: string;
        schedule_table: string | null;
    };
    rows: {
        data: SectorRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    progress: SectorProgress[];
    schedule:
        | {
              id: number;
              category: string;
              year: number;
              month: string;
              description: string;
          }[]
        | null;
    details: SectorDetailLink[];
    progressSummary: Record<string, number | undefined>;
    pendingChanges: {
        id: number;
        change_type: string;
        category: string;
        year: number;
        month: number | null;
        status: string | null;
        description: string | null;
        submitted_at: string;
    }[];
    canManage: boolean;
}

export type ScheduleEntry = NonNullable<SectorShowPageProps['schedule']>[number];
