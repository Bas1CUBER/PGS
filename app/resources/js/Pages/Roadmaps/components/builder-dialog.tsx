import type { InertiaFormProps } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { RoadmapAddBlockForm } from './roadmap-add-block-form';
import { RoadmapBlockPreview } from './roadmap-block-preview';
import { RoadmapStatPreview } from './roadmap-stat-preview';
import type { RoadmapBlock, RoadmapItem } from './types';

interface BuilderDialogProps {
    item: RoadmapItem | null;
    form: InertiaFormProps<{ block_type: string; content: string }>;
    isPending: (action: string) => boolean;
    onUpdateBlock: (block: RoadmapBlock) => void;
    onDeleteBlock: (block: RoadmapBlock) => void;
    onAddBlock: () => void;
    onOpenChange: (open: boolean) => void;
}

export function BuilderDialog({
    item,
    form,
    isPending,
    onUpdateBlock,
    onDeleteBlock,
    onAddBlock,
    onOpenChange,
}: BuilderDialogProps) {
    return (
        <Dialog open={item !== null} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog pgs-modal-wide">
                <DialogHeader>
                    <DialogTitle>Page builder — {item?.sub_label ?? ''}</DialogTitle>
                </DialogHeader>

                <DialogBody className="space-y-4">
                    {(item?.blocks ?? []).some(
                        (block) => block.block_type === 'dashboard_stat',
                    ) && (
                        <div className="space-y-3">
                            <div>
                                <p className="text-sm font-medium">Stat card preview</p>
                                <p className="text-muted-foreground text-xs">
                                    These cards are rendered from the saved dashboard stat
                                    blocks.
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {(item?.blocks ?? [])
                                    .filter((block) => block.block_type === 'dashboard_stat')
                                    .map((block) => (
                                        <RoadmapStatPreview key={block.id} block={block} />
                                    ))}
                            </div>
                        </div>
                    )}

                    <ul className="space-y-2">
                        {(item?.blocks ?? []).map((block) => (
                            <li
                                key={block.id}
                                className="pgs-roadmap-block flex items-center justify-between gap-2"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">{block.block_type}</p>
                                    <div className="pgs-roadmap-block-preview mt-2 max-w-xl">
                                        <RoadmapBlockPreview block={block} />
                                    </div>
                                </div>
                                <div className="flex shrink-0 gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        loading={isPending(`update-block:${String(block.id)}`)}
                                        loadingText="Saving"
                                        onClick={() => {
                                            onUpdateBlock(block);
                                        }}
                                    >
                                        <Save className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        loading={isPending('delete-block')}
                                        loadingText=""
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => {
                                            onDeleteBlock(block);
                                        }}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </li>
                        ))}
                        {(item?.blocks ?? []).length === 0 && (
                            <li className="text-muted-foreground py-2 text-sm">
                                No blocks yet.
                            </li>
                        )}
                    </ul>

                    <RoadmapAddBlockForm form={form} onAddBlock={onAddBlock} />
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}
