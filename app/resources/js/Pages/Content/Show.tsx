import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ImageUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { PageProps } from '@/types';

interface ContentPageProps extends PageProps {
    page: { slug: string; title: string; img_base: string };
    imageUrl: string | null;
    canManage: boolean;
}

export default function ContentShow({ page, imageUrl, canManage }: ContentPageProps) {
    const [file, setFile] = useState<File | null>(null);

    function replace(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (file === null) return;

        const form = new FormData();
        form.append('image', file);

        router.post(`/content/${page.slug}/image`, form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setFile(null);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{page.title}</h2>}
        >
            <Head title={page.title} />

            <div className="mx-auto max-w-4xl space-y-6">
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

                {canManage && (
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
                                <Button type="submit" disabled={file === null}>
                                    <ImageUp className="size-4" />
                                    Upload
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
