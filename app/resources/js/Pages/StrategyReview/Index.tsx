import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FilePenLine, FileUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import { urls } from '@/lib/urls';
import { blankForm } from './components/lib';
import { ReviewFormDialog } from './components/review-form-dialog';
import { ReviewRegister } from './components/review-register';
import type { ReviewDecisionTarget, ReviewForm, StrategyReviewProps } from './components/types';

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
        if (editingId === null) form.post(urls.strategyReview.index, options);
        else form.put(urls.strategyReview.update(String(editingId)), options);
    }

    function openForm(reviewForm: ReviewForm): void {
        setEditingId(reviewForm.id);
        form.setData({ ...blankForm(fields), ...reviewForm.data });
        setFormOpen(true);
    }

    function confirmReview(): void {
        if (reviewTarget === null) return;
        const action = `review:${String(reviewTarget.form.id)}`;
        start(action);
        router.post(
            urls.strategyReview.review(String(reviewTarget.form.id)),
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

                <ReviewRegister
                    forms={forms}
                    canReview={canReview}
                    userId={userId}
                    canEditAny={canEditAny}
                    isPending={isPending}
                    onEdit={openForm}
                    onSelectReviewTarget={setReviewTarget}
                />
            </div>

            <ReviewFormDialog
                open={formOpen}
                form={form}
                editingId={editingId}
                isPending={isPending}
                onOpenChange={(open) => {
                    setFormOpen(open);
                    if (!open) {
                        setEditingId(null);
                        form.reset();
                    }
                }}
                onClose={() => {
                    setFormOpen(false);
                }}
                onSave={save}
            />

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
