import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ImageUp, Pencil, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePendingAction } from '@/hooks/use-pending-action';
import type { PageProps } from '@/types';

interface PathwayPanel {
    type: string;
    text: string;
    image: string;
    title: string;
    status: string;
}
interface AccessMatrix {
    columns: string[];
    rows: Record<string, string>[];
}
interface CharterContent {
    vision: string;
    mission: string;
    core_values: string[];
}
interface ContentPageProps extends PageProps {
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
                    <Card>
                        <CardHeader>
                            <CardTitle>{page.title}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {imageUrl !== null ? (
                                <img
                                    src={imageUrl}
                                    alt={page.title}
                                    className="mx-auto max-h-[70vh] w-auto rounded-lg border"
                                />
                            ) : (
                                <p className="text-muted-foreground py-10 text-center">
                                    Image not found.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {page.content_type === 'charter' && (
                    <>
                        <div className="grid gap-4 lg:grid-cols-3">
                            <ContentPanel title="Vision">{charter.vision}</ContentPanel>
                            <ContentPanel title="Mission">{charter.mission}</ContentPanel>
                            <ContentPanel title="Core values">
                                <div className="space-y-2">
                                    {charter.core_values.map((value) => (
                                        <div key={value} className="pgs-content-chip">
                                            {value}
                                        </div>
                                    ))}
                                </div>
                            </ContentPanel>
                        </div>
                        {canManage && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Edit charter statements</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="vision">Vision</Label>
                                        <textarea
                                            id="vision"
                                            value={charter.vision}
                                            onChange={(e) => {
                                                setCharter({ ...charter, vision: e.target.value });
                                            }}
                                            rows={4}
                                            className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="mission">Mission</Label>
                                        <textarea
                                            id="mission"
                                            value={charter.mission}
                                            onChange={(e) => {
                                                setCharter({ ...charter, mission: e.target.value });
                                            }}
                                            rows={4}
                                            className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="core-values">Core values</Label>
                                        <textarea
                                            id="core-values"
                                            value={charter.core_values.join('\n')}
                                            onChange={(e) => {
                                                setCharter({
                                                    ...charter,
                                                    core_values: e.target.value
                                                        .split(/\r?\n/)
                                                        .filter(Boolean),
                                                });
                                            }}
                                            rows={4}
                                            className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            Enter one value per line.
                                        </p>
                                    </div>
                                    <Button
                                        onClick={saveStructured}
                                        loading={isPending('structured')}
                                        loadingText="Saving"
                                    >
                                        <Save className="size-4" /> Save statements
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}

                {page.content_type === 'pathway' && (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            {panels.map((panel, index) => (
                                <Card key={`${panel.title}-${String(index)}`}>
                                    <CardHeader>
                                        <CardTitle>
                                            {panel.title || `Panel ${String(index + 1)}`}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {panel.image && (
                                            <img
                                                src={`/legacy-img/${encodeURIComponent(panel.image)}`}
                                                alt={panel.title}
                                                className="max-h-72 w-full rounded-lg object-contain"
                                            />
                                        )}
                                        <p className="text-muted-foreground text-sm whitespace-pre-wrap">
                                            {panel.text || 'No panel content yet.'}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                        {canManage && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Edit pathway panels</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {panels.map((panel, index) => (
                                        <div
                                            key={`edit-${String(index)}`}
                                            className="grid gap-3 rounded-xl border p-4 md:grid-cols-2"
                                        >
                                            <div className="space-y-2">
                                                <Label>Panel title</Label>
                                                <Input
                                                    value={panel.title}
                                                    onChange={(e) => {
                                                        updatePanel(index, 'title', e.target.value);
                                                    }}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Status</Label>
                                                <Input
                                                    value={panel.status}
                                                    onChange={(e) => {
                                                        updatePanel(
                                                            index,
                                                            'status',
                                                            e.target.value,
                                                        );
                                                    }}
                                                />
                                            </div>
                                            <div className="space-y-2 md:col-span-2">
                                                <Label>Text</Label>
                                                <textarea
                                                    value={panel.text}
                                                    onChange={(e) => {
                                                        updatePanel(index, 'text', e.target.value);
                                                    }}
                                                    rows={3}
                                                    className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                                                />
                                            </div>
                                        </div>
                                    ))}
                                    <Button
                                        onClick={saveStructured}
                                        loading={isPending('structured')}
                                        loadingText="Saving"
                                    >
                                        <Save className="size-4" /> Save panels
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}

                {page.content_type === 'access' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>User access matrix</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            {structuredContent !== null && typeof structuredContent === 'object' && !Array.isArray(structuredContent) && 'columns' in structuredContent && 'rows' in structuredContent ? (
                                <AccessTable matrix={structuredContent as AccessMatrix} />
                            ) : (
                                <p className="text-muted-foreground text-sm">Access matrix not available.</p>
                            )}
                            {canManage && (
                                <div className="mt-6 space-y-3">
                                    <div className="flex items-center gap-2">
                                        <Pencil className="size-4" />
                                        <p className="font-medium">Edit matrix JSON</p>
                                    </div>
                                    <textarea
                                        value={matrix}
                                        onChange={(e) => {
                                            setMatrix(e.target.value);
                                        }}
                                        rows={18}
                                        className="border-input bg-background flex w-full min-w-[680px] rounded-md border px-3 py-2 font-mono text-xs"
                                    />
                                    <Button
                                        onClick={saveStructured}
                                        loading={isPending('structured')}
                                        loadingText="Saving"
                                    >
                                        <Save className="size-4" /> Save matrix
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {page.content_type === 'image' && canManage && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Replace image</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={replace}
                                className="flex flex-col gap-3 sm:flex-row sm:items-center"
                            >
                                <Input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => {
                                        setFile(e.target.files?.[0] ?? null);
                                    }}
                                    className="max-w-md"
                                />
                                <Button
                                    type="submit"
                                    loading={isPending('replace')}
                                    loadingText="Uploading"
                                    disabled={file === null}
                                >
                                    <ImageUp className="size-4" /> Upload
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );

    function updatePanel(index: number, key: 'title' | 'status' | 'text', value: string): void {
        setPanels((current) =>
            current.map((panel, panelIndex) =>
                panelIndex === index ? { ...panel, [key]: value } : panel,
            ),
        );
    }
}

function ContentPanel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card className="h-full">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="text-muted-foreground min-h-40 text-sm leading-7 whitespace-pre-wrap">
                {children}
            </CardContent>
        </Card>
    );
}

function AccessTable({ matrix }: { matrix: AccessMatrix }) {
    if (!Array.isArray(matrix.columns))
        return <p className="text-muted-foreground">No access matrix configured.</p>;
    return (
        <div data-slot="table-container" className="relative w-full overflow-x-auto">
            <table data-slot="table" className="w-full min-w-[680px] text-left text-sm">
                <thead data-slot="table-header">
                    <tr data-slot="table-row">
                        {matrix.columns.map((column) => (
                            <th
                                key={column}
                                data-slot="table-head"
                                className="border-b px-3 py-3 font-semibold"
                            >
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody data-slot="table-body">
                    {matrix.rows.map((row, index) =>
                        row.section ? (
                            <tr key={`section-${String(index)}`} data-slot="table-row">
                                <th
                                    colSpan={matrix.columns.length}
                                    data-slot="table-head"
                                    className="bg-muted/50 px-3 py-3 text-xs font-semibold tracking-wider uppercase"
                                >
                                    {row.section}
                                </th>
                            </tr>
                        ) : (
                            <tr
                                key={`row-${String(index)}`}
                                data-slot="table-row"
                                className="border-b last:border-0"
                            >
                                {matrix.columns.map((column) => (
                                    <td
                                        key={column}
                                        data-slot="table-cell"
                                        className="px-3 py-3 align-top"
                                    >
                                        {row[column] ?? ''}
                                    </td>
                                ))}
                            </tr>
                        ),
                    )}
                </tbody>
            </table>
        </div>
    );
}
