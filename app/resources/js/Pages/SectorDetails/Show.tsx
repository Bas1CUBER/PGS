import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import { CreateRowDialog } from './components/create-row-dialog';
import { DetailRowsTableCard } from './components/detail-rows-table-card';
import { PaginationLinks } from './components/pagination-links';
import { ResearchStatsGrid } from './components/research-stats-grid';
import { RoadmapDataCard } from './components/roadmap-data-card';
import { SectorBanner } from './components/sector-banner';
import type { SectorDetailPageProps, SectorDetailRow } from './components/types';

export default function SectorDetailShow({
    module,
    columns,
    rows,
    stats,
    lockColumn,
    canManage,
}: SectorDetailPageProps) {
    const [drafts, setDrafts] = useState<Partial<Record<string, Record<string, string>>>>({});
    const newColumns = [...new Set([...module.columns, ...module.year_columns])].filter(
        (column) => column !== 'is_head',
    );
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<number | null>(null);
    const { isPending, start, finish } = usePendingAction();
    const editableColumns = [...new Set([...module.editable, ...module.year_columns])];

    const createForm = useForm<Record<string, string>>(
        Object.fromEntries(newColumns.map((column) => [column, ''])),
    );

    const editForm = useForm({ _method: 'PUT' as const });

    function create(): void {
        start('create');
        createForm.post(`/sectors/${module.pillar}/${module.slug}`, {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setCreateDialogOpen(false);
            },
            onFinish: () => {
                finish('create');
            },
        });
    }

    function commit(id: number, draftOverride?: Record<string, string>): void {
        const rowDraft = draftOverride ?? drafts[String(id)] ?? {};
        if (Object.keys(rowDraft).length === 0) return;
        const action = `save:${String(id)}`;
        start(action);

        editForm.setData(rowDraft);
        editForm.put(`/sectors/${module.pillar}/${module.slug}/${String(id)}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDrafts((prev) => {
                    const next: Record<string, Record<string, string>> = {};

                    for (const [key, value] of Object.entries(prev)) {
                        if (key !== String(id) && value !== undefined) {
                            next[key] = value;
                        }
                    }

                    return next;
                });
            },
            onFinish: () => {
                finish(action);
            },
        });
    }

    function confirmDelete(): void {
        if (deleteTarget === null) return;
        start('delete');
        router.delete(`/sectors/${module.pillar}/${module.slug}/${String(deleteTarget)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish('delete');
                setDeleteTarget(null);
            },
        });
    }

    function toggleLock(id: number): void {
        const action = `lock:${String(id)}`;
        start(action);
        router.post(
            `/sectors/${module.pillar}/${module.slug}/${String(id)}/lock`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    function handleCellBlur(row: SectorDetailRow, column: string, value: string): void {
        const originalValue = row[column] ?? '';
        if (value === originalValue) return;

        const rowDraft = { [column]: value };
        setDrafts((prev) => ({
            ...prev,
            [String(row.id)]: {
                ...(prev[String(row.id)] ?? {}),
                ...rowDraft,
            },
        }));
        commit(Number(row.id), rowDraft);
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{module.label}</h2>}
        >
            <Head title={module.label} />

            <div className="space-y-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href={`/sectors/${module.pillar}`}>
                        <ArrowLeft className="size-4" />
                        {module.pillar_label}
                    </Link>
                </Button>

                <SectorBanner module={module} />

                {stats !== null && <ResearchStatsGrid stats={stats} />}

                <RoadmapDataCard
                    onCreateClick={() => {
                        setCreateDialogOpen(true);
                    }}
                    exportHref={`/sectors/${module.pillar}/${module.slug}/export`}
                />

                <DetailRowsTableCard
                    label={module.label}
                    columns={columns}
                    rows={rows.data}
                    editableColumns={editableColumns}
                    canManage={canManage}
                    lockColumn={lockColumn}
                    isPending={isPending}
                    onCellBlur={handleCellBlur}
                    onCommitRow={commit}
                    onToggleLock={toggleLock}
                    onDeleteRequest={(id) => {
                        setDeleteTarget(id);
                    }}
                />

                {rows.links.length > 3 && <PaginationLinks links={rows.links} />}
            </div>

            <CreateRowDialog
                open={createDialogOpen}
                onOpenChange={(open) => {
                    setCreateDialogOpen(open);
                    if (!open) {
                        createForm.reset();
                    }
                }}
                onClose={() => {
                    setCreateDialogOpen(false);
                }}
                onSubmit={create}
                columns={newColumns}
                form={createForm}
            />

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete detail row"
                description="This action permanently removes this sector detail row."
                confirmationTitle="Confirm detail row deletion"
                confirmationDescription={`Detail row #${String(deleteTarget ?? '')} will be removed.`}
                onConfirm={confirmDelete}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
