import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Download, FilePenLine, Save, Send, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';

interface ReviewForm {
    id: number;
    employee_id: number;
    employee_email: string;
    data: Record<string, string>;
    status: string;
    created_at: string;
    updated_at: string;
}
interface StrategyReviewProps extends PageProps {
    forms: ReviewForm[];
    canReview: boolean;
    userId: number;
    canEditAny: boolean;
    fields: string[];
}

const fieldLabels: Record<string, string> = {
    review_date: 'Review date',
    objective: 'Objective',
    directly_contributing_units: 'Directly contributing units',
    measure: 'Measure',
    target: 'Target',
    actual_to_date_measure: 'Actual to date measure',
    status_measure: 'Measure status',
    kra1_key_results_area: 'KRA 1 key results area',
    kra1_deliverable: 'KRA 1 deliverable',
    kra1_actual_to_date: 'KRA 1 actual to date',
    kra1_status: 'KRA 1 status',
    kra2_key_results_area: 'KRA 2 key results area',
    kra2_deliverable: 'KRA 2 deliverable',
    kra2_actual_to_date: 'KRA 2 actual to date',
    kra2_status: 'KRA 2 status',
    kra3_key_results_area: 'KRA 3 key results area',
    kra3_deliverable: 'KRA 3 deliverable',
    kra3_actual_to_date: 'KRA 3 actual to date',
    kra3_status: 'KRA 3 status',
    continue: 'Continue',
    stop: 'Stop',
    start: 'Start',
    prepared_by: 'Prepared by',
    approved_by: 'Approved by',
};

function blankForm(fields: string[]): Record<string, string> {
    return Object.fromEntries(fields.map((field) => [field, '']));
}

