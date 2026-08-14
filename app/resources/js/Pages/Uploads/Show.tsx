import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Check,
    CircleCheck,
    Download,
    FileText,
    Hourglass,
    Image as ImageIcon,
    List,
    RotateCcw,
    Trash2,
    Undo2,
    Upload,
} from 'lucide-react';
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
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsStatCard, type PgsStatTone } from '@/components/pgs-stat-card';

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
    uploader_id: number;
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
        templates?: {
            label: string;
            file: string;
            preview: boolean;
            url: string;
            source?: 'static' | 'managed';
            id?: number;
        }[];
        template_upload_url: string;
        can_manage_templates: boolean;
    };
    rows: UploadRow[];
    filters: { status: string };
    stats: {
        total: number;
        pdf: number;
        image: number;
        approved: number;
        in_progress: number;
        returned: number;
    } | null;
}

type GovernanceStatKey = keyof NonNullable<UploadsShowPageProps['stats']>;

const governanceStatDefinitions: {
    key: GovernanceStatKey;
    label: string;
    status: string;
    detail: string;
    tone: PgsStatTone;
    icon: typeof List;
}[] = [
    {
        key: 'total',
        label: 'Total',
        status: 'All files',
        detail: 'All module files',
        tone: 'blue',
        icon: List,
    },
    {
        key: 'pdf',
        label: 'PDFs',
        status: 'PDF files',
        detail: 'Uploaded documents',
        tone: 'violet',
        icon: FileText,
    },
    {
        key: 'image',
        label: 'Images',
        status: 'Image files',
        detail: 'Uploaded images',
        tone: 'amber',
        icon: ImageIcon,
    },
    {
        key: 'approved',
        label: 'Approved',
        status: 'Approved',
        detail: 'Ready for use',
        tone: 'green',
        icon: CircleCheck,
    },
    {
        key: 'in_progress',
        label: 'In Progress',
        status: 'In progress',
        detail: 'Awaiting completion',
        tone: 'blue',
        icon: Hourglass,
    },
    {
        key: 'returned',
        label: 'Returned',
        status: 'Returned',
        detail: 'Needs attention',
        tone: 'red',
        icon: Undo2,
    },
];

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

export default function UploadsShow({ module, rows, stats }: UploadsShowPageProps) {
    const { auth } = usePage().props;
    const user = auth.user;
    const canReview = user !== null && (user.role === 'focal' || user.role === 'admin');

    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [templateLabel, setTemplateLabel] = useState('');
    const [templateFile, setTemplateFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const { isPending, start, finish } = usePendingAction();

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (file === null) return;

        const form = new FormData();
        form.append('file', file);
        if (module.has_title) form.append('title', title);
        if (module.has_description) form.append('description', description);

        setUploading(true);
        start('upload');
        router.post(`/uploads/${module.slug}`, form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                finish('upload');
                setTitle('');
                setDescription('');
                setFile(null);
            },
        });
    }

    function setStatus(id: number, status: string): void {
        const action = `status:${String(id)}`;
        start(action);
        router.put(
            `/uploads/${module.slug}/${String(id)}/status`,
            { status },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    function deleteRow(id: number): void {
        const action = `delete:${String(id)}`;
        start(action);
        router.delete(`/uploads/${module.slug}/${String(id)}`, {
            onFinish: () => {
                finish(action);
            },
        });
    }

    function submitTemplate(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (templateFile === null || templateLabel.trim() === '') return;

        const form = new FormData();
        form.append('label', templateLabel);
        form.append('file', templateFile);
        start('template');
        router.post(module.template_upload_url, form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                finish('template');
                setTemplateLabel('');
                setTemplateFile(null);
            },
        });
    }

    function deleteTemplate(id: number): void {
        const action = `template-delete:${String(id)}`;
        start(action);
        router.delete(`/uploads/${module.slug}/templates/${String(id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish(action);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{module.label}</h2>}
        >
            <Head title={module.label} />

            <div className="space-y-6">
                {stats !== null && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        {governanceStatDefinitions.map((stat) => {
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

                {(module.templates ?? []).length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Templates and process guides</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 lg:grid-cols-2">
                            {(module.templates ?? []).map((template) => (
                                <div key={template.file} className="kinetic-template-card">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-medium">{template.label}</p>
                                        <Button asChild variant="outline" size="sm">
                                            <a href={template.url} target="_blank" rel="noreferrer">
                                                <Download className="size-4" /> Open
                                            </a>
                                        </Button>
                                        {module.can_manage_templates &&
                                            template.source === 'managed' &&
                                            template.id !== undefined && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Remove ${template.label}`}
                                                    loading={isPending(
                                                        `template-delete:${String(template.id)}`,
                                                    )}
                                                    onClick={() => {
                                                        if (template.id !== undefined) {
                                                            deleteTemplate(template.id);
                                                        }
                                                    }}
                                                    className="text-destructive"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                    </div>
                                    {template.preview && (
                                        <iframe
                                            src={template.url}
                                            title={template.label}
                                            className="mt-3 h-80 w-full rounded-lg border"
                                        />
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {module.can_manage_templates && (
                    <Card className="kinetic-template-card">
                        <CardHeader>
                            <CardTitle>Manage module templates</CardTitle>
                            <p className="text-muted-foreground text-sm">
                                Add a replacement or supplemental guide for this module. Managed
                                files are available to every user with module access.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitTemplate}
                                className="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="template-label">Template label</Label>
                                    <Input
                                        id="template-label"
                                        value={templateLabel}
                                        onChange={(e) => {
                                            setTemplateLabel(e.target.value);
                                        }}
                                        placeholder="e.g. 2026 review form"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="template-file">Template file</Label>
                                    <Input
                                        id="template-file"
                                        type="file"
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                        onChange={(e) => {
                                            setTemplateFile(e.target.files?.[0] ?? null);
                                        }}
                                        required
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    loading={isPending('template')}
                                    loadingText="Saving"
                                    disabled={templateFile === null || templateLabel.trim() === ''}
                                >
                                    <Upload className="size-4" /> Save template
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

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
                                <Button
                                    type="submit"
                                    loading={uploading}
                                    loadingText="Uploading"
                                    disabled={file === null}
                                >
                                    <Upload className="size-4" />
                                    Upload
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
                                                {row.uploader ?? '—'}
                                            </p>
                                        </TableCell>
                                        {module.has_title && (
                                            <TableCell className="text-sm">
                                                {row.title ?? '—'}
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
                                                    {row.status ?? '—'}
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
                                                            loading={isPending(
                                                                `status:${String(row.id)}`,
                                                            )}
                                                            loadingText=""
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
                                                            loading={isPending(
                                                                `status:${String(row.id)}`,
                                                            )}
                                                            loadingText=""
                                                            onClick={() => {
                                                                setStatus(row.id, 'Returned');
                                                            }}
                                                        >
                                                            <RotateCcw className="size-4" />
                                                        </Button>
                                                    </>
                                                )}
                                                {user !== null &&
                                                    (user.role === 'admin' ||
                                                        user.role === 'focal' ||
                                                        row.uploader_id === user.id) && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label="Delete"
                                                            loading={isPending(
                                                                `delete:${String(row.id)}`,
                                                            )}
                                                            loadingText=""
                                                            className="text-destructive hover:text-destructive"
                                                            onClick={() => {
                                                                deleteRow(row.id);
                                                            }}
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    )}
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
                                            Nothing here yet — upload the first {module.singular}.
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
