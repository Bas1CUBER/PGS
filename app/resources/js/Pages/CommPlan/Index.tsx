import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FileUp, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { statusClass } from '@/lib/status';
import { urls } from '@/lib/urls';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

interface CommPlanRow {
    id: number;
    objective: string;
    target_audience: string | null;
    message: string | null;
    channel: string | null;
    timeframe: string | null;
    requirements: string | null;
    responsible_person: string | null;
    status: string;
    created_by?: number | null;
}

interface CommPlanPageProps extends PageProps {
    rows: CommPlanRow[];
    userId?: number;
    canManage: boolean;
    uploadUrl: string;
}

const statusStyles: Record<string, string> = {
    Completed: statusClass('Completed'),
    Ongoing: statusClass('Ongoing'),
    'Not Accomplished/Started': statusClass('Not Accomplished/Started'),
};

export default function CommPlanIndex({ rows, userId, canManage, uploadUrl }: CommPlanPageProps) {
    const form = useForm({
        objective: '',
        channel: '',
        timeframe: '',
        responsible_person: '',
        message: '',
        target_audience: '',
        requirements: '',
    });

    const [editing, setEditing] = useState<CommPlanRow | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<CommPlanRow | null>(null);
    const { isPending, start, finish } = usePendingAction();
    const canEdit = (row: CommPlanRow): boolean => canManage || row.created_by === userId;

    function create(e: { preventDefault(): void }): void {
        e.preventDefault();
        form.post(urls.communicationPlan.index, {
            preserveScroll: true,
            onSuccess: () => {
                setFormOpen(false);
                form.reset();
            },
        });
    }

    function openEdit(row: CommPlanRow): void {
        setEditing(row);
        setFormOpen(true);
        form.setData({
            objective: row.objective,
            channel: row.channel ?? '',
            timeframe: row.timeframe ?? '',
            responsible_person: row.responsible_person ?? '',
            message: row.message ?? '',
            target_audience: row.target_audience ?? '',
            requirements: row.requirements ?? '',
        });
    }

    function saveEdit(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (editing === null) return;
        form.put(urls.communicationPlan.update(String(editing.id)), {
            preserveScroll: true,
            onSuccess: () => {
                setEditing(null);
                setFormOpen(false);
            },
        });
    }

    function setStatus(id: number, status: string): void {
        const row = rows.find((r) => r.id === id);
        if (row === undefined) return;
        const action = `status:${String(id)}`;
        start(action);
        router.put(
            urls.communicationPlan.update(String(id)),
            {
                objective: row.objective,
                target_audience: row.target_audience,
                message: row.message,
                channel: row.channel,
                timeframe: row.timeframe,
                requirements: row.requirements,
                responsible_person: row.responsible_person,
                status,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    function confirmDelete(): void {
        if (deleteTarget === null) return;
        start('delete');
        router.delete(urls.communicationPlan.destroy(String(deleteTarget.id)), {
            preserveScroll: true,
            onFinish: () => {
                finish('delete');
                setDeleteTarget(null);
            },
        });
    }

    const formFields = (
        <>
            <div className="space-y-2">
                <Label htmlFor="objective">Objective</Label>
                <textarea
                    id="objective"
                    value={form.data.objective}
                    onChange={(e) => {
                        form.setData('objective', e.target.value);
                    }}
                    rows={2}
                    className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                    required
                />
                {form.errors.objective && (
                    <p className="text-destructive text-sm">{form.errors.objective}</p>
                )}
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-2">
                    <Label htmlFor="channel">Channel</Label>
                    <Input
                        id="channel"
                        value={form.data.channel}
                        onChange={(e) => {
                            form.setData('channel', e.target.value);
                        }}
                    />
                    {form.errors.channel && (
                        <p className="text-destructive text-sm">{form.errors.channel}</p>
                    )}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="timeframe">Timeframe</Label>
                    <Input
                        id="timeframe"
                        value={form.data.timeframe}
                        onChange={(e) => {
                            form.setData('timeframe', e.target.value);
                        }}
                    />
                    {form.errors.timeframe && (
                        <p className="text-destructive text-sm">{form.errors.timeframe}</p>
                    )}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="responsible">Responsible person</Label>
                    <Input
                        id="responsible"
                        value={form.data.responsible_person}
                        onChange={(e) => {
                            form.setData('responsible_person', e.target.value);
                        }}
                    />
                    {form.errors.responsible_person && (
                        <p className="text-destructive text-sm">{form.errors.responsible_person}</p>
                    )}
                </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="audience">Target audience</Label>
                    <Input
                        id="audience"
                        value={form.data.target_audience}
                        onChange={(e) => {
                            form.setData('target_audience', e.target.value);
                        }}
                    />
                    {form.errors.target_audience && (
                        <p className="text-destructive text-sm">{form.errors.target_audience}</p>
                    )}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="requirements">Requirements</Label>
                    <Input
                        id="requirements"
                        value={form.data.requirements}
                        onChange={(e) => {
                            form.setData('requirements', e.target.value);
                        }}
                    />
                    {form.errors.requirements && (
                        <p className="text-destructive text-sm">{form.errors.requirements}</p>
                    )}
                </div>
            </div>
            <div className="space-y-2">
                <Label htmlFor="message">Message</Label>
                <textarea
                    id="message"
                    value={form.data.message}
                    onChange={(e) => {
                        form.setData('message', e.target.value);
                    }}
                    rows={2}
                    className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                />
                {form.errors.message && (
                    <p className="text-destructive text-sm">{form.errors.message}</p>
                )}
            </div>
        </>
    );

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Communication Plan</h2>}
        >
            <Head title="Communication Plan" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex flex-wrap justify-end gap-2">
                    <Button asChild variant="outline">
                        <a href={uploadUrl}>
                            <FileUp className="size-4" /> Upload register
                        </a>
                    </Button>
                    <Button
                        type="button"
                        onClick={() => {
                            setEditing(null);
                            form.reset();
                            setFormOpen(true);
                        }}
                    >
                        <Plus className="size-4" /> Add row
                    </Button>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <ul className="divide-y">
                            {rows.length === 0 && (
                                <li className="text-muted-foreground py-10 text-center">
                                    No communication plan rows yet.
                                </li>
                            )}
                            {rows.map((row) => (
                                <li key={row.id} className="px-6 py-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">{row.objective}</p>
                                            {row.target_audience !== null && (
                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    Audience: {row.target_audience}
                                                </p>
                                            )}
                                            {row.message !== null && (
                                                <p className="text-muted-foreground mt-0.5 text-sm">
                                                    {row.message}
                                                </p>
                                            )}
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {[
                                                    row.channel,
                                                    row.timeframe,
                                                    row.responsible_person,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <select
                                                value={row.status}
                                                disabled={
                                                    !canEdit(row) ||
                                                    isPending(`status:${String(row.id)}`)
                                                }
                                                onChange={(e) => {
                                                    setStatus(row.id, e.target.value);
                                                }}
                                                className="border-input bg-background h-9 rounded-md border px-2 text-xs"
                                                aria-label="Row status"
                                            >
                                                <option>Not Accomplished/Started</option>
                                                <option>Ongoing</option>
                                                <option>Completed</option>
                                            </select>
                                            <Badge
                                                variant="outline"
                                                className={statusStyles[row.status] ?? ''}
                                            >
                                                {row.status}
                                            </Badge>
                                            {canEdit(row) && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        openEdit(row);
                                                    }}
                                                >
                                                    Edit
                                                </Button>
                                            )}
                                            {canEdit(row) && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    loading={isPending('delete')}
                                                    loadingText=""
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        setDeleteTarget(row);
                                                    }}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);
                    if (!open) setEditing(null);
                }}
            >
                <DialogContent className="pgs-modal-form-dialog pgs-modal-wide">
                    <DialogHeader>
                        <DialogTitle>{editing === null ? 'Add a row' : 'Edit row'}</DialogTitle>
                        <DialogDescription>
                            {editing === null
                                ? 'Define communication objectives, channels and responsibilities.'
                                : `Editing row #${String(editing.id)}.`}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={editing === null ? create : saveEdit}
                        className="pgs-modal-form pgs-modal-form-scroll"
                    >
                        <DialogBody>{formFields}</DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setFormOpen(false);
                                    setEditing(null);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                loading={form.processing}
                                loadingText={editing === null ? 'Adding' : 'Saving'}
                            >
                                <Plus className="size-4" />
                                {editing === null ? 'Add row' : 'Save changes'}
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
                title="Delete communication plan"
                description="This action permanently removes the communication plan entry."
                confirmationTitle="Confirm communication plan deletion"
                confirmationDescription={`"${deleteTarget?.objective ?? 'This entry'}" will be removed from the communication plan.`}
                onConfirm={confirmDelete}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
