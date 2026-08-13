import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Camera, ImagePlus, Plus, Trash2 } from 'lucide-react';
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
    const [photoFile, setPhotoFile] = useState<File | null>(null);

    function createAlbum(e: { preventDefault(): void }): void {
        e.preventDefault();
        router.post('/gallery/albums', { name, description }, { preserveScroll: true });
        setName('');
        setDescription('');
    }

    function uploadPhoto(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (uploadTarget === null || photoFile === null) return;

        const form = new FormData();
        form.append('photo', photoFile);
        form.append('caption', caption);

        router.post(`/gallery/albums/${String(uploadTarget.id)}/photos`, form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploadTarget(null);
                setCaption('');
                setPhotoFile(null);
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
                                <Button type="submit">
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
                                        <Badge variant="outline" className="shrink-0">
                                            {album.photo_count} photo(s)
                                        </Badge>
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
                                                            aria-label="Delete photo"
                                                            onClick={() => {
                                                                router.delete(
                                                                    `/gallery/photos/${String(photo.id)}`,
                                                                );
                                                            }}
                                                            className="absolute top-1 right-1 rounded bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100"
                                                        >
                                                            <Trash2 className="size-3" />
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
                            <Label htmlFor="photo">Photo (JPG/PNG/WebP, max 10 MB)</Label>
                            <Input
                                id="photo"
                                type="file"
                                accept="image/*"
                                onChange={(e) => {
                                    setPhotoFile(e.target.files?.[0] ?? null);
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
                            <Button type="submit" disabled={photoFile === null}>
                                Upload
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
