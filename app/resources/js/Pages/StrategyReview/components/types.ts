import type { PageProps } from '@/types';

export interface ReviewForm {
    id: number;
    employee_id: number;
    employee_email: string;
    data: Record<string, string>;
    status: string;
    created_at: string;
    updated_at: string;
}

export interface ReviewDecisionTarget {
    form: ReviewForm;
    status: 'Approved' | 'Returned';
}

export interface StrategyReviewProps extends PageProps {
    forms: ReviewForm[];
    canReview: boolean;
    userId: number;
    canEditAny: boolean;
    fields: string[];
    uploadUrl: string;
}
