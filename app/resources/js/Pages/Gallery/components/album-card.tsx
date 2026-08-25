import { ImagePlus, LoaderCircle, Pencil, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { Album, Photo } from './types';

interface AlbumCardProps {
    album: Album;
    photos: Photo[];
    canManage: boolean;
    albumDeletePending: boolean;
    photoDeletePending: boolean;
    onEditAlbum: (album: Album) => void;
    onDeleteAlbum: (album: Album) => void;
    onEditPhoto: (photo: Photo) => void;
    onDeletePhoto: (photo: Photo) => void;
    onUploadPhotos: (album: Album) => void;
}

export function AlbumCard({
    album,
    photos,
    canManage,
    albumDeletePending,
    photoDeletePending,
    onEditAlbum,
    onDeleteAlbum,
    onEditPhoto,
    onDeletePhoto,
    onUploadPhotos,
}: AlbumCardProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle>{album.name}</CardTitle>
                        <CardDescription>{album.description ?? ''}</CardDescription>
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
                                    onEditAlbum(album);
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
                                loading={albumDeletePending}
                                onClick={() => {
                                    onDeleteAlbum(album);
                                }}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {photos.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No photos yet.</p>
                ) : (
                    <div className="grid grid-cols-3 gap-2">
                        {photos.map((photo) => (
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
                                            onEditPhoto(photo);
                                        }}
                                    >
                                        <Pencil className="size-3" />
                                        <span className="sr-only">Edit caption</span>
                                    </button>
                                )}
                                {canManage && (
                                    <button
                                        type="button"
                                        aria-label="Delete photo"
                                        disabled={photoDeletePending}
                                        onClick={() => {
                                            onDeletePhoto(photo);
                                        }}
                                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90 absolute top-1 right-1 rounded p-1 opacity-0 transition group-hover:opacity-100"
                                    >
                                        {photoDeletePending ? (
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
                            onUploadPhotos(album);
                        }}
                    >
                        <ImagePlus className="size-4" />
                        Add photos
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
