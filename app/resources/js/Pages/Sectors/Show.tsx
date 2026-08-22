import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import { AddIndicatorDialog } from './components/add-indicator-dialog';
import { DetailRoadmapsCard } from './components/detail-roadmaps-card';
import { EditIndicatorDialog } from './components/edit-indicator-dialog';
import { IndicatorsTableCard } from './components/indicators-table-card';
import { PaginationLinks } from './components/pagination-links';
import { PendingChangesCard } from './components/pending-changes-card';
import { ProgressCard } from './components/progress-card';
import { ProgressSummaryCards } from './components/progress-summary-cards';
import { ScheduleCard } from './components/schedule-card';
import { SectorBanner } from './components/sector-banner';
import type {
    PendingDecisionTarget,
    SectorRow,
    SectorShowPageProps,
} from './components/types';

export default function SectorShow({
    module,
    rows,
    progress,
    schedule,
    details: detailModules,
    progressSummary,
    pendingChanges,
    canManage,
}: SectorShowPageProps) {
    const [editTarget, setEditTarget] = useState<SectorRow | null>(null);
    const [indicatorFilter, setIndicatorFilter] = useState('');
    const [addDialogOpen, setAddDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<SectorRow | null>(null);
    const [decisionTarget, setDecisionTarget] = useState<PendingDecisionTarget | null>(null);
    const { isPending, start, finish } = usePendingAction();

    const addForm = useForm({ category: '', year: String(new Date().getFullYear()), description: '' });
    const editForm = useForm({ category: '', year: '', description: '' });

    const normalizedIndicatorFilter = indicatorFilter.trim().toLowerCase();
    const filteredRows = rows.data.filter((row) =>
        `${row.category} ${String(row.year)} ${row.description}`
            .toLowerCase()
            .includes(normalizedIndicatorFilter),
    );

    function addRow(e: { preventDefault(): void }): void {
        e.preventDefault();
        addForm.post(`/sectors/${module.slug}/rows`, {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset();
                setAddDialogOpen(false);
            },
        });
    }

    function confirmDelete(): void {
        if (deleteTarget === null) return;

        start('delete');
        router.delete(`/sectors/${module.slug}/rows/${String(deleteTarget.id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish('delete');
                setDeleteTarget(null);
            },
        });
    }

    function confirmDecision(): void {
        if (decisionTarget === null) return;
        const action = `decision:${String(decisionTarget.id)}`;
        start(action);
        router.post(
            `/sectors/${module.slug}/pending/${String(decisionTarget.id)}/decision`,
            { decision: decisionTarget.decision },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                    setDecisionTarget(null);
                },
            },
        );
    }

    function openEdit(row: SectorRow): void {
        setEditTarget(row);
        editForm.setData({
            category: row.category,
            year: String(row.year),
            description: row.description,
        });
    }

    function saveEdit(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (editTarget === null) return;
        editForm.put(`/sectors/${module.slug}/rows/${String(editTarget.id)}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditTarget(null);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{module.label}</h2>}
        >
            <Head title={module.label} />

            <div className="space-y-6">
                <SectorBanner module={module} />

                <DetailRoadmapsCard moduleSlug={module.slug} details={detailModules} />

                <ProgressSummaryCards progressSummary={progressSummary} />

                {canManage && pendingChanges.length > 0 && (
                    <PendingChangesCard
                        changes={pendingChanges}
                        isPending={isPending}
                        onSelectDecision={setDecisionTarget}
                    />
                )}

                <IndicatorsTableCard
                    rows={rows}
                    filteredRows={filteredRows}
                    filter={indicatorFilter}
                    onFilterChange={setIndicatorFilter}
                    canManage={canManage}
                    isPending={isPending}
                    onOpenAdd={() => {
                        setAddDialogOpen(true);
                    }}
                    onOpenEdit={openEdit}
                    onDeleteRow={setDeleteTarget}
                />

                {rows.links.length > 3 && <PaginationLinks links={rows.links} />}

                <div className="grid gap-4 lg:grid-cols-2">
                    <ProgressCard progress={progress} />
                    <ScheduleCard schedule={schedule} />
                </div>
            </div>

            <AddIndicatorDialog
                open={addDialogOpen}
                form={addForm}
                canManage={canManage}
                onOpenChange={(open) => {
                    setAddDialogOpen(open);
                    if (!open) addForm.reset();
                }}
                onClose={() => {
                    setAddDialogOpen(false);
                }}
                onSubmit={addRow}
            />

            <EditIndicatorDialog
                open={editTarget !== null}
                form={editForm}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditTarget(null);
                        editForm.reset();
                    }
                }}
                onClose={() => {
                    setEditTarget(null);
                }}
                onSubmit={saveEdit}
            />

            <PgsConfirmationDialog
                open={decisionTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDecisionTarget(null);
                }}
                title={
                    decisionTarget?.decision === 'Approved'
                        ? 'Approve pending change'
                        : 'Reject pending change'
                }
                description={
                    decisionTarget?.decision === 'Approved'
                        ? 'This will approve the submitted change and apply it to the roadmap.'
                        : 'This will reject the submitted change and remove it from the pending queue.'
                }
                confirmationTitle={
                    decisionTarget?.decision === 'Approved'
                        ? 'Confirm approval'
                        : 'Confirm rejection'
                }
                confirmationDescription={`${decisionTarget?.category ?? 'This change'} (${String(decisionTarget?.year ?? '')})${decisionTarget?.description ? ` — ${decisionTarget.description}` : ''}`}
                onConfirm={confirmDecision}
                loading={
                    decisionTarget !== null && isPending(`decision:${String(decisionTarget.id)}`)
                }
                loadingText={decisionTarget?.decision === 'Approved' ? 'Approving' : 'Rejecting'}
                confirmText={decisionTarget?.decision === 'Approved' ? 'Approve' : 'Reject'}
                confirmVariant={decisionTarget?.decision === 'Approved' ? 'default' : 'destructive'}
                kind={decisionTarget?.decision === 'Approved' ? 'approve' : 'reject'}
            />

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete indicator"
                description="This action permanently removes the roadmap indicator."
                confirmationTitle="Confirm indicator deletion"
                confirmationDescription={`"${deleteTarget?.description ?? 'This indicator'}" will be removed from the roadmap.`}
                onConfirm={confirmDelete}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
