import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
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
    const [drafts, setDrafts] = useState<Record<string, Record<string, string>>>({});
    const newColumns = [...new Set([...module.columns, ...module.year_columns])].filter(
        (column) => column !== 'is_head',
    );
    const [newData, setNewData] = useState<Record<string, string>>(() =>
        Object.fromEntries(newColumns.map((column) => [column, ''])),
    );
    const { isPending, start, finish } = usePendingAction();
    const editableColumns = [...new Set([...module.editable, ...module.year_columns])];

    function create(): void {
        start('create');
        router.post(`/sectors/${module.pillar}/${module.slug}`, newData, {
            preserveScroll: true,
            onSuccess: () => {
                setNewData(Object.fromEntries(newColumns.map((column) => [column, ''])));
            },
            onFinish: () => {
                finish('create');
            },
        });
    }

    function commit(id: number): void {
        const rowDraft = drafts[String(id)] ?? {};
        if (Object.keys(rowDraft).length === 0) return;
        const action = `save:${String(id)}`;
        start(action);

        router.put(`/sectors/${module.pillar}/${module.slug}/${String(id)}`, rowDraft, {
            preserveScroll: true,
            onSuccess: () => {
                setDrafts((prev) => {
                    const next: Record<string, Record<string, string>> = {};

                    for (const [key, value] of Object.entries(prev)) {
                        if (key !== String(id)) {
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

    function remove(id: number): void {
        const action = `delete:${String(id)}`;
        start(action);
        router.delete(`/sectors/${module.pillar}/${module.slug}/${String(id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish(action);
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
                            <CardTitle className="flex items-center gap-2">
                                <Plus className="size-4" /> Add roadmap row
                            </CardTitle>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Add a new record to this detailed roadmap table.
                            </p>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <a href={`/sectors/${module.pillar}/${module.slug}/export`}>
                                <Download className="size-4" /> Export CSV
                            </a>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            {newColumns.map((column) => (
                                <div key={column} className="space-y-2">
                                    <label
                                        className="text-xs font-semibold"
                                        htmlFor={`new-${column}`}
                                    >
                                        {column.replace(/_/g, ' ')}
                                    </label>
                                    <input
                                        id={`new-${column}`}
                                        value={newData[column] ?? ''}
                                        onChange={(event) => {
                                            setNewData({
                                                ...newData,
                                                [column]: event.target.value,
                                            });
                                        }}
                                        className="border-input bg-background flex h-8 w-full rounded-md border px-2 text-sm"
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 flex justify-end">
                            <Button
                                onClick={create}
                                loading={isPending('create')}
                                loadingText="Adding"
                            >
                                <Plus className="size-4" /> Add row
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{module.label}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
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
                                                <TableCell
                                                    key={column}
                                                    className="whitespace-nowrap"
                                                >
                                                    {editableColumns.includes(column) ? (
                                                        <input
                                                            type="text"
                                                            defaultValue={row[column] ?? ''}
                                                            disabled={
                                                                Boolean(row.locked) && !canManage
                                                            }
                                                            onBlur={(e) => {
                                                                const value = e.target.value;
                                                                setDrafts((prev) => ({
                                                                    ...prev,
                                                                    [String(row.id)]: {
                                                                        ...(prev[String(row.id)] ??
                                                                            {}),
                                                                        [column]: value,
                                                                    },
                                                                }));
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
                                                        <Badge
                                                            variant="outline"
                                                            className="mr-1 gap-1"
                                                        >
                                                            <LockKeyhole className="size-3" />{' '}
                                                            Locked
                                                        </Badge>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Save row"
                                                        disabled={Boolean(row.locked) && !canManage}
                                                        loading={isPending(
                                                            `save:${String(row.id)}`,
                                                        )}
                                                        onClick={() => {
                                                            commit(Number(row.id));
                                                        }}
                                                    >
                                                        <Save className="size-4" />
                                                    </Button>
                                                    {lockColumn !== null && canManage && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={
                                                                row.locked
                                                                    ? 'Unlock row'
                                                                    : 'Lock row'
                                                            }
                                                            loading={isPending(
                                                                `lock:${String(row.id)}`,
                                                            )}
                                                            onClick={() => {
                                                                toggleLock(Number(row.id));
                                                            }}
                                                        >
                                                            {row.locked ? (
                                                                <LockOpen className="size-4" />
                                                            ) : (
                                                                <LockKeyhole className="size-4" />
                                                            )}
                                                        </Button>
                                                    )}
                                                    {canManage && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Delete row"
                                                            className="text-destructive"
                                                            loading={isPending(
                                                                `delete:${String(row.id)}`,
                                                            )}
                                                            onClick={() => {
                                                                remove(Number(row.id));
                                                            }}
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    )}
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
                        </div>
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
        </AuthenticatedLayout>
    );
}
