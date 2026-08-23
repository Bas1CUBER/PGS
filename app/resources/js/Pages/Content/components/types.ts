import type { PageProps } from '@/types';

export interface PathwayPanel {
    type: string;
    text: string;
    image: string;
    title: string;
    status: string;
}

export interface AccessMatrix {
    columns: string[];
    rows: Record<string, string>[];
}

export interface CharterContent {
    vision: string;
    mission: string;
    core_values: string[];
}

export interface ContentPageProps extends PageProps {
    page: {
        slug: string;
        title: string;
        img_base: string;
        content_type: 'image' | 'pathway' | 'charter' | 'access';
    };
    imageUrl: string | null;
    structuredContent: PathwayPanel[] | CharterContent | AccessMatrix | null;
    canManage: boolean;
}
