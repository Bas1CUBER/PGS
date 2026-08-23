import { Download, ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ReviewDecisionTarget, ReviewForm } from './types';

interface ReviewRegisterProps {
    forms: ReviewForm[];
    canReview: boolean;
    userId: number;
    canEditAny: boolean;
    isPending: (action: string) => boolean;
    onEdit: (reviewForm: ReviewForm) => void;
    onSelectReviewTarget: (target: ReviewDecisionTarget) => void;
}

export function ReviewRegister({
    forms,
    canReview,
    userId,
    canEditAny,
    isPending,
    onEdit,
    onSelectReviewTarget,
}: ReviewRegisterProps) {
    return (
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
                                    onEdit(reviewForm);
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
                                            onSelectReviewTarget({
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
                                            onSelectReviewTarget({
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
    );
}
