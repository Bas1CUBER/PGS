import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { LayoutList, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/components/pgs-toast';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import { usePendingAction } from '@/hooks/use-pending-action';
import { AddItemDialog } from './components/add-item-dialog';
import { AddSectionDialog } from './components/add-section-dialog';
import { BuilderDialog } from './components/builder-dialog';
import { blockTypes, isRecordOfStrings } from './components/lib';
import { RoadmapSectionCard } from './components/roadmap-section-card';
import { RoadmapStatPreview } from './components/roadmap-stat-preview';
import type {
    RoadmapBlock,
    RoadmapItem,
    RoadmapTitleRow,
    RoadmapsPageProps,
} from './components/types';

export default function RoadmapsIndex({ titles }: RoadmapsPageProps) {
    const titleForm = useForm({ title: '' });
    const itemForm = useForm({ sub_label: '' });
    const blockForm = useForm({ block_type: blockTypes[0] ?? 'paragraph', content: '{}' });
    const blockUpdateForm = useForm({ content: '{}' });
    const reorderForm = useForm({ direction: 'up' as 'up' | 'down' });
    const [itemDrafts, setItemDrafts] = useState<Record<number, string>>({});
    const [titleDialogOpen, setTitleDialogOpen] = useState(false);
    const [itemTitleId, setItemTitleId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<RoadmapTitleRow | null>(null);
    const [deleteItemTarget, setDeleteItemTarget] = useState<RoadmapItem | null>(null);
    const [deleteBlockTarget, setDeleteBlockTarget] = useState<RoadmapBlock | null>(null);
    const [builderItem, setBuilderItem] = useState<RoadmapItem | null>(null);
    const { isPending, start, finish } = usePendingAction();
    const { showToast } = useToast();
    const statBlocks = titles.flatMap((title) =>
        title.items.flatMap((item) =>
            (item.blocks ?? [])
                .filter((block) => block.block_type === 'dashboard_stat')
                .map((block) => ({ block, item })),
        ),
    );

    function addTitle(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (titleForm.data.title.trim() === '') return;
        titleForm.post('/roadmaps/titles', {
            preserveScroll: true,
            onSuccess: () => {
                titleForm.reset();
                setTitleDialogOpen(false);
            },
        });
    }

    function addItem(titleId: number): void {
        const content = (itemDrafts[titleId] ?? '').trim();
        if (content === '') return;
        const action = `add-item:${String(titleId)}`;
        start(action);
        itemForm.setData('sub_label', content);
        itemForm.post(`/roadmaps/titles/${String(titleId)}/items`, {
            preserveScroll: true,
            onSuccess: () => {
                setItemDrafts((prev) => ({ ...prev, [titleId]: '' }));
                setItemTitleId(null);
            },
            onFinish: () => {
                finish(action);
            },
        });
    }

    function openBuilder(item: RoadmapItem): void {
        setBuilderItem(item);
        blockForm.reset();
    }

    function addBlock(): void {
        if (builderItem === null) return;

        try {
            const parsed: unknown = JSON.parse(blockForm.data.content);
            if (!isRecordOfStrings(parsed)) {
                showToast('error', 'Block content must be a flat JSON object with string values.');
                return;
            }
            blockForm.setData({ block_type: blockForm.data.block_type, content: JSON.stringify(parsed) });
            blockForm.post(`/roadmaps/items/${String(builderItem.id)}/blocks`, {
                preserveScroll: true,
                onSuccess: () => {
                    blockForm.reset('content');
                },
            });
        } catch {
            showToast('error', 'Invalid JSON — please check the block content.');
        }
    }

    function updateBlock(block: RoadmapBlock): void {
        const raw = window.prompt('Block content (JSON):', JSON.stringify(block.content));
        if (raw === null) return;

        try {
            const parsed: unknown = JSON.parse(raw);
            if (!isRecordOfStrings(parsed)) {
                showToast('error', 'Block content must be a flat JSON object with string values.');
                return;
            }
            blockUpdateForm.setData({ content: JSON.stringify(parsed) });
            blockUpdateForm.put(`/roadmaps/blocks/${String(block.id)}`, {
                preserveScroll: true,
            });
        } catch {
            showToast('error', 'Invalid JSON — please check the block content.');
        }
    }

    function reorderItem(id: number, direction: 'up' | 'down'): void {
        const action = `reorder:${String(id)}:${direction}`;
        start(action);
        reorderForm.setData({ direction });
        reorderForm.post(`/roadmaps/items/${String(id)}/reorder`, {
            preserveScroll: true,
            onFinish: () => {
                finish(action);
            },
        });
    }

    function confirmDeleteItem(): void {
        if (deleteItemTarget === null) return;
        start('delete-item');
        router.delete(`/roadmaps/items/${String(deleteItemTarget.id)}`, {
            onFinish: () => {
                finish('delete-item');
                setDeleteItemTarget(null);
            },
        });
    }

    function deleteTitle(): void {
        if (deleteTarget === null) return;
        start('delete-title');
        router.delete(`/roadmaps/titles/${String(deleteTarget.id)}`, {
            onFinish: () => {
                finish('delete-title');
                setDeleteTarget(null);
            },
        });
    }

    function confirmDeleteBlock(): void {
        if (deleteBlockTarget === null) return;
        start('delete-block');
        router.delete(`/roadmaps/blocks/${String(deleteBlockTarget.id)}`, {
            onFinish: () => {
                finish('delete-block');
                setDeleteBlockTarget(null);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Roadmaps</h2>}
        >
            <Head title="Roadmaps" />

            <div className="space-y-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <CardTitle>Roadmap sections</CardTitle>
                        <Button
                            type="button"
                            onClick={() => {
                                setTitleDialogOpen(true);
                            }}
                        >
                            <Plus className="size-4" /> Add section
                        </Button>
                    </CardHeader>
                </Card>

                {statBlocks.length > 0 && (
                    <section className="space-y-3">
                        <div>
                            <h3 className="text-base font-medium">Configured stat cards</h3>
                            <p className="text-muted-foreground text-xs">
                                Dashboard stat blocks from the roadmap page builder.
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {statBlocks.map(({ block, item }) => (
                                <div key={`${String(item.id)}-${String(block.id)}`}>
                                    <RoadmapStatPreview block={block} />
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {titles.length === 0 && (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center">
                            <LayoutList className="mx-auto mb-2 size-8" />
                            No roadmap sections yet — add the first one above.
                        </CardContent>
                    </Card>
                )}

                {titles.map((title) => (
                    <RoadmapSectionCard
                        key={title.id}
                        title={title}
                        isPending={isPending}
                        onOpenBuilder={openBuilder}
                        onReorder={reorderItem}
                        onDeleteItem={setDeleteItemTarget}
                        onDeleteSection={setDeleteTarget}
                        onAddItem={setItemTitleId}
                    />
                ))}
            </div>

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete section"
                description="This action permanently removes the section and its items."
                confirmationTitle="Confirm section deletion"
                confirmationDescription={`"${deleteTarget?.title ?? 'This section'}" and all of its items will be removed.`}
                onConfirm={deleteTitle}
                loading={isPending('delete-title')}
                loadingText="Deleting"
            />

            <PgsConfirmationDialog
                open={deleteItemTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteItemTarget(null);
                }}
                title="Delete roadmap item"
                description="This action permanently removes the roadmap item and its blocks."
                confirmationTitle="Confirm roadmap item deletion"
                confirmationDescription={`"${deleteItemTarget?.sub_label ?? 'This item'}" and its blocks will be removed.`}
                onConfirm={confirmDeleteItem}
                loading={isPending('delete-item')}
                loadingText="Deleting"
            />

            <PgsConfirmationDialog
                open={deleteBlockTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteBlockTarget(null);
                }}
                title="Delete roadmap block"
                description="This action permanently removes the roadmap content block."
                confirmationTitle="Confirm block deletion"
                confirmationDescription={`${deleteBlockTarget?.block_type ?? 'This block'} will be removed from the roadmap item.`}
                onConfirm={confirmDeleteBlock}
                loading={isPending('delete-block')}
                loadingText="Deleting"
            />

            <AddSectionDialog
                open={titleDialogOpen}
                form={titleForm}
                onOpenChange={(open) => {
                    setTitleDialogOpen(open);
                    if (!open) titleForm.reset();
                }}
                onClose={() => {
                    setTitleDialogOpen(false);
                }}
                onSubmit={addTitle}
            />

            <AddItemDialog
                open={itemTitleId !== null}
                form={itemForm}
                titles={titles}
                itemTitleId={itemTitleId}
                draftValue={itemTitleId === null ? '' : (itemDrafts[itemTitleId] ?? '')}
                isPending={isPending}
                onDraftChange={(value) => {
                    if (itemTitleId !== null) {
                        setItemDrafts((prev) => ({ ...prev, [itemTitleId]: value }));
                    }
                }}
                onOpenChange={(open) => {
                    if (!open) setItemTitleId(null);
                }}
                onClose={() => {
                    setItemTitleId(null);
                }}
                onSubmit={() => {
                    if (itemTitleId !== null) addItem(itemTitleId);
                }}
            />

            <BuilderDialog
                item={builderItem}
                form={blockForm}
                isPending={isPending}
                onUpdateBlock={updateBlock}
                onDeleteBlock={setDeleteBlockTarget}
                onAddBlock={addBlock}
                onOpenChange={(open) => {
                    if (!open) setBuilderItem(null);
                }}
            />
        </AuthenticatedLayout>
    );
}
