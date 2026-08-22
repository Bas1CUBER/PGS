import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RoadmapItemRow } from './roadmap-item-row';
import type { RoadmapItem, RoadmapTitleRow } from './types';

interface RoadmapSectionCardProps {
    title: RoadmapTitleRow;
    isPending: (action: string) => boolean;
    onOpenBuilder: (item: RoadmapItem) => void;
    onReorder: (id: number, direction: 'up' | 'down') => void;
    onDeleteItem: (item: RoadmapItem) => void;
    onDeleteSection: (title: RoadmapTitleRow) => void;
    onAddItem: (titleId: number) => void;
}

export function RoadmapSectionCard({
    title,
    isPending,
    onOpenBuilder,
    onReorder,
    onDeleteItem,
    onDeleteSection,
    onAddItem,
}: RoadmapSectionCardProps) {
    return (
        <Card className="pgs-roadmap-section-card">
            <CardHeader className="pgs-roadmap-section-header flex flex-row items-center justify-between">
                <CardTitle>{title.title}</CardTitle>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-destructive hover:text-destructive"
                    onClick={() => {
                        onDeleteSection(title);
                    }}
                >
                    <Trash2 className="size-4" />
                    Delete
                </Button>
            </CardHeader>
            <CardContent className="pgs-roadmap-section-content space-y-3">
                <ul className="space-y-2">
                    {title.items.map((item) => (
                        <RoadmapItemRow
                            key={item.id}
                            item={item}
                            isPending={isPending}
                            onOpenBuilder={onOpenBuilder}
                            onReorder={onReorder}
                            onDeleteItem={onDeleteItem}
                        />
                    ))}
                    {title.items.length === 0 && (
                        <li className="text-muted-foreground py-2 text-sm">
                            No items in this section yet.
                        </li>
                    )}
                </ul>

                <div className="flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            onAddItem(title.id);
                        }}
                    >
                        <Plus className="size-4" /> Add item
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
