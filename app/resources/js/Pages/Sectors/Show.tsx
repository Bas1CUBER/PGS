import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    CalendarClock,
    CheckCircle2,
    CircleDashed,
    CircleX,
    ListChecks,
    Pencil,
    Plus,
    Search,
    Table2,
    Trash2,
    XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { relativeInternalUrl } from '@/lib/relative-url';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsStatCard } from '@/components/pgs-stat-card';
import { legacyImageUrl } from '@/lib/legacy-asset';
import { TableRowActions } from '@/components/table-row-actions';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

interface SectorRow {
    id: number;
    category: string;
    year: number;
    description: string;
}

interface SectorProgress {
    id: number;
    category: string;
    year: number;
    month: string;
    status: string;
    remarks: string | null;
    description: string | null;
}

interface PendingDecisionTarget {
    id: number;
    decision: 'Approved' | 'Rejected';
    category: string;
    year: number;
    description: string | null;
}

interface SectorDetailLink {
    slug: string;
    label: string;
}

interface SectorShowPageProps extends PageProps {
    module: {
        slug: string;
        label: string;
        logo: string;
        table: string;
        progress_table: string;
        schedule_table: string | null;
    };
    rows: {
        data: SectorRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    progress: SectorProgress[];
    schedule:
        | {
              id: number;
              category: string;
              year: number;
              month: string;
              description: string;
          }[]
        | null;
    details: SectorDetailLink[];
    progressSummary: Record<string, number | undefined>;
    pendingChanges: {
        id: number;
        change_type: string;
        category: string;
        year: number;
        month: number | null;
        status: string | null;
        description: string | null;
        submitted_at: string;
    }[];
    canManage: boolean;
}

const statusStyles: Record<string, string> = {
    Completed: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Ongoing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    Pending: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

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
                <Card className="pgs-sector-banner">
                    <CardContent className="flex items-center gap-4 p-5 sm:p-6">
                        <div className="pgs-sector-logo" aria-hidden="true">
                            <img src={legacyImageUrl(module.logo)} alt="" />
                        </div>
                        <div>
                            <p className="pgs-section-kicker">Sector roadmap</p>
                            <h1 className="text-2xl font-semibold">{module.label}</h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Indicators, progress tracking, schedules, and detailed roadmaps.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Table2 className="size-4" />
                            Detail roadmaps
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {detailModules.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No detail roadmaps for this pillar yet.
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {detailModules.map((detail) => (
                                    <Button key={detail.slug} asChild variant="outline" size="sm">
                                        <Link href={`/sectors/${module.slug}/${detail.slug}`}>
                                            {detail.label}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-3">
                    <PgsStatCard
                        label="Accomplished"
                        value={progressSummary.Accomplished ?? 0}
                        icon={<CheckCircle2 className="size-5" />}
                        status="Complete"
                        detail="Completed indicators"
                        tone="green"
                        compact
                    />
                    <PgsStatCard
                        label="Ongoing"
                        value={progressSummary.Ongoing ?? 0}
                        icon={<CircleDashed className="size-5" />}
                        status="Active"
                        detail="Indicators in progress"
                        tone="blue"
                        compact
                    />
                    <PgsStatCard
                        label="Not accomplished / started"
                        value={progressSummary['Not Accomplished/Started'] ?? 0}
                        icon={<CircleX className="size-5" />}
                        status="Needs attention"
                        detail="Indicators needing action"
                        tone="red"
                        compact
                    />
                </div>

                {canManage && pendingChanges.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Pending roadmap changes</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {pendingChanges.map((change) => (
                                <div
                                    key={change.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {change.change_type === 'add_row'
                                                ? 'New indicator'
                                                : 'Progress update'}{' '}
                                            — {change.category} ({change.year})
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {change.description ?? change.status ?? 'No details'} ·{' '}
                                            {new Date(change.submitted_at).toLocaleString()}
                                        </p>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            className="pgs-success-action-button"
                                            onClick={() => {
                                                setDecisionTarget({
                                                    id: change.id,
                                                    decision: 'Approved',
                                                    category: change.category,
                                                    year: change.year,
                                                    description: change.description,
                                                });
                                            }}
                                            loading={isPending(`decision:${String(change.id)}`)}
                                        >
                                            <CheckCircle2 className="size-4" /> Approve
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            className="pgs-destructive-action-button"
                                            onClick={() => {
                                                setDecisionTarget({
                                                    id: change.id,
                                                    decision: 'Rejected',
                                                    category: change.category,
                                                    year: change.year,
                                                    description: change.description,
                                                });
                                            }}
                                            loading={isPending(`decision:${String(change.id)}`)}
                                        >
                                            <XCircle className="size-4" /> Reject
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="table-toolbar">
                        <div className="table-toolbar-heading">
                            <CardTitle>Indicators</CardTitle>
                            <p className="table-toolbar-meta">
                                {rows.data.length}{' '}
                                {rows.data.length === 1 ? 'indicator' : 'indicators'}
                            </p>
                        </div>
                        <div className="table-toolbar-actions">
                            <div className="table-search-shell">
                                <Search className="table-search-icon" aria-hidden="true" />
                                <Input
                                    className="table-search"
                                    value={indicatorFilter}
                                    onChange={(event) => {
                                        setIndicatorFilter(event.target.value);
                                    }}
                                    placeholder="Filter indicators..."
                                    aria-label="Filter indicators"
                                />
                            </div>
                            <Button
                                type="button"
                                onClick={() => {
                                    setAddDialogOpen(true);
                                }}
                            >
                                <Plus className="size-4" /> Add indicator
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Year</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredRows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            {row.category}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {row.year}
                                        </TableCell>
                                        <TableCell className="text-sm">{row.description}</TableCell>
                                        <TableCell className="text-right">
                                            <TableRowActions label={row.description}>
                                                <DropdownMenuItem
                                                    onSelect={() => {
                                                        openEdit(row);
                                                    }}
                                                >
                                                    <Pencil className="size-4" /> Edit
                                                </DropdownMenuItem>
                                                {canManage && (
                                                    <>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            disabled={isPending('delete')}
                                                            onSelect={() => {
                                                                setDeleteTarget(row);
                                                            }}
                                                        >
                                                            <Trash2 className="size-4" /> Delete
                                                        </DropdownMenuItem>
                                                    </>
                                                )}
                                            </TableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {filteredRows.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            {rows.data.length === 0
                                                ? 'No indicators yet.'
                                                : 'No indicators match your filter.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {rows.links.length > 3 && (
                    <div className="flex justify-center gap-2">
                        {rows.links.map((link, index) => (
                            <span key={index}>
                                {link.url ? (
                                    <Button
                                        asChild
                                        variant={link.active ? 'default' : 'ghost'}
                                        size="sm"
                                    >
                                        <Link href={relativeInternalUrl(link.url) ?? '#'}>
                                            {link.label.replace(/&laquo;|&raquo;/g, '')}
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button variant="ghost" size="sm" disabled>
                                        {link.label.replace(/&laquo;|&raquo;/g, '')}
                                    </Button>
                                )}
                            </span>
                        ))}
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ListChecks className="size-4" />
                                Progress
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {progress.length === 0 ? (
                                <p className="text-muted-foreground px-6 pb-6 text-sm">
                                    No progress entries.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {progress.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="flex items-center justify-between gap-3 px-6 py-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">
                                                    {entry.category} - {entry.year}
                                                    {entry.month !== '' ? ` (${entry.month})` : ''}
                                                </p>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {entry.remarks ?? entry.description ?? ''}
                                                </p>
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className={cn(statusStyles[entry.status] ?? '')}
                                            >
                                                {entry.status}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CalendarClock className="size-4" />
                                Schedule
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {schedule === null ? (
                                <p className="text-muted-foreground px-6 pb-6 text-sm">
                                    No schedule for this pillar.
                                </p>
                            ) : schedule.length === 0 ? (
                                <p className="text-muted-foreground px-6 pb-6 text-sm">
                                    No schedule entries.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {schedule.map((entry) => (
                                        <li key={entry.id} className="px-6 py-3">
                                            <p className="text-sm font-medium">
                                                {entry.category} - {entry.year} ({entry.month})
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {entry.description}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={addDialogOpen} onOpenChange={(open) => {
                setAddDialogOpen(open);
                if (!open) addForm.reset();
            }}>
                <DialogContent className="pgs-modal-form-dialog">
                    <DialogHeader className="pgs-modal-header">
                        <span className="pgs-modal-eyebrow">Responsive overlay</span>
                        <DialogTitle>Add roadmap indicator</DialogTitle>
                        <DialogDescription>
                            Add a category, year, and description to this roadmap.
                            {!canManage && ' New indicators are sent to an admin for approval.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={addRow} className="pgs-modal-form pgs-modal-form-scroll">
                        <DialogBody>
                            <div className="pgs-modal-field">
                                <label htmlFor="new-category">Category</label>
                                <Input
                                    id="new-category"
                                    value={addForm.data.category}
                                    onChange={(e) => {
                                        addForm.setData('category', e.target.value);
                                    }}
                                    required
                                />
                                {addForm.errors.category && (
                                    <p className="text-destructive text-sm">{addForm.errors.category}</p>
                                )}
                            </div>
                            <div className="pgs-modal-field">
                                <label htmlFor="new-year">Year</label>
                                <Input
                                    id="new-year"
                                    type="number"
                                    value={addForm.data.year}
                                    onChange={(e) => {
                                        addForm.setData('year', e.target.value);
                                    }}
                                    required
                                />
                                {addForm.errors.year && (
                                    <p className="text-destructive text-sm">{addForm.errors.year}</p>
                                )}
                            </div>
                            <div className="pgs-modal-field">
                                <label htmlFor="new-description">Description</label>
                                <textarea
                                    id="new-description"
                                    value={addForm.data.description}
                                    onChange={(e) => {
                                        addForm.setData('description', e.target.value);
                                    }}
                                    rows={4}
                                    className="pgs-modal-textarea"
                                    required
                                />
                                {addForm.errors.description && (
                                    <p className="text-destructive text-sm">{addForm.errors.description}</p>
                                )}
                            </div>
                        </DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setAddDialogOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" loading={addForm.processing} loadingText="Saving">
                                <Plus className="size-4" /> Add
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditTarget(null);
                        editForm.reset();
                    }
                }}
            >
                <DialogContent className="pgs-modal-form-dialog">
                    <DialogHeader className="pgs-modal-header">
                        <span className="pgs-modal-eyebrow">Responsive overlay</span>
                        <DialogTitle>Edit indicator</DialogTitle>
                        <DialogDescription>
                            Update the selected roadmap indicator.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={saveEdit} className="pgs-modal-form pgs-modal-form-scroll">
                        <DialogBody>
                            <div className="pgs-modal-field">
                                <label htmlFor="cat">Category</label>
                                <Input
                                    id="cat"
                                    value={editForm.data.category}
                                    onChange={(e) => {
                                        editForm.setData('category', e.target.value);
                                    }}
                                    required
                                />
                                {editForm.errors.category && (
                                    <p className="text-destructive text-sm">{editForm.errors.category}</p>
                                )}
                            </div>
                            <div className="pgs-modal-field">
                                <label htmlFor="yr">Year</label>
                                <Input
                                    id="yr"
                                    type="number"
                                    value={editForm.data.year}
                                    onChange={(e) => {
                                        editForm.setData('year', e.target.value);
                                    }}
                                    required
                                />
                                {editForm.errors.year && (
                                    <p className="text-destructive text-sm">{editForm.errors.year}</p>
                                )}
                            </div>
                            <div className="pgs-modal-field">
                                <label htmlFor="desc">Description</label>
                                <textarea
                                    id="desc"
                                    value={editForm.data.description}
                                    onChange={(e) => {
                                        editForm.setData('description', e.target.value);
                                    }}
                                    rows={4}
                                    className="pgs-modal-textarea"
                                    required
                                />
                                {editForm.errors.description && (
                                    <p className="text-destructive text-sm">{editForm.errors.description}</p>
                                )}
                            </div>
                        </DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setEditTarget(null);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" loading={editForm.processing} loadingText="Saving">
                                Save
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

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
