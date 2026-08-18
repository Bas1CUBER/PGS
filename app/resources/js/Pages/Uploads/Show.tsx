import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
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
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsStatCard, type PgsStatTone } from '@/components/pgs-stat-card';
import { TableRowActions } from '@/components/table-row-actions';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

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

interface UploadStatusTarget {
    row: UploadRow;
    status: 'Approved' | 'Returned';
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
        upload_base_url: string;
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

    const uploadForm = useForm({ title: '', description: '' });
    const templateForm = useForm({ label: '' });

    const [file, setFile] = useState<File | null>(null);
    const [templateFile, setTemplateFile] = useState<File | null>(null);
    const [templateDialogOpen, setTemplateDialogOpen] = useState(false);
    const [uploadDialogOpen, setUploadDialogOpen] = useState(false);
    const [deleteRowTarget, setDeleteRowTarget] = useState<UploadRow | null>(null);
    const [deleteTemplateTarget, setDeleteTemplateTarget] = useState<{
        id: number;
        label: string;
    } | null>(null);
    const [statusTarget, setStatusTarget] = useState<UploadStatusTarget | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (file === null) return;

        const formData = new FormData();
        formData.append('file', file);
        if (module.has_title) formData.append('title', uploadForm.data.title);
        if (module.has_description) formData.append('description', uploadForm.data.description);

