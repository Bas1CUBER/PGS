import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Megaphone, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

interface NoticeRow {
    notice_id: number;
    title: string | null;
    description: string | null;
    created_at: string;
}

interface NoticesPageProps extends PageProps {
    notices: {
        data: NoticeRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}

export default function NoticesIndex({ notices }: NoticesPageProps) {
    const { auth } = usePage().props;
    const user = auth.user;
    const canManage = user !== null && (user.role === 'admin' || user.role === 'focal');

    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [editing, setEditing] = useState<NoticeRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<NoticeRow | null>(null);

    function createNotice(e: { preventDefault(): void }): void {
        e.preventDefault();
        router.post('/notices', { title, description }, { preserveScroll: true });
        setTitle('');
        setDescription('');
    }

    function saveEdit(): void {
        if (editing === null) return;
        router.put(
            `/notices/${String(editing.notice_id)}`,
            { title, description },
            { preserveScroll: true },
        );
        setEditing(null);
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
                                        value={title}
                                        onChange={(e) => {
                                            setTitle(e.target.value);
                                        }}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="description">Description</Label>
                                    <textarea
                                        id="description"
                                        value={description}
                                        onChange={(e) => {
                                            setDescription(e.target.value);
                                        }}
                                        rows={3}
                                        className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="flex justify-end">
                                    <Button type="submit" size="sm">
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
                                </div>
                                {canManage && (
                                    <div className="flex shrink-0 gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Edit notice"
                                            onClick={() => {
                                                setEditing(notice);
                                                setTitle(notice.title ?? '');
                                                setDescription(notice.description ?? '');
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
                                        <a href={link.url}>
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
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit notice</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div className="space-y-2">
                            <Label htmlFor="edit-title">Title</Label>
                            <Input
                                id="edit-title"
                                value={title}
                                onChange={(e) => {
                                    setTitle(e.target.value);
                                }}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-description">Description</Label>
                            <textarea
                                id="edit-description"
                                value={description}
                                onChange={(e) => {
                                    setDescription(e.target.value);
                                }}
                                rows={3}
                                className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setEditing(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button onClick={saveEdit}>Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete notice</DialogTitle>
                        <p className="text-muted-foreground text-sm">
                            Delete "{deleteTarget?.title ?? ''}"? This cannot be undone.
                        </p>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setDeleteTarget(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (deleteTarget !== null) {
                                    router.delete(`/notices/${String(deleteTarget.notice_id)}`);
                                }
                                setDeleteTarget(null);
                            }}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
