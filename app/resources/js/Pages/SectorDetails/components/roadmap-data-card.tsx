import { Download, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';

interface RoadmapDataCardProps {
    onCreateClick: () => void;
    exportHref: string;
}

export function RoadmapDataCard({ onCreateClick, exportHref }: RoadmapDataCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>Roadmap data</CardTitle>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Manage records in this detailed roadmap table.
                    </p>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        onClick={() => {
                            onCreateClick();
                        }}
                    >
                        <Plus className="size-4" /> Add row
                    </Button>
                    <Button asChild variant="outline">
                        <a href={exportHref}>
                            <Download className="size-4" /> Export CSV
                        </a>
                    </Button>
                </div>
            </CardHeader>
        </Card>
    );
}