        start('upload');
        uploadForm.post(module.upload_base_url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setUploadDialogOpen(false);
            },
            onFinish: () => {
                finish('upload');
                uploadForm.reset();
                setFile(null);
            },
        });
    }

    function confirmStatus(): void {
        if (statusTarget === null) return;
        const action = `status:${String(statusTarget.row.id)}`;
        start(action);
        router.put(
            `${module.upload_base_url}/${String(statusTarget.row.id)}/status`,
            { status: statusTarget.status },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                    setStatusTarget(null);
                },
            },
        );
    }

    function confirmDeleteRow(): void {
        if (deleteRowTarget === null) return;
        start('delete');
        router.delete(`${module.upload_base_url}/${String(deleteRowTarget.id)}`, {
            onFinish: () => {
                finish('delete');
                setDeleteRowTarget(null);
            },
        });
    }

    function submitTemplate(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (templateFile === null || templateForm.data.label.trim() === '') return;

        const formData = new FormData();
        formData.append('label', templateForm.data.label);
        formData.append('file', templateFile);
        start('template');
        templateForm.post(module.template_upload_url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setTemplateDialogOpen(false);
            },
            onFinish: () => {
                finish('template');
                templateForm.reset();
                setTemplateFile(null);
            },
        });
    }

    function confirmDeleteTemplate(): void {
        if (deleteTemplateTarget === null) return;
        start('template-delete');
        router.delete(`${module.upload_base_url}/templates/${String(deleteTemplateTarget.id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish('template-delete');
                setDeleteTemplateTarget(null);
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
                                                    loading={isPending('template-delete')}
                                                    onClick={() => {
                                                        if (template.id !== undefined) {
                                                            setDeleteTemplateTarget({
                                                                id: template.id,
                                                                label: template.label,
                                                            });
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
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setTemplateDialogOpen(true);
                            }}
                        >
                            <Upload className="size-4" /> Manage module templates
                        </Button>
                    </div>
                )}

                <div className="flex justify-end">
                    <Button
                        type="button"
                        onClick={() => {
                            setUploadDialogOpen(true);
                        }}
                    >
                        <Upload className="size-4" /> Upload {module.singular}
                    </Button>
                </div>

                <Dialog
                    open={templateDialogOpen}
                    onOpenChange={(open) => {
                        setTemplateDialogOpen(open);
                        if (!open) {
                            templateForm.reset();
                            setTemplateFile(null);
                        }
                    }}
                >
                    <DialogContent className="pgs-modal-form-dialog">
                        <DialogHeader>
                            <DialogTitle>Manage module templates</DialogTitle>
                            <DialogDescription>
                                Add a replacement or supplemental guide for this module.
                            </DialogDescription>
                        </DialogHeader>
                        <form
                            onSubmit={submitTemplate}
                            className="pgs-modal-form pgs-modal-form-scroll"
                        >
                            <DialogBody>
                                <div className="pgs-modal-field">
                                    <Label htmlFor="template-label">Template label</Label>
                                    <Input
                                        id="template-label"
                                        value={templateForm.data.label}
                                        onChange={(e) => {
                                            templateForm.setData('label', e.target.value);
                                        }}
                                        placeholder="e.g. 2026 review form"
                                        required
                                    />
                                    {templateForm.errors.label && (
                                        <p className="text-destructive text-sm">
                                            {templateForm.errors.label}
                                        </p>
                                    )}
                                </div>
                                <div className="pgs-modal-field">
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
                            </DialogBody>
                            <DialogFooter className="pgs-modal-footer">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setTemplateDialogOpen(false);
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    loading={templateForm.processing}
                                    loadingText="Saving"
                                    disabled={templateFile === null || templateForm.data.label.trim() === ''}
                                >
                                    <Upload className="size-4" /> Save template
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={uploadDialogOpen}
                    onOpenChange={(open) => {
                        setUploadDialogOpen(open);
                        if (!open) {
                            uploadForm.reset();
                            setFile(null);
                        }
                    }}
                >
                    <DialogContent className="pgs-modal-form-dialog">
                        <DialogHeader>
                            <DialogTitle>Upload {module.singular}</DialogTitle>
                            <DialogDescription>Add a file to this module.</DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submit} className="pgs-modal-form pgs-modal-form-scroll">
                            <DialogBody>
                                {module.has_title && (
                                    <div className="pgs-modal-field">
                                        <Label htmlFor="title">Title</Label>
                                        <Input
                                            id="title"
                                            value={uploadForm.data.title}
                                            onChange={(e) => {
                                                uploadForm.setData('title', e.target.value);
                                            }}
                                            required
                                        />
                                        {uploadForm.errors.title && (
                                            <p className="text-destructive text-sm">
                                                {uploadForm.errors.title}
                                            </p>
                                        )}
                                    </div>
                                )}
                                {module.has_description && (
                                    <div className="pgs-modal-field">
                                        <Label htmlFor="description">Description</Label>
                                        <textarea
                                            id="description"
                                            value={uploadForm.data.description}
                                            onChange={(e) => {
                                                uploadForm.setData('description', e.target.value);
                                            }}
                                            rows={3}
                                            className="pgs-modal-textarea"
                                        />
                                        {uploadForm.errors.description && (
                                            <p className="text-destructive text-sm">
                                                {uploadForm.errors.description}
                                            </p>
                                        )}
                                    </div>
                                )}
                                <div className="pgs-modal-field">
                                    <Label htmlFor="upload-file">File</Label>
                                    <Input
                                        id="upload-file"
                                        type="file"
                                        onChange={(e) => {
                                            setFile(e.target.files?.[0] ?? null);
                                        }}
                                    />
                                </div>
                            </DialogBody>
                            <DialogFooter className="pgs-modal-footer">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setUploadDialogOpen(false);
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    loading={uploadForm.processing}
                                    loadingText="Uploading"
                                    disabled={file === null}
                                >
                                    <Upload className="size-4" /> Upload
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

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
                                            <TableRowActions label={row.original_name}>
                                                <DropdownMenuItem asChild>
                                                    <a
                                                        href={`${module.upload_base_url}/${String(row.id)}/download`}
                                                    >
                                                        <Download className="size-4" /> Download
                                                    </a>
                                                </DropdownMenuItem>
                                                {module.has_status && canReview && (
                                                    <>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            disabled={isPending(
                                                                `status:${String(row.id)}`,
                                                            )}
                                                            onSelect={() => {
                                                                setStatusTarget({
                                                                    row,
                                                                    status: 'Approved',
                                                                });
                                                            }}
                                                        >
                                                            <Check className="size-4" /> Approve
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            disabled={isPending(
                                                                `status:${String(row.id)}`,
                                                            )}
                                                            onSelect={() => {
                                                                setStatusTarget({
                                                                    row,
                                                                    status: 'Returned',
                                                                });
                                                            }}
                                                        >
                                                            <RotateCcw className="size-4" /> Return
                                                        </DropdownMenuItem>
                                                    </>
                                                )}
                                                {user !== null &&
                                                    (user.role === 'admin' ||
                                                        user.role === 'focal' ||
                                                        row.uploader_id === user.id) && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                disabled={isPending('delete')}
                                                                onSelect={() => {
                                                                    setDeleteRowTarget(row);
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

            <PgsConfirmationDialog
                open={statusTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setStatusTarget(null);
                }}
                title={
                    statusTarget?.status === 'Approved'
                        ? `Approve ${module.singular}`
                        : `Return ${module.singular}`
                }
                description={
                    statusTarget?.status === 'Approved'
                        ? `This will mark the ${module.singular} as approved.`
                        : `This will return the ${module.singular} for further work.`
                }
                confirmationTitle={
                    statusTarget?.status === 'Approved' ? 'Confirm approval' : 'Confirm return'
                }
                confirmationDescription={`${statusTarget?.row.original_name ?? 'This file'} will be marked ${statusTarget?.status.toLowerCase() ?? 'updated'}.`}
                onConfirm={confirmStatus}
                loading={
                    statusTarget !== null && isPending(`status:${String(statusTarget.row.id)}`)
                }
                loadingText={statusTarget?.status === 'Approved' ? 'Approving' : 'Returning'}
                confirmText={statusTarget?.status === 'Approved' ? 'Approve' : 'Return'}
                confirmVariant={statusTarget?.status === 'Approved' ? 'default' : 'destructive'}
                kind={statusTarget?.status === 'Approved' ? 'approve' : 'reject'}
            />

            <PgsConfirmationDialog
                open={deleteRowTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteRowTarget(null);
                }}
                title={`Delete ${module.singular}`}
                description={`This action permanently removes the ${module.singular}.`}
                confirmationTitle={`Confirm ${module.singular} deletion`}
                confirmationDescription={`${deleteRowTarget?.original_name ?? 'This file'} will be removed.`}
                onConfirm={confirmDeleteRow}
                loading={isPending('delete')}
                loadingText="Deleting"
            />

            <PgsConfirmationDialog
                open={deleteTemplateTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTemplateTarget(null);
                }}
                title="Delete template"
                description="This action permanently removes the managed template."
                confirmationTitle="Confirm template deletion"
                confirmationDescription={`"${deleteTemplateTarget?.label ?? 'This template'}" will be removed.`}
                onConfirm={confirmDeleteTemplate}
                loading={isPending('template-delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