export default function StrategyReviewIndex({
    forms,
    canReview,
    userId,
    canEditAny,
    fields,
}: StrategyReviewProps) {
    const [data, setData] = useState<Record<string, string | undefined>>(() => blankForm(fields));
    const [editingId, setEditingId] = useState<number | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function save(status: 'Draft' | 'Submitted'): void {
        const action = status === 'Draft' ? 'draft' : 'submit';
        start(action);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (status === 'Submitted') {
                    setEditingId(null);
                    setData(blankForm(fields));
                }
            },
            onFinish: () => {
                finish(action);
            },
        };
        if (editingId === null) router.post('/strategy-review', { ...data, status }, options);
        else router.put(`/strategy-review/${String(editingId)}`, { ...data, status }, options);
    }

    function openForm(review: ReviewForm): void {
        setEditingId(review.id);
        setData({ ...blankForm(fields), ...review.data });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function review(id: number, status: 'Approved' | 'Returned'): void {
        const action = `review:${String(id)}`;
        start(action);
        router.post(
            `/strategy-review/${String(id)}/review`,
            { status },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Strategy Review</h2>}
        >
            <Head title="Strategy Review" />
            <div className="mx-auto max-w-6xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FilePenLine className="size-4" /> Strategy Review form
                        </CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Complete the review, save it as a draft, or submit it for approval.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Review date"
                                value={data.review_date ?? ''}
                                type="date"
                                onChange={(value) => {
                                    setData({ ...data, review_date: value });
                                }}
                            />
                            <Field
                                label="Objective"
                                value={data.objective ?? ''}
                                area
                                onChange={(value) => {
                                    setData({ ...data, objective: value });
                                }}
                            />
                            <Field
                                label="Directly contributing units"
                                value={data.directly_contributing_units ?? ''}
                                area
                                onChange={(value) => {
                                    setData({ ...data, directly_contributing_units: value });
                                }}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {['measure', 'target', 'actual_to_date_measure', 'status_measure'].map(
                                (field) => (
                                    <Field
                                        key={field}
                                        label={fieldLabels[field] ?? field}
                                        value={data[field] ?? ''}
                                        onChange={(value) => {
                                            setData({ ...data, [field]: value });
                                        }}
                                    />
                                ),
                            )}
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            {[1, 2, 3].map((number) => (
                                <Card key={number} className="bg-transparent shadow-none">
                                    <CardHeader className="px-4 pb-2">
                                        <CardTitle className="text-sm">KRA {number}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3 px-4">
                                        {[
                                            'key_results_area',
                                            'deliverable',
                                            'actual_to_date',
                                            'status',
                                        ].map((suffix) => {
                                            const field = `kra${String(number)}_${suffix}`;
                                            return (
                                                <Field
                                                    key={field}
                                                    label={fieldLabels[field] ?? field}
                                                    value={data[field] ?? ''}
                                                    area={suffix === 'key_results_area'}
                                                    onChange={(value) => {
                                                        setData({ ...data, [field]: value });
                                                    }}
                                                />
                                            );
                                        })}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            {['continue', 'stop', 'start'].map((field) => (
                                <Field
                                    key={field}
                                    label={fieldLabels[field] ?? field}
                                    value={data[field] ?? ''}
                                    area
                                    onChange={(value) => {
                                        setData({ ...data, [field]: value });
                                    }}
                                />
                            ))}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Prepared by"
                                value={data.prepared_by ?? ''}
                                onChange={(value) => {
                                    setData({ ...data, prepared_by: value });
                                }}
                            />
                            <Field
                                label="Approved by (unit head)"
                                value={data.approved_by ?? ''}
                                onChange={(value) => {
                                    setData({ ...data, approved_by: value });
                                }}
                            />
                        </div>
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    save('Draft');
                                }}
                                loading={isPending('draft')}
                                loadingText="Saving"
                            >
                                <Save className="size-4" /> Save draft
                            </Button>
                            <Button
                                onClick={() => {
                                    save('Submitted');
                                }}
                                loading={isPending('submit')}
                                loadingText="Submitting"
                            >
                                <Send className="size-4" />{' '}
                                {editingId === null ? 'Submit review' : 'Update and submit'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <section className="space-y-3">
                    <div>
                        <p className="pgs-section-kicker">Review register</p>
                        <h3 className="text-lg font-semibold">Saved submissions</h3>
                    </div>
                    {forms.length === 0 && (
                        <Card>
                            <CardContent className="text-muted-foreground py-10 text-center">
                                No Strategy Review forms yet.
                            </CardContent>
                        </Card>
                    )}
                    {forms.map((reviewForm) => (
                        <Card key={reviewForm.id}>
                            <CardHeader className="flex flex-row items-start justify-between gap-3">
                                <div>
                                    <CardTitle>
                                        {reviewForm.data.objective ||
                                            `Strategy Review #${String(reviewForm.id)}`}
                                    </CardTitle>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {reviewForm.employee_email} · updated{' '}
                                        {new Date(reviewForm.updated_at).toLocaleString()}
                                    </p>
                                </div>
                                <Badge variant="outline">{reviewForm.status}</Badge>
                            </CardHeader>
                            <CardContent className="flex flex-wrap justify-end gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <a href={`/strategy-review/${String(reviewForm.id)}/pdf`}>
                                        <Download className="size-4" /> PDF
                                    </a>
                                </Button>
                                {(canEditAny || reviewForm.employee_id === userId) && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            openForm(reviewForm);
                                        }}
                                    >
                                        Continue editing
                                    </Button>
                                )}
                                {canReview &&
                                    reviewForm.employee_id !== userId &&
                                    reviewForm.status === 'Submitted' && (
                                        <>
                                            <Button
                                                onClick={() => {
                                                    review(reviewForm.id, 'Approved');
                                                }}
                                                loading={isPending(
                                                    `review:${String(reviewForm.id)}`,
                                                )}
                                            >
                                                <ShieldCheck className="size-4" /> Approve
                                            </Button>
                                            <Button
                                                variant="outline"
                                                onClick={() => {
                                                    review(reviewForm.id, 'Returned');
                                                }}
                                                loading={isPending(
                                                    `review:${String(reviewForm.id)}`,
                                                )}
                                            >
                                                Return
                                            </Button>
                                        </>
                                    )}
                            </CardContent>
                        </Card>
                    ))}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    value,
    onChange,
    area = false,
    type = 'text',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    area?: boolean;
    type?: string;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {area ? (
                <textarea
                    value={value}
                    onChange={(e) => {
                        onChange(e.target.value);
                    }}
                    rows={3}
                    className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                />
            ) : (
                <Input
                    type={type}
                    value={value}
                    onChange={(e) => {
                        onChange(e.target.value);
                    }}
                />
            )}
        </div>
    );
}
