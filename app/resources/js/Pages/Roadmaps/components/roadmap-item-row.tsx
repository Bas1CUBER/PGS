import { ArrowDown, ArrowUp, FileText, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { RoadmapItem } from './types';

interface RoadmapItemRowProps {
    item: RoadmapItem;
    isPending: (action: string) => boolean;
    onOpenBuilder: (item: RoadmapItem) => void;
    onReorder: (id: number, direction: 'up' | 'down') => void;
    onDeleteItem: (item: RoadmapItem) => void;
}

export function RoadmapItemRow({
    item,
    isPending,
    onOpenBuilder,
    onReorder,
    onDeleteItem,
}: RoadmapItemRowProps) {
    return (
        <li className="pgs-roadmap-item flex items-center gap-2">
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">
                    {item.sub_letter}. {item.sub_label}
                </p>
                <p className="text-muted-foreground text-xs">
                    <FileText className="mr-1 inline size-3" />
                    {(item.blocks ?? []).length} block(s)
                </p>
            </div>
            <div className="flex shrink-0 gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        onOpenBuilder(item);
                    }}
                >
                    Blocks
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Move up"
                    loading={isPending(`reorder:${String(item.id)}:up`)}
                    loadingText=""
                    onClick={() => {
                        onReorder(item.id, 'up');
                    }}
                >
                    <ArrowUp className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Move down"
                    loading={isPending(
                        `reorder:${String(item.id)}:down`,
                    )}
                    loadingText=""
                    onClick={() => {
                        onReorder(item.id, 'down');
                    }}
                >
                    <ArrowDown className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Delete item"
                    loading={isPending('delete-item')}
                    loadingText=""
                    className="text-destructive hover:text-destructive"
                    onClick={() => {
                        onDeleteItem(item);
                    }}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
        </li>
    );
}
