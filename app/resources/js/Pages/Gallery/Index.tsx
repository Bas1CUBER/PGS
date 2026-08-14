import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Camera, ImagePlus, LoaderCircle, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';

interface Album {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
    photo_count: number;
}

interface Photo {
    id: number;
    album_id: number;
    caption: string | null;
    uploaded_at: string;
}

interface GalleryPageProps extends PageProps {
    albums: Album[];
    photos: Record<number, Photo[]>;
}

export default function GalleryIndex({ albums, photos }: GalleryPageProps) {
    const { auth } = usePage().props;
    const user = auth.user;
    const canManage = user !== null && (user.role === 'admin' || user.role === 'focal');

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [uploadTarget, setUploadTarget] = useState<Album | null>(null);
    const [caption, setCaption] = useState('');
    const [photoFiles, setPhotoFiles] = useState<File[]>([]);
    const [editingAlbum, setEditingAlbum] = useState<Album | null>(null);
    const [albumEditName, setAlbumEditName] = useState('');
    const [albumEditDescription, setAlbumEditDescription] = useState('');
    const [editingPhoto, setEditingPhoto] = useState<Photo | null>(null);
    const [photoEditCaption, setPhotoEditCaption] = useState('');
    const { isPending, start, finish } = usePendingAction();

    function createAlbum(e: { preventDefault(): void }): void {
        e.preventDefault();
        start('create-album');
        router.post(
            '/gallery/albums',
            {
                name,
                description,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish('create-album');
                },
            },
        );
        setName('');
        setDescription('');
    }

    function uploadPhoto(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (uploadTarget === null || photoFiles.length === 0) return;

        const form = new FormData();
        photoFiles.forEach((photo) => {
            form.append('photos[]', photo);
        });
        form.append('caption', caption);

        start('upload-photo');
        router.post(`/gallery/albums/${String(uploadTarget.id)}/photos`, form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                finish('upload-photo');
                setUploadTarget(null);
                setCaption('');
                setPhotoFiles([]);
            },
        });
    }

    function openAlbumEdit(album: Album): void {
        setEditingAlbum(album);
        setAlbumEditName(album.name);
        setAlbumEditDescription(album.description ?? '');
    }

    function saveAlbum(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (editingAlbum === null) return;
        start(`edit-album:${String(editingAlbum.id)}`);
        router.put(
            `/gallery/albums/${String(editingAlbum.id)}`,
            {
                name: albumEditName,
                description: albumEditDescription,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(`edit-album:${String(editingAlbum.id)}`);
                    setEditingAlbum(null);
                },
            },
        );
    }

    function openPhotoEdit(photo: Photo): void {
        setEditingPhoto(photo);
        setPhotoEditCaption(photo.caption ?? '');
    }

    function savePhoto(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (editingPhoto === null) return;
        start(`edit-photo:${String(editingPhoto.id)}`);
        router.put(
            `/gallery/photos/${String(editingPhoto.id)}`,
            { caption: photoEditCaption },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(`edit-photo:${String(editingPhoto.id)}`);
                    setEditingPhoto(null);
                },
            },
        );
    }

    function deletePhoto(photoId: number): void {
        const action = `delete-photo:${String(photoId)}`;
        start(action);
        router.delete(`/gallery/photos/${String(photoId)}`, {
            onFinish: () => {
                finish(action);
            },
        });
    }

    function deleteAlbum(albumId: number): void {
        const action = `delete-album:${String(albumId)}`;
        start(action);
        router.delete(`/gallery/albums/${String(albumId)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish(action);
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
                    <Card>
                        <CardHeader>
                            <CardTitle>New album</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={createAlbum}
                                className="flex flex-col gap-3 sm:flex-row"
                            >
                                <Input
                                    value={name}
                                    onChange={(e) => {
                                        setName(e.target.value);
                                    }}
                                    placeholder="Album name"
                                    required
                                />
                                <Input
                                    value={description}
                                    onChange={(e) => {
                                        setDescription(e.target.value);
                                    }}
                                    placeholder="Description (optional)"
                                    className="sm:max-w-xs"
                                />
                                <Button
                                    type="submit"
                                    loading={isPending('create-album')}
                                    loadingText="Creating"
                                >
                                    <Plus className="size-4" />
                                    Create
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {albums.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center">
                            <Camera className="mx-auto mb-2 size-8" />
                            No albums yet.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {albums.map((album) => (
                            <Card key={album.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <CardTitle>{album.name}</CardTitle>
                                            <CardDescription>
                                                {album.description ?? ''}
                                            </CardDescription>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline" className="shrink-0">
                                                {album.photo_count} photo(s)
                                            </Badge>
                                            {canManage && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Edit ${album.name}`}
                                                    onClick={() => {
                                                        openAlbumEdit(album);
                                                    }}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                            {canManage && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Delete ${album.name}`}
                                                    className="text-destructive"
                                                    loading={isPending(
                                                        `delete-album:${String(album.id)}`,
                                                    )}
                                                    onClick={() => {
                                                        deleteAlbum(album.id);
                                                    }}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {(photos[album.id] ?? []).length === 0 ? (
                                        <p className="text-muted-foreground text-sm">
                                            No photos yet.
                                        </p>
                                    ) : (
                                        <div className="grid grid-cols-3 gap-2">
                                            {(photos[album.id] ?? []).map((photo) => (
                                                <div key={photo.id} className="group relative">
                                                    <img
                                                        src={`/gallery/photos/${String(photo.id)}/file`}
                                                        alt={photo.caption ?? 'Photo'}
                                                        className="aspect-square w-full rounded-md object-cover"
                                                    />
                                                    {canManage && (
                                                        <button
                                                            type="button"
                                                            className="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100"
                                                            onClick={() => {
                                                                openPhotoEdit(photo);
                                                            }}
                                                        >
                                                            <Pencil className="size-3" />
                                                            <span className="sr-only">
                                                                Edit caption
                                                            </span>
                                                        </button>
                                                    )}
                                                    {canManage && (
                                                        <button
                                                            type="button"
                                                            aria-label="Delete photo"
                                                            disabled={isPending(
                                                                `delete-photo:${String(photo.id)}`,
                                                            )}
                                                            onClick={() => {
                                                                deletePhoto(photo.id);
                                                            }}
                                                            className="absolute top-1 right-1 rounded bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100"
                                                        >
                                                            {isPending(
                                                                `delete-photo:${String(photo.id)}`,
                                                            ) ? (
                                                                <LoaderCircle className="loading-button-spinner size-3" />
                                                            ) : (
                                                                <Trash2 className="size-3" />
                                                            )}
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    {canManage && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setUploadTarget(album);
                                            }}
                                        >
                                            <ImagePlus className="size-4" />
                                            Add photos
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog
                open={uploadTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUploadTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add photos to "{uploadTarget?.name ?? ''}"</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={uploadPhoto} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="caption">Caption (optional)</Label>
                            <Input
                                id="caption"
                                value={caption}
                                onChange={(e) => {
                                    setCaption(e.target.value);
                                }}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="photo">Photos (JPG/PNG/WebP, max 10 MB each)</Label>
                            <Input
                                id="photo"
                                type="file"
                                accept="image/*"
                                multiple
                                onChange={(e) => {
                                    setPhotoFiles(Array.from(e.target.files ?? []));
                                }}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setUploadTarget(null);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                loading={isPending('upload-photo')}
                                loadingText="Uploading"
                                disabled={photoFiles.length === 0}
                            >
                                Upload
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingAlbum !== null}
                onOpenChange={(open) => {
                    if (!open) setEditingAlbum(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit album</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={saveAlbum} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-album-name">Name</Label>
                            <Input
                                id="edit-album-name"
                                value={albumEditName}
                                onChange={(e) => {
                                    setAlbumEditName(e.target.value);
                                }}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-album-description">Description</Label>
                            <Input
                                id="edit-album-description"
                                value={albumEditDescription}
                                onChange={(e) => {
                                    setAlbumEditDescription(e.target.value);
                                }}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                loading={
                                    editingAlbum !== null &&
                                    isPending(`edit-album:${String(editingAlbum.id)}`)
                                }
                                loadingText="Saving"
                            >
                                <Save className="size-4" /> Save album
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingPhoto !== null}
                onOpenChange={(open) => {
                    if (!open) setEditingPhoto(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit photo caption</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={savePhoto} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-photo-caption">Caption</Label>
                            <Input
                                id="edit-photo-caption"
                                value={photoEditCaption}
                                onChange={(e) => {
                                    setPhotoEditCaption(e.target.value);
                                }}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                loading={
                                    editingPhoto !== null &&
                                    isPending(`edit-photo:${String(editingPhoto.id)}`)
                                }
                                loadingText="Saving"
                            >
                                <Save className="size-4" /> Save caption
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
