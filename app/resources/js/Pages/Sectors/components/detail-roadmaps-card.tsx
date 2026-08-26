import { Link } from '@inertiajs/react';
import { Table2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { urls } from '@/lib/urls';
import type { SectorDetailLink } from './types';

interface DetailRoadmapsCardProps {
    moduleSlug: string;
    details: SectorDetailLink[];
}

export function DetailRoadmapsCard({ moduleSlug, details }: DetailRoadmapsCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Table2 className="size-4" />
                    Detail roadmaps
                </CardTitle>
            </CardHeader>
            <CardContent>
                {details.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No detail roadmaps for this pillar yet.
                    </p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {details.map((detail) => (
                            <Button key={detail.slug} asChild variant="outline" size="sm">
                                <Link href={urls.sectorDetails(moduleSlug, detail.slug)}>
                                    {detail.label}
                                </Link>
                            </Button>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
