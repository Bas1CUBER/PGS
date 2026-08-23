import type { PageProps } from '@/types';

export interface Measure {
    id: number;
    impact: string;
    measure: string;
    bl: string | null;
}

export interface Year {
    id: number;
    year: number;
}

export interface ScorecardPageProps extends PageProps {
    measures: Measure[];
    years: Year[];
    values: Partial<Record<string, { value: string | null }>>;
}
