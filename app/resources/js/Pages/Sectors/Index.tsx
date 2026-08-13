import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Layers } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { PageProps } from '@/types';

interface SectorModulesPageProps extends PageProps {
    modules: Record<
        string,
        { label: string; table: string; progress_table: string; schedule_table: string | null }
    >;
}

export default function SectorsIndex({ modules }: SectorModulesPageProps) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Sector Roadmaps</h2>}
        >
            <Head title="Sector Roadmaps" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="text-muted-foreground flex items-center gap-2">
                    <Layers className="size-5" />
                    <p className="text-sm">
                        Thematic roadmap pillars — indicator tables, progress tracking and
                        schedules.
                    </p>
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
                                    <Link href={`/sectors/${slug}`}>Open</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
