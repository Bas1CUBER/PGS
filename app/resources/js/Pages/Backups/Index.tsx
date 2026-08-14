import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Database, Download, RefreshCw, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useState } from 'react';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';

interface BackupRow {
    disk: string;
    path: string;
    size: number;
    date: number;
}

interface BackupsPageProps extends PageProps {
    backups: BackupRow[];
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return String(bytes) + ' B';
    const kb = bytes / 1024;
    if (kb < 1024) return kb.toFixed(1) + ' kB';
    const mb = kb / 1024;
    if (mb < 1024) return mb.toFixed(1) + ' MB';
    return (mb / 1024).toFixed(2) + ' GB';
}

export default function BackupsIndex({ backups }: BackupsPageProps) {
    const [creating, setCreating] = useState(false);
    const { isPending, start, finish } = usePendingAction();

    function createBackup(): void {
        setCreating(true);
        router.post('/backups', undefined, {
            onFinish: () => {
                setCreating(false);
            },
        });
    }

    function deleteBackup(backup: BackupRow): void {
        const action = `delete:${backup.path}`;
        start(action);
        router.delete(`/backups/${backup.disk}/${backup.path}`, {
            onFinish: () => {
                finish(action);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Backups</h2>}
        >
            <Head title="Backups" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="text-muted-foreground flex items-center gap-2">
                        <Database className="size-5" />
                        <p className="text-sm">Database-only snapshots stored on the local disk.</p>
                    </div>
                    <Button
                        onClick={createBackup}
                        loading={creating}
                        loadingText="Creating"
                        disabled={creating}
                    >
                        <RefreshCw className="size-4" />
                        Create backup
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Snapshots</CardTitle>
                        <CardDescription>Most recent 50 backups.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>File</TableHead>
                                    <TableHead>Size</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {backups.map((backup) => (
                                    <TableRow key={backup.path}>
                                        <TableCell className="font-sans text-xs">
                                            {backup.path.split('/').pop()}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {formatBytes(backup.size)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {new Date(backup.date).toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                    aria-label="Download backup"
                                                >
                                                    <a
                                                        href={`/backups/${backup.disk}/${backup.path}`}
                                                    >
                                                        <Download className="size-4" />
                                                    </a>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    aria-label="Delete backup"
                                                    loading={isPending(`delete:${backup.path}`)}
                                                    loadingText=""
                                                    onClick={() => {
                                                        deleteBackup(backup);
                                                    }}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {backups.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            No backups yet — create your first snapshot.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
