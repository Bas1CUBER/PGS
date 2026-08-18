import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    Activity,
    ArrowLeft,
    BookOpen,
    CircleCheck,
    Download,
    LockKeyhole,
    LockOpen,
    Plus,
    Presentation,
    Save,
    Trash2,
} from 'lucide-react';
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
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PageProps } from '@/types';
import { relativeInternalUrl } from '@/lib/relative-url';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsStatCard, type PgsStatTone } from '@/components/pgs-stat-card';
import { legacyImageUrl } from '@/lib/legacy-asset';
import { TableRowActions } from '@/components/table-row-actions';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

interface SectorDetailPageProps extends PageProps {
    module: {
        slug: string;
        label: string;
        pillar: string;
        pillar_label: string;
        logo: string;
        table: string;
        columns: string[];
        year_columns: string[];
        editable: string[];
    };
    columns: string[];
    rows: {
        data: Record<string, string | null>[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    stats: {
        ongoing: number;
        completed: number;
        presented: number;
        published: number;
    } | null;
    lockColumn: string | null;
    canManage: boolean;
}

type ResearchStatKey = keyof NonNullable<SectorDetailPageProps['stats']>;

const researchStatDefinitions: {
    key: ResearchStatKey;
    label: string;
    status: string;
    detail: string;
    tone: PgsStatTone;
    icon: typeof Activity;
}[] = [
    {
        key: 'ongoing',
        label: 'On-going',
        status: 'In progress',
        detail: 'Active research work',
        tone: 'blue',
        icon: Activity,
    },
    {
        key: 'completed',
        label: 'Completed',
        status: 'Submitted',
        detail: 'Submitted outputs',
        tone: 'green',
        icon: CircleCheck,
    },
    {
        key: 'presented',
        label: 'Presented',
        status: 'Presented',
        detail: 'Shared research outputs',
        tone: 'violet',
        icon: Presentation,
    },
    {
        key: 'published',
        label: 'Published',
        status: 'Published',
        detail: 'Published outputs',
        tone: 'amber',
        icon: BookOpen,
    },
];

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

                <Card className="pgs-sector-banner">
                    <CardContent className="flex items-center gap-4 p-5 sm:p-6">
                        <div className="pgs-sector-logo" aria-hidden="true">
                            <img src={legacyImageUrl(module.logo)} alt="" />
                        </div>
                        <div>
                            <p className="pgs-section-kicker">Detailed roadmap</p>
                            <h1 className="text-2xl font-semibold">{module.label}</h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {module.pillar_label} · legacy roadmap detail
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {stats !== null && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {researchStatDefinitions.map((stat) => {
                            const Icon = stat.icon;

                            return (
                                <PgsStatCard
                                    key={stat.key}
                                    compact
                                    label={stat.label}
                                    value={stats[stat.key]}
                                    icon={<Icon className="size-5" />}
                                    status={stat.status}
                                    detail={stat.detail}
                                    tone={stat.tone}
                                />
                            );
                        })}
                    </div>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle>Roadmap data</CardTitle>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Manage records in this detailed roadmap table.
                            </p>
                        </div>
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                type="button"
                                onClick={() => {
                                    setCreateDialogOpen(true);
                                }}
                            >
                                <Plus className="size-4" /> Add row
                            </Button>
                            <Button asChild variant="outline">
                                <a href={`/sectors/${module.pillar}/${module.slug}/export`}>
                                    <Download className="size-4" /> Export CSV
                                </a>
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{module.label}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {columns.map((column) => (
                                        <TableHead key={column} className="whitespace-nowrap">
                                            {column.replace(/_/g, ' ')}
                                        </TableHead>
                                    ))}
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.data.map((row) => (
                                    <TableRow key={row.id}>
                                        {columns.map((column) => (
                                            <TableCell key={column} className="whitespace-nowrap">
                                                {editableColumns.includes(column) ? (
                                                    <input
                                                        type="text"
                                                        defaultValue={row[column] ?? ''}
                                                        disabled={
                                                            (Boolean(row.locked) && !canManage) ||
                                                            isPending(`save:${String(row.id)}`)
                                                        }
                                                        onBlur={(e) => {
                                                            const value = e.target.value;
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
                                                        }}
                                                        className="hover:border-input focus:border-input focus:bg-background h-8 w-full min-w-24 rounded-md border border-transparent bg-transparent px-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                                                    />
                                                ) : (
                                                    <span>{row[column] ?? '—'}</span>
                                                )}
                                            </TableCell>
                                        ))}
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                {row.locked && (
                                                    <Badge variant="outline" className="mr-1 gap-1">
                                                        <LockKeyhole className="size-3" /> Locked
                                                    </Badge>
                                                )}
                                                <TableRowActions label={`Row #${String(row.id)}`}>
                                                    <DropdownMenuItem
                                                        disabled={
                                                            (Boolean(row.locked) && !canManage) ||
                                                            isPending(`save:${String(row.id)}`)
                                                        }
                                                        onSelect={() => {
                                                            commit(Number(row.id));
                                                        }}
                                                    >
                                                        <Save className="size-4" /> Save
                                                    </DropdownMenuItem>
                                                    {lockColumn !== null && canManage && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                disabled={isPending(
                                                                    `lock:${String(row.id)}`,
                                                                )}
                                                                onSelect={() => {
                                                                    toggleLock(Number(row.id));
                                                                }}
                                                            >
                                                                {row.locked ? (
                                                                    <LockOpen className="size-4" />
                                                                ) : (
                                                                    <LockKeyhole className="size-4" />
                                                                )}
                                                                {row.locked ? 'Unlock' : 'Lock'} row
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                    {canManage && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                disabled={isPending('delete')}
                                                                onSelect={() => {
                                                                    setDeleteTarget(Number(row.id));
                                                                }}
                                                            >
                                                                <Trash2 className="size-4" /> Delete
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                </TableRowActions>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {rows.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={columns.length + 1}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            No rows in this table yet.
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
            </div>

            <Dialog
                open={createDialogOpen}
                onOpenChange={(open) => {
                    setCreateDialogOpen(open);
                    if (!open) {
                        createForm.reset();
                    }
                }}
            >
                <DialogContent className="pgs-modal-form-dialog pgs-modal-wide">
                    <DialogHeader>
                        <DialogTitle>Add roadmap row</DialogTitle>
                        <DialogDescription>
                            Add a new record to this detailed roadmap table.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            create();
                        }}
                        className="pgs-modal-form pgs-modal-form-scroll"
                    >
                        <DialogBody>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {newColumns.map((column) => (
                                    <div key={column} className="pgs-modal-field">
                                        <label htmlFor={`new-${column}`}>
                                            {column.replace(/_/g, ' ')}
                                        </label>
                                        <input
                                            id={`new-${column}`}
                                            value={createForm.data[column] ?? ''}
                                            onChange={(event) => {
                                                createForm.setData(column, event.target.value);
                                            }}
                                        />
                                        {createForm.errors[column] && (
                                            <p className="text-destructive text-sm">
                                                {createForm.errors[column]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setCreateDialogOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                loading={createForm.processing}
                                loadingText="Adding"
                            >
                                <Plus className="size-4" /> Add row
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
