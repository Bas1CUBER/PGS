import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardCheck, Download, FileUp, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import { useState, type ReactElement } from 'react';
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
import { usePendingAction } from '@/hooks/use-pending-action';
import type { PageProps } from '@/types';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

interface Review {
    id: number;
    employee_id: number;
    employee_email: string;
    data: Record<string, string>;
    pdf_file: string | null;
    created_at: string;
}
interface OperationsReviewProps extends PageProps {
    reviews: Review[];
    fields: string[];
    uploadUrl: string;
    userId: number;
    canEditAny: boolean;
}
type FormData = Record<string, string>;

const labels: Record<string, string> = {
    department: 'Department / division',
    head_deputy: 'Head / deputy',
    documenter: 'Documenter',
    strategic_contributions: 'Strategic contributions',
    deliverable: 'Deliverable',
    deadline: 'Deadline',
    status: 'Deliverable status',
    meeting_venue_schedule: 'Meeting venue and schedule',
    scoreboard_location_oic: 'Scoreboard location and OIC',
    action_point: 'Action point',
    responsible_person: 'Responsible person',
    target_date: 'Target date',
    action_status: 'Action status',
    wins_celebrated: 'How wins are celebrated',
    best_performers_recognized: 'How best performers are recognized',
    frequency: 'Recognition frequency',
    prepared_by: 'Prepared by',
    approved_by: 'Approved by',
};

function blankForm(fields: string[]): FormData {
    return Object.fromEntries(fields.map((field) => [field, '']));
}

export default function OperationsReviewIndex({
    reviews,
    fields,
    uploadUrl,
    userId,
    canEditAny,
}: OperationsReviewProps) {
    const form = useForm(blankForm(fields));
    const [editingId, setEditingId] = useState<number | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Review | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function save(): void {
        start('save');
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setEditingId(null);
                setFormOpen(false);
            },
            onFinish: () => {
                finish('save');
            },
        };

        if (editingId === null) {
            form.post('/operations-review', options);
        } else {
            form.put(`/operations-review/${String(editingId)}`, options);
        }
    }

    function edit(review: Review): void {
        setEditingId(review.id);
        form.setData({ ...blankForm(fields), ...review.data });
        setFormOpen(true);
    }

    function confirmDelete(): void {
        if (deleteTarget === null) return;
        start('delete');
        router.delete(`/operations-review/${String(deleteTarget.id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish('delete');
                setDeleteTarget(null);
            },
        });
    }

    function field(name: string, area = false): ReactElement {
        const value = form.data[name] ?? '';
        return (
            <div className={area ? 'space-y-2 md:col-span-2' : 'space-y-2'}>
                <label className="text-xs font-semibold" htmlFor={`operations-${name}`}>
                    {labels[name] ?? name.replace(/_/g, ' ')}
                </label>
                {area ? (
                    <textarea
                        id={`operations-${name}`}
                        value={value}
                        onChange={(event) => {
                            form.setData(name, event.target.value);
                        }}
                        rows={3}
                        className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                    />
                ) : (
                    <Input
                        id={`operations-${name}`}
                        value={value}
                        onChange={(event) => {
                            form.setData(name, event.target.value);
                        }}
                        required={['department', 'head_deputy', 'documenter'].includes(name)}
                    />
                )}
                {form.errors[name] && (
                    <p className="text-destructive text-sm">{form.errors[name]}</p>
                )}
            </div>
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Operations Review</h2>}
        >
            <Head title="Operations Review" />
            <div className="mx-auto max-w-6xl space-y-6">
                <Card className="pgs-template-card">
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <p className="pgs-section-kicker">Performance assessment</p>
                            <CardTitle className="mt-1 flex items-center gap-2">
                                <ClipboardCheck className="size-5" /> Operations Review form
                            </CardTitle>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Capture the review meeting, strategic contributions, action points,
                                and recognition notes.
                            </p>
                        </div>
                        <div className="flex shrink-0 flex-wrap items-center gap-2">
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
                                <Plus className="size-4" /> New review
                            </Button>
                        </div>
                    </CardHeader>
                </Card>
                <section className="space-y-3">
                    <div>
                        <p className="pgs-section-kicker">Review register</p>
                        <h3 className="text-lg font-semibold">Saved Operations Reviews</h3>
                    </div>
                    {reviews.length === 0 && (
                        <Card>
                            <CardContent className="pgs-empty-state text-muted-foreground py-10 text-center">
                                No structured Operations Reviews yet.
                            </CardContent>
                        </Card>
                    )}
                    {reviews.map((review) => (
                        <Card key={review.id}>
                            <CardHeader className="flex flex-row items-start justify-between gap-3">
                                <div>
                                    <CardTitle>
                                        {review.data.department ||
                                            `Operations Review #${String(review.id)}`}
                                    </CardTitle>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {review.employee_email} ·{' '}
                                        {new Date(review.created_at).toLocaleString()}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button asChild variant="outline" size="sm">
                                        <a href={`/operations-review/${String(review.id)}/pdf`}>
                                            <Download className="size-4" /> PDF
                                        </a>
                                    </Button>
                                    {(canEditAny || review.employee_id === userId) && (
                                        <>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    edit(review);
                                                }}
                                            >
                                                <Pencil className="size-4" /> Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="Delete review"
                                                className="text-destructive"
                                                loading={isPending('delete')}
                                                onClick={() => {
                                                    setDeleteTarget(review);
                                                }}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm md:grid-cols-2">
                                <Detail label="Head / deputy" value={review.data.head_deputy} />
                                <Detail label="Documenter" value={review.data.documenter} />
                                <Detail
                                    label="Strategic contributions"
                                    value={review.data.strategic_contributions}
                                />
                                <Detail label="Action point" value={review.data.action_point} />
                                <Detail label="Action status" value={review.data.action_status} />
                                <Detail label="Approved by" value={review.data.approved_by} />
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
                                ? 'New review'
                                : `Edit review #${String(editingId)}`}
                        </DialogTitle>
                        <DialogDescription>
                            Capture the review meeting, action points, and recognition notes.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            save();
                        }}
                        className="pgs-modal-form pgs-modal-form-scroll"
                    >
                        <DialogBody>
                            <div className="grid gap-4 sm:grid-cols-3">
                                {field('department')}
                                {field('head_deputy')}
                                {field('documenter')}
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                {field('strategic_contributions', true)}
                                {field('deliverable')}
                                {field('deadline')}
                                {field('status')}
                                {field('meeting_venue_schedule', true)}
                                {field('scoreboard_location_oic', true)}
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                {field('action_point')}
                                {field('responsible_person')}
                                {field('target_date')}
                                {field('action_status')}
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                {field('wins_celebrated', true)}
                                {field('best_performers_recognized', true)}
                                {field('frequency')}
                                {field('prepared_by')}
                                {field('approved_by')}
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
                            <Button type="submit" loading={form.processing} loadingText="Saving">
                                <Save className="size-4" /> Save review
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete review"
                description="This action permanently removes the Operations Review."
                confirmationTitle="Confirm review deletion"
                confirmationDescription={`${deleteTarget?.data.department ?? `Operations Review #${String(deleteTarget?.id ?? '')}`} will be removed.`}
                onConfirm={confirmDelete}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}

function Detail({ label, value }: { label: string; value?: string }): ReactElement {
    return (
        <div>
            <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                {label}
            </p>
            <p className="mt-1 whitespace-pre-wrap">{value ?? '—'}</p>
        </div>
    );
}
