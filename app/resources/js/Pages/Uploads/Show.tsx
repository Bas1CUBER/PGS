import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Check, Download, RotateCcw, Trash2, Upload } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface UploadRow {
    id: number;
    title: string | null;
    description: string | null;
    filename: string;
    original_name: string;
    file_size: number;
    status: string | null;
    uploaded_at: string;
    uploader: string | null;
}

interface UploadsShowPageProps extends PageProps {
    module: {
        slug: string;
        label: string;
        table: string;
        has_title: boolean;
        has_description: boolean;
        has_status: boolean;
        status_values: string[] | null;
        uploader_fk: string;
        uploader_label: string;
        singular: string;
    };
    rows: UploadRow[];
    filters: { status: string };
}

const statusStyles: Record<string, string> = {
    Approved: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    Returned: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    'In Progress': 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
};

function formatBytes(bytes: number): string {
    if (bytes < 1024) return String(bytes) + ' B';
    const kb = bytes / 1024;
    if (kb < 1024) return kb.toFixed(1) + ' kB';
    return (kb / 1024).toFixed(1) + ' MB';
}

export default function UploadsShow({ module, rows }: UploadsShowPageProps) {
    const { auth } = usePage().props;
    const user = auth.user;
    const canReview = user !== null && (user.role === 'focal' || user.role === 'admin');

    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (file === null) return;

        const form = new FormData();
        form.append('file', file);
        if (module.has_title) form.append('title', title);
        if (module.has_description) form.append('description', description);

        setUploading(true);
        router.post(`/uploads/${module.slug}`, form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                setTitle('');
                setDescription('');
                setFile(null);
            },
        });
    }

    function setStatus(id: number, status: string): void {
        router.put(
            `/uploads/${module.slug}/${String(id)}/status`,
            { status },
            { preserveScroll: true },
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{module.label}</h2>}
        >
            <Head title={module.label} />

            <div className="space-y-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href="/uploads">
                        <ArrowLeft className="size-4" />
                        All upload modules
                    </Link>
                </Button>

                <Card>
                    <CardHeader>
                        <CardTitle>Upload {module.singular}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-3">
                            {module.has_title && (
                                <div className="space-y-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        value={title}
                                        onChange={(e) => {
                                            setTitle(e.target.value);
                                        }}
                                        required
                                    />
                                </div>
                            )}
                            {module.has_description && (
                                <div className="space-y-2">
                                    <Label htmlFor="description">Description</Label>
                                    <textarea
                                        id="description"
                                        value={description}
                                        onChange={(e) => {
                                            setDescription(e.target.value);
                                        }}
                                        rows={2}
                                        className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                </div>
                            )}
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <Input
                                    type="file"
                                    onChange={(e) => {
                                        setFile(e.target.files?.[0] ?? null);
                                    }}
                                    className="max-w-md"
                                />
                                <Button type="submit" disabled={uploading || file === null}>
                                    <Upload className="size-4" />
                                    {uploading ? 'UploadingÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦' : 'Upload'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>File</TableHead>
                                    {module.has_title && <TableHead>Title</TableHead>}
                                    <TableHead>Size</TableHead>
                                    <TableHead>Uploaded</TableHead>
                                    {module.has_status && <TableHead>Status</TableHead>}
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="max-w-64">
                                            <p className="truncate font-medium">
                                                {row.original_name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {row.uploader ?? 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â'}
                                            </p>
                                        </TableCell>
                                        {module.has_title && (
                                            <TableCell className="text-sm">
                                                {row.title ?? 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â'}
                                            </TableCell>
                                        )}
                                        <TableCell className="text-muted-foreground text-sm">
                                            {formatBytes(row.file_size)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {new Date(row.uploaded_at).toLocaleString()}
                                        </TableCell>
                                        {module.has_status && (
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        statusStyles[row.status ?? ''] ?? '',
                                                    )}
                                                >
                                                    {row.status ?? 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â'}
                                                </Badge>
                                            </TableCell>
                                        )}
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                    aria-label="Download"
                                                >
                                                    <a
                                                        href={`/uploads/${module.slug}/${String(row.id)}/download`}
                                                    >
                                                        <Download className="size-4" />
                                                    </a>
                                                </Button>
                                                {module.has_status && canReview && (
                                                    <>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label="Approve"
                                                            onClick={() => {
                                                                setStatus(row.id, 'Approved');
                                                            }}
                                                        >
                                                            <Check className="size-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label="Return"
                                                            onClick={() => {
                                                                setStatus(row.id, 'Returned');
                                                            }}
                                                        >
                                                            <RotateCcw className="size-4" />
                                                        </Button>
                                                    </>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    aria-label="Delete"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        router.delete(
                                                            `/uploads/${module.slug}/${String(row.id)}`,
                                                        );
                                                    }}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {rows.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={module.has_status ? 6 : 5}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            Nothing here yet ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â upload the first{' '}
                                            {module.singular}.
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
