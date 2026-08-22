import type { PageProps } from '@/types';

export interface UploadRow {
    id: number;
    title: string | null;
    description: string | null;
    filename: string;
    original_name: string;
    file_size: number;
    status: string | null;
    uploaded_at: string;
    uploader: string | null;
    uploader_id: number;
}

export interface ModuleTemplate {
    label: string;
    file: string;
    preview: boolean;
    url: string;
    source?: 'static' | 'managed';
    id?: number;
}

export interface UploadStatusTarget {
    row: UploadRow;
    status: 'Approved' | 'Returned';
}

export interface DeleteTemplateTarget {
    id: number;
    label: string;
}

export interface UploadsShowPageProps extends PageProps {
    module: {
        slug: string;
        label: string;
        table: string;
        has_title: boolean;
        has_description: boolean;
        has_status: boolean;
        status_values: string[] | null;
        uploader_fk: string;
        uploader_label: string;
        singular: string;
        templates?: ModuleTemplate[];
        upload_base_url: string;
        template_upload_url: string;
        can_manage_templates: boolean;
    };
    rows: UploadRow[];
    filters: { status: string };
    stats: {
        total: number;
        pdf: number;
        image: number;
        approved: number;
        in_progress: number;
        returned: number;
    } | null;
}

export type GovernanceStats = NonNullable<UploadsShowPageProps['stats']>;

export type GovernanceStatKey = keyof GovernanceStats;
