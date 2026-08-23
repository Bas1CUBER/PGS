import type { PageProps } from '@/types';

export interface Album {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
    photo_count: number;
}

export interface Photo {
    id: number;
    album_id: number;
    caption: string | null;
    uploaded_at: string;
}

export interface GalleryPageProps extends PageProps {
    albums: Album[];
    photos: Record<number, Photo[]>;
}
