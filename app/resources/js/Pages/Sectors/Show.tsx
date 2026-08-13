import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, CalendarClock, ListChecks, Table2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

interface SectorDetailLink {
    slug: string;
    label: string;
}

interface SectorShowPageProps extends PageProps {
    module: {
        slug: string;
        label: string;
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
}: SectorShowPageProps) {
    const [editTarget, setEditTarget] = useState<SectorRow | null>(null);
    const [editCategory, setEditCategory] = useState('');
    const [editYear, setEditYear] = useState('');
    const [editDescription, setEditDescription] = useState('');

    function openEdit(row: SectorRow): void {
        setEditTarget(row);
        setEditCategory(row.category);
        setEditYear(String(row.year));
        setEditDescription(row.description);
    }

    function saveEdit(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (editTarget === null) return;
        router.put(
            `/sectors/${module.slug}/rows/${String(editTarget.id)}`,
            { category: editCategory, year: editYear, description: editDescription },
            { preserveScroll: true },
        );
        setEditTarget(null);
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{module.label}</h2>}
        >
            <Head title={module.label} />

            <div className="space-y-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href="/sectors">
                        <ArrowLeft className="size-4" />
                        All sectors
                    </Link>
                </Button>

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
                                        <Link href={`/sector-details/${detail.slug}`}>
                                            {detail.label}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Indicators</CardTitle>
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
                                {rows.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            {row.category}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {row.year}
                                        </TableCell>
                                        <TableCell className="text-sm">{row.description}</TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    openEdit(row);
                                                }}
                                            >
                                                Edit
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {rows.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            No indicators yet.
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

            {editTarget !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <Card className="w-full max-w-lg">
                        <CardHeader>
                            <CardTitle>Edit indicator</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={saveEdit} className="space-y-3">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <label htmlFor="cat" className="text-sm font-medium">
                                            Category
                                        </label>
                                        <Input
                                            id="cat"
                                            value={editCategory}
                                            onChange={(e) => {
                                                setEditCategory(e.target.value);
                                            }}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label htmlFor="yr" className="text-sm font-medium">
                                            Year
                                        </label>
                                        <Input
                                            id="yr"
                                            type="number"
                                            value={editYear}
                                            onChange={(e) => {
                                                setEditYear(e.target.value);
                                            }}
                                            required
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <label htmlFor="desc" className="text-sm font-medium">
                                        Description
                                    </label>
                                    <textarea
                                        id="desc"
                                        value={editDescription}
                                        onChange={(e) => {
                                            setEditDescription(e.target.value);
                                        }}
                                        rows={4}
                                        className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                        required
                                    />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setEditTarget(null);
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit">Save</Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
