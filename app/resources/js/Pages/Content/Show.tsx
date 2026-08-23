import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AccessSection } from '@/Pages/Content/components/access-section';
import { CharterSection } from '@/Pages/Content/components/charter-section';
import { ImageSection } from '@/Pages/Content/components/image-section';
import { PathwaySection } from '@/Pages/Content/components/pathway-section';
import type {
    CharterContent,
    ContentPageProps,
    PathwayPanel,
} from '@/Pages/Content/components/types';
import { usePendingAction } from '@/hooks/use-pending-action';

export default function ContentShow({
    page,
    imageUrl,
    structuredContent,
    canManage,
}: ContentPageProps) {
    const [file, setFile] = useState<File | null>(null);
    const [charter, setCharter] = useState<CharterContent>(() =>
        structuredContent !== null && 'vision' in structuredContent
            ? structuredContent
            : { vision: '', mission: '', core_values: [] },
    );
    const [panels, setPanels] = useState<PathwayPanel[]>(() =>
        structuredContent !== null && Array.isArray(structuredContent) ? structuredContent : [],
    );
    const [matrix, setMatrix] = useState(() =>
        JSON.stringify(structuredContent ?? { columns: [], rows: [] }, null, 2),
    );
    const { isPending, start, finish } = usePendingAction();

    function replace(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (file === null) return;
        const form = new FormData();
        form.append('image', file);
        start('replace');
        router.post(`/content/${page.slug}/image`, form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                finish('replace');
                setFile(null);
            },
        });
    }

    function saveStructured(): void {
        start('structured');
        const payload: Record<string, string> =
            page.content_type === 'charter'
                ? {
                      vision: charter.vision,
                      mission: charter.mission,
                      core_values: charter.core_values.join('\n'),
                  }
                : page.content_type === 'pathway'
                  ? { panels: JSON.stringify(panels) }
                  : { matrix };
        router.post(`/content/${page.slug}/structured`, payload, {
            preserveScroll: true,
            onFinish: () => {
                finish('structured');
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{page.title}</h2>}
        >
            <Head title={page.title} />
            <div className="mx-auto max-w-6xl space-y-6">
                {page.content_type === 'image' && (
                    <ImageSection
                        title={page.title}
                        imageUrl={imageUrl}
                        file={file}
                        setFile={setFile}
                        onSubmit={replace}
                        isPending={isPending}
                        canManage={canManage}
                    />
                )}

                {page.content_type === 'charter' && (
                    <CharterSection
                        charter={charter}
                        setCharter={setCharter}
                        onSave={saveStructured}
                        isPending={isPending}
                        canManage={canManage}
                    />
                )}

                {page.content_type === 'pathway' && (
                    <PathwaySection
                        panels={panels}
                        setPanels={setPanels}
                        onSave={saveStructured}
                        isPending={isPending}
                        canManage={canManage}
                    />
                )}

                {page.content_type === 'access' && (
                    <AccessSection
                        structuredContent={structuredContent}
                        matrix={matrix}
                        setMatrix={setMatrix}
                        onSave={saveStructured}
                        isPending={isPending}
                        canManage={canManage}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
