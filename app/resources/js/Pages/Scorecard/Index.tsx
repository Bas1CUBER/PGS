import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarPlus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePendingAction } from '@/hooks/use-pending-action';
import { useToast } from '@/components/pgs-toast';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import { urls } from '@/lib/urls';
import { AddYearDialog } from './components/add-year-dialog';
import { CreateMeasureDialog } from './components/create-measure-dialog';
import { EditMeasureDialog } from './components/edit-measure-dialog';
import { valueKey } from './components/lib';
import { ScorecardTable } from './components/scorecard-table';
import type { Measure, ScorecardPageProps, Year } from './components/types';

export default function ScorecardIndex({ measures, years, values }: ScorecardPageProps) {
    const { auth, errors } = usePage().props;
    const user = auth.user;
    const canManage = user !== null && (user.role === 'admin' || user.role === 'focal');
    const { showToast } = useToast();

    const [impact, setImpact] = useState('');
    const [measure, setMeasure] = useState('');
    const [bl, setBl] = useState('');
    const [newYear, setNewYear] = useState('');
    const [measureDialogOpen, setMeasureDialogOpen] = useState(false);
    const [yearDialogOpen, setYearDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Measure | null>(null);
    const [drafts, setDrafts] = useState<Partial<Record<string, string>>>({});
    const [deleteTarget, setDeleteTarget] = useState<Year | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function createMeasure(e: { preventDefault(): void }): void {
        e.preventDefault();
        start('create-measure');
        router.post(
            urls.scorecard.measures,
            { impact, measure, bl },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setImpact('');
                    setMeasure('');
                    setBl('');
                    setMeasureDialogOpen(false);
                },
                onFinish: () => {
                    finish('create-measure');
                },
            },
        );
    }

    function addYear(e: { preventDefault(): void }): void {
        e.preventDefault();
        start('add-year');
        router.post(
            urls.scorecard.years,
            { year: newYear },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewYear('');
                    setYearDialogOpen(false);
                },
                onFinish: () => {
                    finish('add-year');
                },
            },
        );
    }

    function saveEdit(): void {
        if (editing === null) return;
        start('save-measure');
        router.put(
            urls.scorecard.measure(String(editing.id)),
            { impact, measure, bl },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditing(null);
                },
                onFinish: () => {
                    finish('save-measure');
                },
            },
        );
    }

    function commitValue(measureId: number, yearId: number): void {
        const key = valueKey(measureId, yearId);
        const draft = drafts[key];

        if (draft === undefined) return;

        const action = `value:${key}`;
        start(action);
        router.put(
            urls.scorecard.value(String(measureId), String(yearId)),
            { value: draft },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDrafts((prev) => {
                        const next: Partial<Record<string, string>> = {};
                        Object.entries(prev).forEach(([draftKey, value]) => {
                            if (draftKey !== key) next[draftKey] = value;
                        });
                        return next;
                    });
                },
                onError: () => {
                    showToast('error', 'Could not save the value. Please review it and try again.');
                },
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    function confirmDelete(): void {
        if (deleteTarget === null) return;
        start('delete-year');
        router.delete(urls.scorecard.year(String(deleteTarget.id)), {
            onFinish: () => {
                finish('delete-year');
                setDeleteTarget(null);
            },
        });
    }

    function openEdit(target: Measure): void {
        setEditing(target);
        setImpact(target.impact);
        setMeasure(target.measure);
        setBl(target.bl ?? '');
    }

    function changeDraft(key: string, value: string): void {
        setDrafts((prev) => ({
            ...prev,
            [key]: value,
        }));
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Impact Scorecard</h2>}
        >
            <Head title="Impact Scorecard" />

            <div className="space-y-6">
                {canManage && (
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            onClick={() => {
                                setMeasureDialogOpen(true);
                            }}
                        >
                            <Plus className="size-4" /> Add measure
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setYearDialogOpen(true);
                            }}
                        >
                            <CalendarPlus className="size-4" /> Add year
                        </Button>
                    </div>
                )}

                <ScorecardTable
                    measures={measures}
                    years={years}
                    values={values}
                    drafts={drafts}
                    canManage={canManage}
                    isPending={isPending}
                    onDraftChange={changeDraft}
                    onCommitValue={commitValue}
                    onEditMeasure={openEdit}
                    onDeleteYear={setDeleteTarget}
                />
            </div>

            <CreateMeasureDialog
                open={measureDialogOpen}
                impact={impact}
                measure={measure}
                bl={bl}
                errors={errors}
                onImpactChange={setImpact}
                onMeasureChange={setMeasure}
                onBaselineChange={setBl}
                onOpenChange={(open) => {
                    setMeasureDialogOpen(open);
                    if (!open) {
                        setImpact('');
                        setMeasure('');
                        setBl('');
                    }
                }}
                onCancel={() => {
                    setMeasureDialogOpen(false);
                }}
                onSubmit={createMeasure}
                isPending={isPending}
            />

            <AddYearDialog
                open={yearDialogOpen}
                year={newYear}
                errors={errors}
                onYearChange={setNewYear}
                onOpenChange={(open) => {
                    setYearDialogOpen(open);
                    if (!open) setNewYear('');
                }}
                onCancel={() => {
                    setYearDialogOpen(false);
                }}
                onSubmit={addYear}
                isPending={isPending}
            />

            <EditMeasureDialog
                open={editing !== null}
                impact={impact}
                measure={measure}
                bl={bl}
                errors={errors}
                onImpactChange={setImpact}
                onMeasureChange={setMeasure}
                onBaselineChange={setBl}
                onOpenChange={(open) => {
                    if (!open) setEditing(null);
                }}
                onCancel={() => {
                    setEditing(null);
                }}
                onSave={saveEdit}
                isPending={isPending}
            />

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete scorecard year"
                description="This action permanently removes the scorecard year and its values."
                confirmationTitle="Confirm year deletion"
                confirmationDescription={`Year ${String(deleteTarget?.year ?? '')} and its values will be removed.`}
                onConfirm={confirmDelete}
                loading={isPending('delete-year')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
