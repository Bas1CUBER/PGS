import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePendingAction } from '@/hooks/use-pending-action';
import { AlbumsGrid } from './components/albums-grid';
import { CreateAlbumDialog } from './components/create-album-dialog';
import { DeleteAlbumDialog } from './components/delete-album-dialog';
import { DeletePhotoDialog } from './components/delete-photo-dialog';
import { EditAlbumDialog } from './components/edit-album-dialog';
import { EditPhotoCaptionDialog } from './components/edit-photo-caption-dialog';
import { UploadPhotosDialog } from './components/upload-photos-dialog';
import type { Album, GalleryPageProps, Photo } from './components/types';

export default function GalleryIndex({ albums, photos }: GalleryPageProps) {
    const { auth } = usePage().props;
    const user = auth.user;
    const canManage = user !== null && (user.role === 'admin' || user.role === 'focal');

    const albumForm = useForm({ name: '', description: '' });
    const albumEditForm = useForm({ name: '', description: '' });
    const photoForm = useForm({ caption: '' });
    const photoEditForm = useForm({ caption: '' });

    const [albumDialogOpen, setAlbumDialogOpen] = useState(false);
    const [uploadTarget, setUploadTarget] = useState<Album | null>(null);
    const [photoFiles, setPhotoFiles] = useState<File[]>([]);
    const [editingAlbum, setEditingAlbum] = useState<Album | null>(null);
    const [editingPhoto, setEditingPhoto] = useState<Photo | null>(null);
    const [deleteAlbumTarget, setDeleteAlbumTarget] = useState<Album | null>(null);
    const [deletePhotoTarget, setDeletePhotoTarget] = useState<Photo | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function createAlbum(e: { preventDefault(): void }): void {
        e.preventDefault();
        start('create-album');
        albumForm.post('/gallery/albums', {
            preserveScroll: true,
            onSuccess: () => {
                setAlbumDialogOpen(false);
                albumForm.reset();
            },
            onFinish: () => {
                finish('create-album');
            },
        });
    }

    function uploadPhoto(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (uploadTarget === null || photoFiles.length === 0) return;

        const formData = new FormData();
        photoFiles.forEach((photo) => {
            formData.append('photos[]', photo);
        });
        photoForm.setData('caption', photoForm.data.caption);

        start('upload-photo');
        photoForm.post(`/gallery/albums/${String(uploadTarget.id)}/photos`, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                finish('upload-photo');
                setUploadTarget(null);
                photoForm.reset();
                setPhotoFiles([]);
            },
        });
    }

    function openAlbumEdit(album: Album): void {
        setEditingAlbum(album);
        albumEditForm.setData({ name: album.name, description: album.description ?? '' });
    }

    function saveAlbum(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (editingAlbum === null) return;
        start(`edit-album:${String(editingAlbum.id)}`);
        albumEditForm.put(`/gallery/albums/${String(editingAlbum.id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish(`edit-album:${String(editingAlbum.id)}`);
                setEditingAlbum(null);
            },
        });
    }

    function openPhotoEdit(photo: Photo): void {
        setEditingPhoto(photo);
        photoEditForm.setData({ caption: photo.caption ?? '' });
    }

    function savePhoto(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (editingPhoto === null) return;
        start(`edit-photo:${String(editingPhoto.id)}`);
        photoEditForm.put(`/gallery/photos/${String(editingPhoto.id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish(`edit-photo:${String(editingPhoto.id)}`);
                setEditingPhoto(null);
            },
        });
    }

    function confirmDeletePhoto(): void {
        if (deletePhotoTarget === null) return;
        start('delete-photo');
        router.delete(`/gallery/photos/${String(deletePhotoTarget.id)}`, {
            onFinish: () => {
                finish('delete-photo');
                setDeletePhotoTarget(null);
            },
        });
    }

    function confirmDeleteAlbum(): void {
        if (deleteAlbumTarget === null) return;
        start('delete-album');
        router.delete(`/gallery/albums/${String(deleteAlbumTarget.id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish('delete-album');
                setDeleteAlbumTarget(null);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Gallery</h2>}
        >
            <Head title="Gallery" />

            <div className="space-y-6">
                {canManage && (
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            onClick={() => {
                                setAlbumDialogOpen(true);
                            }}
                        >
                            <Plus className="size-4" /> New album
                        </Button>
                    </div>
                )}

                <AlbumsGrid
                    albums={albums}
                    photos={photos}
                    canManage={canManage}
                    albumDeletePending={isPending('delete-album')}
                    photoDeletePending={isPending('delete-photo')}
                    onEditAlbum={openAlbumEdit}
                    onDeleteAlbum={setDeleteAlbumTarget}
                    onEditPhoto={openPhotoEdit}
                    onDeletePhoto={setDeletePhotoTarget}
                    onUploadPhotos={setUploadTarget}
                />
            </div>

            <CreateAlbumDialog
                open={albumDialogOpen}
                form={albumForm}
                onOpenChange={(open) => {
                    setAlbumDialogOpen(open);
                    if (!open) {
                        albumForm.reset();
                    }
                }}
                onClose={() => {
                    setAlbumDialogOpen(false);
                }}
                onSubmit={createAlbum}
            />

            <UploadPhotosDialog
                open={uploadTarget !== null}
                targetName={uploadTarget?.name ?? ''}
                form={photoForm}
                photoFiles={photoFiles}
                onPhotoFilesChange={setPhotoFiles}
                onOpenChange={(open) => {
                    if (!open) {
                        setUploadTarget(null);
                    }
                }}
                onClose={() => {
                    setUploadTarget(null);
                }}
                onSubmit={uploadPhoto}
            />

            <EditAlbumDialog
                open={editingAlbum !== null}
                form={albumEditForm}
                onOpenChange={(open) => {
                    if (!open) setEditingAlbum(null);
                }}
                onSubmit={saveAlbum}
            />

            <EditPhotoCaptionDialog
                open={editingPhoto !== null}
                form={photoEditForm}
                onOpenChange={(open) => {
                    if (!open) setEditingPhoto(null);
                }}
                onSubmit={savePhoto}
            />

            <DeleteAlbumDialog
                target={deleteAlbumTarget}
                onOpenChange={(open) => {
                    if (!open) setDeleteAlbumTarget(null);
                }}
                onConfirm={confirmDeleteAlbum}
                loading={isPending('delete-album')}
            />

            <DeletePhotoDialog
                target={deletePhotoTarget}
                onOpenChange={(open) => {
                    if (!open) setDeletePhotoTarget(null);
                }}
                onConfirm={confirmDeletePhoto}
                loading={isPending('delete-photo')}
            />
        </AuthenticatedLayout>
    );
}
