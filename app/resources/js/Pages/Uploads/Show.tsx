import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import { StatsGrid } from './components/stats-grid';
import { TemplatesCard } from './components/templates-card';
import { TemplateDialog } from './components/template-dialog';
import { UploadDialog } from './components/upload-dialog';
import { UploadsTable } from './components/uploads-table';
import type {
    DeleteTemplateTarget,
    ModuleTemplate,
    UploadRow,
    UploadStatusTarget,
    UploadsShowPageProps,
} from './components/types';

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
    const [deleteTemplateTarget, setDeleteTemplateTarget] =
        useState<DeleteTemplateTarget | null>(null);
    const [statusTarget, setStatusTarget] = useState<UploadStatusTarget | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function submit(event: { preventDefault(): void }): void {
        event.preventDefault();
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

    function submitTemplate(event: { preventDefault(): void }): void {
        event.preventDefault();
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

    function handleUploadDialogChange(open: boolean): void {
        setUploadDialogOpen(open);
        if (!open) {
            uploadForm.reset();
            setFile(null);
        }
    }

    function handleTemplateDialogChange(open: boolean): void {
        setTemplateDialogOpen(open);
        if (!open) {
            templateForm.reset();
            setTemplateFile(null);
        }
    }

    function handleRemoveTemplate(template: ModuleTemplate): void {
        if (template.id !== undefined) {
            setDeleteTemplateTarget({ id: template.id, label: template.label });
        }
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{module.label}</h2>}
        >
            <Head title={module.label} />

            <div className="space-y-6">
                {stats !== null && <StatsGrid stats={stats} />}

                <TemplatesCard
                    templates={module.templates ?? []}
                    canManageTemplates={module.can_manage_templates}
                    isTemplateDeletePending={isPending('template-delete')}
                    onRemoveTemplate={handleRemoveTemplate}
                />

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

                <TemplateDialog
                    open={templateDialogOpen}
                    form={templateForm}
                    file={templateFile}
                    onOpenChange={handleTemplateDialogChange}
                    onClose={() => {
                        setTemplateDialogOpen(false);
                    }}
                    onFileChange={setTemplateFile}
                    onSubmit={submitTemplate}
                />

                <UploadDialog
                    open={uploadDialogOpen}
                    form={uploadForm}
                    file={file}
                    singular={module.singular}
                    hasTitle={module.has_title}
                    hasDescription={module.has_description}
                    onOpenChange={handleUploadDialogChange}
                    onClose={() => {
                        setUploadDialogOpen(false);
                    }}
                    onFileChange={setFile}
                    onSubmit={submit}
                />

                <UploadsTable
                    rows={rows}
                    module={module}
                    user={user}
                    canReview={canReview}
                    isPending={isPending}
                    onSelectStatus={setStatusTarget}
                    onDeleteRow={setDeleteRowTarget}
                />
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
