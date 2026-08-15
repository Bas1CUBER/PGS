import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Download, Pencil, Plus, Search } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
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
import { TableRowActions } from '@/components/table-row-actions';

interface DeliverableRow {
    id: number;
    title: string | null;
    form_type: string | null;
    division: string | null;
    target_date: string | null;
    status: string | null;
    mov_file: string | null;
    uploader: { id: number; name: string | null; email: string } | null;
}

interface DeliverablesPageProps extends PageProps {
    deliverables: {
        data: DeliverableRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string; status: string };
    statuses: string[];
}

const statusStyles: Record<string, string> = {
    Accomplished: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Ongoing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    'Not Yet Started': 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

export default function DeliverablesIndex({
    deliverables,
    filters,
    statuses,
}: DeliverablesPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        router.get('/deliverables', { search, status }, { preserveState: true, replace: true });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Deliverables</h2>}
        >
            <Head title="Deliverables" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <form onSubmit={submit} className="flex w-full max-w-md items-center gap-2">
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                }}
                                placeholder="Search title or division…"
                                className="pl-9"
                                aria-label="Search deliverables"
                            />
                        </div>
                        <select
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                router.get(
                                    '/deliverables',
                                    { search, status: e.target.value },
                                    { preserveState: true, replace: true },
                                );
                            }}
                            className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                            aria-label="Filter by status"
                        >
                            <option value="">All statuses</option>
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>
                        <Button type="submit" size="sm">
                            Search
                        </Button>
                    </form>

                    <Button asChild size="sm">
                        <Link href="/deliverables/create">
                            <Plus className="size-4" />
                            Add deliverable
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Division</TableHead>
                                    <TableHead>Target date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Uploader</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {deliverables.data.map((deliverable) => (
                                    <TableRow key={deliverable.id}>
                                        <TableCell className="font-medium">
                                            {deliverable.title ?? '-'}
                                            {deliverable.form_type !== null && (
                                                <p className="text-muted-foreground text-xs">
                                                    {deliverable.form_type}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {deliverable.division ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {deliverable.target_date ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    statusStyles[deliverable.status ?? ''],
                                                )}
                                            >
                                                {deliverable.status ?? '-'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {deliverable.uploader?.name ??
                                                deliverable.uploader?.email ??
                                                '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <TableRowActions
                                                label={
                                                    deliverable.title ??
                                                    `Deliverable #${String(deliverable.id)}`
                                                }
                                            >
                                                {deliverable.mov_file !== null && (
                                                    <>
                                                        <DropdownMenuItem asChild>
                                                            <a
                                                                href={`/deliverables/${String(deliverable.id)}/download`}
                                                            >
                                                                <Download className="size-4" />{' '}
                                                                Download
                                                            </a>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                    </>
                                                )}
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={`/deliverables/${String(deliverable.id)}/edit`}
                                                    >
                                                        <Pencil className="size-4" /> Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                            </TableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {deliverables.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            No deliverables match your filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {deliverables.links.length > 3 && (
                    <div className="flex justify-center gap-2">
                        {deliverables.links.map((link, index) => (
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
