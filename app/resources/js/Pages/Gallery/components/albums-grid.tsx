import { Camera } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { AlbumCard } from './album-card';
import type { Album, Photo } from './types';

interface AlbumsGridProps {
    albums: Album[];
    photos: Record<number, Photo[]>;
    canManage: boolean;
    albumDeletePending: boolean;
    photoDeletePending: boolean;
    onEditAlbum: (album: Album) => void;
    onDeleteAlbum: (album: Album) => void;
    onEditPhoto: (photo: Photo) => void;
    onDeletePhoto: (photo: Photo) => void;
    onUploadPhotos: (album: Album) => void;
}

export function AlbumsGrid({
    albums,
    photos,
    canManage,
    albumDeletePending,
    photoDeletePending,
    onEditAlbum,
    onDeleteAlbum,
    onEditPhoto,
    onDeletePhoto,
    onUploadPhotos,
}: AlbumsGridProps) {
    if (albums.length === 0) {
        return (
            <Card>
                <CardContent className="text-muted-foreground py-10 text-center">
                    <Camera className="mx-auto mb-2 size-8" />
                    No albums yet.
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {albums.map((album) => (
                <AlbumCard
                    key={album.id}
                    album={album}
                    photos={photos[album.id] ?? []}
                    canManage={canManage}
                    albumDeletePending={albumDeletePending}
                    photoDeletePending={photoDeletePending}
                    onEditAlbum={onEditAlbum}
                    onDeleteAlbum={onDeleteAlbum}
                    onEditPhoto={onEditPhoto}
                    onDeletePhoto={onDeletePhoto}
                    onUploadPhotos={onUploadPhotos}
                />
            ))}
        </div>
    );
}
