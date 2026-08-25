import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Megaphone, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import type { PageProps } from '@/types';
import { relativeInternalUrl } from '@/lib/relative-url';
import { usePendingAction } from '@/hooks/use-pending-action';

interface NoticeRow {
    notice_id: number;
    title: string | null;
    description: string | null;
    created_at: string;
    image_url: string | null;
    video_url: string | null;
}

interface NoticesPageProps extends PageProps {
    notices: {
        data: NoticeRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}

interface NoticeFormData {
    title: string;
    description: string;
    image: File | null;
    video: File | null;
}

export default function NoticesIndex({ notices }: NoticesPageProps) {
    const { auth } = usePage().props;
    const user = auth.user;
    const canManage = user !== null && (user.role === 'admin' || user.role === 'focal');

    const createForm = useForm<NoticeFormData>({
        title: '',
        description: '',
        image: null,
        video: null,
    });
    const editForm = useForm<NoticeFormData & { _method: 'put' }>({
        title: '',
        description: '',
        image: null,
        video: null,
        _method: 'put',
    });
    const deleteForm = useForm({});
    const [editing, setEditing] = useState<NoticeRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<NoticeRow | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function createNotice(e: { preventDefault(): void }): void {
        e.preventDefault();
        start('create');
        createForm.post('/notices', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
            },
            onFinish: () => {
                finish('create');
            },
        });
    }

    function saveEdit(): void {
        if (editing === null) return;
        start(`edit:${String(editing.notice_id)}`);
        editForm.post(`/notices/${String(editing.notice_id)}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setEditing(null);
                editForm.reset();
            },
            onFinish: () => {
                finish(`edit:${String(editing.notice_id)}`);
            },
        });
    }

    function deleteNotice(): void {
        if (deleteTarget === null) return;
        start('delete');
        deleteForm.delete(`/notices/${String(deleteTarget.notice_id)}`, {
            onFinish: () => {
                finish('delete');
                setDeleteTarget(null);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Notices</h2>}
        >
            <Head title="Notices" />

            <div className="mx-auto max-w-3xl space-y-6">
                {canManage && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Publish a notice</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={createNotice} className="space-y-3">
                                <div className="space-y-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        value={createForm.data.title}
                                        onChange={(e) => {
                                            createForm.setData('title', e.target.value);
                                        }}
                                        required
                                    />
                                    {createForm.errors.title && (
                                        <p className="text-destructive text-sm">
                                            {createForm.errors.title}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="description">Description</Label>
                                    <textarea
                                        id="description"
                                        value={createForm.data.description}
                                        onChange={(e) => {
                                            createForm.setData('description', e.target.value);
                                        }}
                                        rows={3}
                                        className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                    {createForm.errors.description && (
                                        <p className="text-destructive text-sm">
                                            {createForm.errors.description}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="notice-image">Image</Label>
                                        <Input
                                            id="notice-image"
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => {
                                                createForm.setData(
                                                    'image',
                                                    e.target.files?.[0] ?? null,
                                                );
                                            }}
                                        />
                                        {createForm.errors.image && (
                                            <p className="text-destructive text-sm">
                                                {createForm.errors.image}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="notice-video">MP4 video</Label>
                                        <Input
                                            id="notice-video"
                                            type="file"
                                            accept="video/mp4,video/webm,video/quicktime"
                                            onChange={(e) => {
                                                createForm.setData(
                                                    'video',
                                                    e.target.files?.[0] ?? null,
                                                );
                                            }}
                                        />
                                        {createForm.errors.video && (
                                            <p className="text-destructive text-sm">
                                                {createForm.errors.video}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        loading={isPending('create')}
                                        loadingText="Publishing"
                                    >
                                        <Plus className="size-4" />
                                        Publish
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {notices.data.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center">
                            <Megaphone className="mx-auto mb-2 size-8" />
                            No notices yet.
                        </CardContent>
                    </Card>
                ) : (
                    notices.data.map((notice) => (
                        <Card key={notice.notice_id}>
                            <CardHeader className="flex flex-row items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <CardTitle>{notice.title ?? 'Untitled'}</CardTitle>
                                        <Badge variant="outline" className="text-xs">
                                            {new Date(notice.created_at).toLocaleDateString()}
                                        </Badge>
                                    </div>
                                    {notice.description !== null && (
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {notice.description}
                                        </p>
                                    )}
                                    {(notice.image_url !== null || notice.video_url !== null) && (
                                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                            {notice.image_url !== null && (
                                                <img
                                                    src={notice.image_url}
                                                    alt={notice.title ?? 'Notice image'}
                                                    className="max-h-56 w-full rounded-lg object-cover"
                                                />
                                            )}
                                            {notice.video_url !== null && (
                                                <video
                                                    src={notice.video_url}
                                                    controls
                                                    className="max-h-56 w-full rounded-lg"
                                                />
                                            )}
                                        </div>
                                    )}
                                </div>
                                {canManage && (
                                    <div className="flex shrink-0 gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Edit notice"
                                            onClick={() => {
                                                setEditing(notice);
                                                editForm.setData({
                                                    title: notice.title ?? '',
                                                    description: notice.description ?? '',
                                                    image: null,
                                                    video: null,
                                                    _method: 'put',
                                                });
                                            }}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Delete notice"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => {
                                                setDeleteTarget(notice);
                                            }}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                )}
                            </CardHeader>
                        </Card>
                    ))
                )}

                {notices.links.length > 3 && (
                    <div className="flex justify-center gap-2">
                        {notices.links.map((link, index) => (
                            <span key={index}>
                                {link.url ? (
                                    <Button
                                        asChild
                                        variant={link.active ? 'default' : 'ghost'}
                                        size="sm"
                                    >
                                        <a href={relativeInternalUrl(link.url) ?? '#'}>
                                            {link.label.replace(/&laquo;|&raquo;/g, '')}
                                        </a>
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
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) setEditing(null);
                }}
            >
                <DialogContent className="pgs-modal-form-dialog">
                    <DialogHeader>
                        <DialogTitle>Edit notice</DialogTitle>
                    </DialogHeader>
                    <DialogBody className="space-y-3">
                        <div className="space-y-2">
                            <Label htmlFor="edit-title">Title</Label>
                            <Input
                                id="edit-title"
                                value={editForm.data.title}
                                onChange={(e) => {
                                    editForm.setData('title', e.target.value);
                                }}
                            />
                            {editForm.errors.title && (
                                <p className="text-destructive text-sm">{editForm.errors.title}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-description">Description</Label>
                            <textarea
                                id="edit-description"
                                value={editForm.data.description}
                                onChange={(e) => {
                                    editForm.setData('description', e.target.value);
                                }}
                                rows={3}
                                className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                            />
                            {editForm.errors.description && (
                                <p className="text-destructive text-sm">
                                    {editForm.errors.description}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="edit-notice-image">Replace image</Label>
                                <Input
                                    id="edit-notice-image"
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => {
                                        editForm.setData('image', e.target.files?.[0] ?? null);
                                    }}
                                />
                                {editForm.errors.image && (
                                    <p className="text-destructive text-sm">
                                        {editForm.errors.image}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-notice-video">Replace video</Label>
                                <Input
                                    id="edit-notice-video"
                                    type="file"
                                    accept="video/mp4,video/webm,video/quicktime"
                                    onChange={(e) => {
                                        editForm.setData('video', e.target.files?.[0] ?? null);
                                    }}
                                />
                                {editForm.errors.video && (
                                    <p className="text-destructive text-sm">
                                        {editForm.errors.video}
                                    </p>
                                )}
                            </div>
                        </div>
                    </DialogBody>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setEditing(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={saveEdit}
                            loading={
                                editing !== null &&
                                isPending(`edit:${String(editing.notice_id)}`)
                            }
                            loadingText="Saving"
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete notice"
                description="This action permanently removes the notice."
                confirmationTitle="Confirm notice deletion"
                confirmationDescription={`"${deleteTarget?.title ?? 'This notice'}" will be removed from the workspace.`}
                onConfirm={deleteNotice}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
