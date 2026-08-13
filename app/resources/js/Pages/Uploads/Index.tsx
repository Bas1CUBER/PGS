import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { FolderOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { PageProps } from '@/types';

interface UploadsIndexPageProps extends PageProps {
    modules: Record<
        string,
        {
            label: string;
            table: string;
            has_title: boolean;
            has_description: boolean;
            has_status: boolean;
            singular: string;
        }
    >;
}

export default function UploadsIndex({ modules }: UploadsIndexPageProps) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Uploads</h2>}
        >
            <Head title="Uploads" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="text-muted-foreground flex items-center gap-2">
                    <FolderOpen className="size-5" />
                    <p className="text-sm">Resource and review submissions across modules.</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {Object.entries(modules).map(([slug, module]) => (
                        <Card key={slug}>
                            <CardHeader>
                                <CardTitle>{module.label}</CardTitle>
                                <CardDescription className="capitalize">{slug}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button asChild size="sm">
                                    <Link href={`/uploads/${slug}`}>Open</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
