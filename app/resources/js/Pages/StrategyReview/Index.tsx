import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Download, FilePenLine, FileUp, Save, Send, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

interface ReviewForm {
    id: number;
    employee_id: number;
    employee_email: string;
    data: Record<string, string>;
    status: string;
    created_at: string;
    updated_at: string;
}

interface ReviewDecisionTarget {
    form: ReviewForm;
    status: 'Approved' | 'Returned';
}
interface StrategyReviewProps extends PageProps {
    forms: ReviewForm[];
    canReview: boolean;
    userId: number;
    canEditAny: boolean;
    fields: string[];
    uploadUrl: string;
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
    uploadUrl,
}: StrategyReviewProps) {
    const form = useForm<Record<string, string>>(blankForm(fields));
    const [editingId, setEditingId] = useState<number | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [reviewTarget, setReviewTarget] = useState<ReviewDecisionTarget | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function save(status: 'Draft' | 'Submitted'): void {
        const action = status === 'Draft' ? 'draft' : 'submit';
        start(action);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setEditingId(null);
                form.reset();
                setFormOpen(false);
            },
            onFinish: () => {
                finish(action);
            },
        };
        form.setData('status', status);
        if (editingId === null) form.post('/strategy-review', options);
        else form.put(`/strategy-review/${String(editingId)}`, options);
    }

    function openForm(review: ReviewForm): void {
        setEditingId(review.id);
        form.setData({ ...blankForm(fields), ...review.data });
        setFormOpen(true);
    }

    function confirmReview(): void {
        if (reviewTarget === null) return;
        const action = `review:${String(reviewTarget.form.id)}`;
        start(action);
        router.post(
            `/strategy-review/${String(reviewTarget.form.id)}/review`,
            { status: reviewTarget.status },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                    setReviewTarget(null);
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
                        <div className="flex flex-wrap items-center gap-2">
                            <Button asChild variant="outline">
                                <a href={uploadUrl}>
                                    <FileUp className="size-4" /> Upload register
                                </a>
                            </Button>
                            <Button
                                type="button"
                                onClick={() => {
                                    setEditingId(null);
                                    form.reset();
                                    setFormOpen(true);
                                }}
                            >
                                <FilePenLine className="size-4" /> New review
                            </Button>
                        </div>
                    </CardHeader>
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
                                                className="pgs-success-action-button"
                                                onClick={() => {
                                                    setReviewTarget({
                                                        form: reviewForm,
                                                        status: 'Approved',
                                                    });
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
                                                    setReviewTarget({
                                                        form: reviewForm,
                                                        status: 'Returned',
                                                    });
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

            <Dialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);
                    if (!open) {
                        setEditingId(null);
                        form.reset();
                    }
                }}
            >
                <DialogContent className="pgs-modal-form-dialog pgs-modal-wide">
                    <DialogHeader>
                        <DialogTitle>
                            {editingId === null
                                ? 'New strategy review'
                                : `Edit review #${String(editingId)}`}
                        </DialogTitle>
                        <DialogDescription>
                            Complete the review, save it as a draft, or submit it for approval.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="pgs-modal-form pgs-modal-form-scroll">
                        <DialogBody>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Review date"
                                    value={form.data.review_date}
                                    type="date"
                                    error={form.errors.review_date}
                                    onChange={(value) => {
                                        form.setData('review_date', value);
                                    }}
                                />
                                <Field
                                    label="Objective"
                                    value={form.data.objective}
                                    area
                                    error={form.errors.objective}
                                    onChange={(value) => {
                                        form.setData('objective', value);
                                    }}
                                />
                                <Field
                                    label="Directly contributing units"
                                    value={form.data.directly_contributing_units}
                                    area
                                    error={form.errors.directly_contributing_units}
                                    onChange={(value) => {
                                        form.setData('directly_contributing_units', value);
                                    }}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                {[
                                    'measure',
                                    'target',
                                    'actual_to_date_measure',
                                    'status_measure',
                                ].map((field) => (
                                    <Field
                                        key={field}
                                        label={fieldLabels[field] ?? field}
                                        value={form.data[field]}
                                        error={form.errors[field]}
                                        onChange={(value) => {
                                            form.setData(field, value);
                                        }}
                                    />
                                ))}
                            </div>
                            <div className="grid gap-4 md:grid-cols-3">
                                {[1, 2, 3].map((number) => (
                                    <div
                                        key={number}
                                        className="pgs-nested-card rounded-[var(--kinetic-radius-control)] p-4"
                                    >
                                        <p className="mb-3 text-sm font-semibold">KRA {number}</p>
                                        <div className="space-y-3">
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
                                                        value={form.data[field]}
                                                        area={suffix === 'key_results_area'}
                                                        error={form.errors[field]}
                                                        onChange={(value) => {
                                                            form.setData(field, value);
                                                        }}
                                                    />
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div className="grid gap-4 md:grid-cols-3">
                                {['continue', 'stop', 'start'].map((field) => (
                                    <Field
                                        key={field}
                                        label={fieldLabels[field] ?? field}
                                        value={form.data[field]}
                                        area
                                        error={form.errors[field]}
                                        onChange={(value) => {
                                            form.setData(field, value);
                                        }}
                                    />
                                ))}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Prepared by"
                                    value={form.data.prepared_by}
                                    error={form.errors.prepared_by}
                                    onChange={(value) => {
                                        form.setData('prepared_by', value);
                                    }}
                                />
                                <Field
                                    label="Approved by (unit head)"
                                    value={form.data.approved_by}
                                    error={form.errors.approved_by}
                                    onChange={(value) => {
                                        form.setData('approved_by', value);
                                    }}
                                />
                            </div>
                        </DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setFormOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
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
                                type="button"
                                onClick={() => {
                                    save('Submitted');
                                }}
                                loading={isPending('submit')}
                                loadingText="Submitting"
                            >
                                <Send className="size-4" />{' '}
                                {editingId === null ? 'Submit review' : 'Update and submit'}
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            <PgsConfirmationDialog
                open={reviewTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setReviewTarget(null);
                }}
                title={
                    reviewTarget?.status === 'Approved'
                        ? 'Approve strategy review'
                        : 'Return strategy review'
                }
                description={
                    reviewTarget?.status === 'Approved'
                        ? 'This will approve the submitted strategy review.'
                        : 'This will return the submitted strategy review for further work.'
                }
                confirmationTitle={
                    reviewTarget?.status === 'Approved' ? 'Confirm approval' : 'Confirm return'
                }
                confirmationDescription={`${reviewTarget?.form.data.objective ?? `Strategy Review #${String(reviewTarget?.form.id ?? '')}`} will be marked ${reviewTarget?.status.toLowerCase() ?? 'updated'}.`}
                onConfirm={confirmReview}
                loading={
                    reviewTarget !== null && isPending(`review:${String(reviewTarget.form.id)}`)
                }
                loadingText={reviewTarget?.status === 'Approved' ? 'Approving' : 'Returning'}
                confirmText={reviewTarget?.status === 'Approved' ? 'Approve' : 'Return'}
                confirmVariant={reviewTarget?.status === 'Approved' ? 'default' : 'destructive'}
                kind={reviewTarget?.status === 'Approved' ? 'approve' : 'reject'}
            />
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    value,
    onChange,
    area = false,
    type = 'text',
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    area?: boolean;
    type?: string;
    error?: string;
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
            {error && <p className="text-destructive text-sm">{error}</p>}
        </div>
    );
}
