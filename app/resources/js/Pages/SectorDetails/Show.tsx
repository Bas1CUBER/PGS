import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Save } from 'lucide-react';
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

interface SectorDetailPageProps extends PageProps {
    module: {
        slug: string;
        label: string;
        pillar: string;
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
}

export default function SectorDetailShow({ module, columns, rows }: SectorDetailPageProps) {
    const [drafts, setDrafts] = useState<Record<string, Record<string, string>>>({});

    function commit(id: number): void {
        const rowDraft = drafts[String(id)] ?? {};
        if (Object.keys(rowDraft).length === 0) return;

        router.put(`/sector-details/${module.slug}/${String(id)}`, rowDraft, {
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
        });
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
                        {module.pillar}
                    </Link>
                </Button>

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
                                                    <input
                                                        type="text"
                                                        defaultValue={row[column] ?? ''}
                                                        onBlur={(e) => {
                                                            const value = e.target.value;
                                                            setDrafts((prev) => ({
                                                                ...prev,
                                                                [String(row.id)]: {
                                                                    ...(prev[String(row.id)] ?? {}),
                                                                    [column]: value,
                                                                },
                                                            }));
                                                        }}
                                                        className="hover:border-input focus:border-input focus:bg-background h-8 w-full min-w-24 rounded-md border border-transparent bg-transparent px-2 text-sm"
                                                    />
                                                </TableCell>
                                            ))}
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        commit(Number(row.id));
                                                    }}
                                                >
                                                    <Save className="size-4" />
                                                </Button>
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
                                        <Link href={link.url}>
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
