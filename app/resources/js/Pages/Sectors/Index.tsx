import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Layers } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { PageProps } from '@/types';
import { legacyImageUrl } from '@/lib/legacy-asset';

interface SectorModulesPageProps extends PageProps {
    modules: Record<
        string,
        {
            label: string;
            logo: string;
            table: string;
            progress_table: string;
            schedule_table: string | null;
        }
    >;
}

export default function SectorsIndex({ modules }: SectorModulesPageProps) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Sector Roadmaps</h2>}
        >
            <Head title="Sector Roadmaps" />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="text-muted-foreground flex items-center gap-2">
                    <Layers className="size-5" />
                    <p className="text-sm">
                        Thematic roadmap pillars — indicator tables, progress tracking and
                        schedules.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {Object.entries(modules).map(([slug, module]) => (
                        <Card key={slug} className="pgs-sector-card">
                            <CardHeader className="flex flex-col items-center gap-4 p-5 text-center">
                                <div className="pgs-sector-card-logo" aria-hidden="true">
                                    <img src={legacyImageUrl(module.logo)} alt="" />
                                </div>
                                <div className="min-w-0">
                                    <CardTitle className="pgs-sector-card-title">
                                        {module.label}
                                    </CardTitle>
                                    <CardDescription className="pgs-sector-card-description capitalize">
                                        {slug}
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="mt-auto w-full p-5 pt-0 text-center">
                                <Button asChild size="sm" className="w-full">
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
