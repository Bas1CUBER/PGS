import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowDown,
    ArrowUp,
    BarChart3,
    FileText,
    LayoutList,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsStatCard, type PgsStatTone } from '@/components/pgs-stat-card';

interface RoadmapBlock {
    id: number;
    block_type: string;
    content: Record<string, unknown>;
}

interface RoadmapItem {
    id: number;
    sub_label: string;
    sub_letter: string;
    page_slug: string;
    sort_order: number;
    blocks?: RoadmapBlock[];
}

interface RoadmapTitleRow {
    id: number;
    title: string;
    sort_order: number;
    items: RoadmapItem[];
}

interface RoadmapsPageProps extends PageProps {
    titles: RoadmapTitleRow[];
}

const blockTypes = ['heading', 'paragraph', 'table', 'dashboard_stat'];

function contentText(content: Record<string, unknown>, key: string, fallback: string): string {
    const value = content[key];

    return typeof value === 'string' || typeof value === 'number' ? String(value) : fallback;
}

function contentTone(content: Record<string, unknown>): PgsStatTone {
    const tone = content.tone;

    return tone === 'green' || tone === 'violet' || tone === 'amber' || tone === 'red'
        ? tone
        : 'blue';
}

function RoadmapStatPreview({ block }: { block: RoadmapBlock }) {
    if (block.block_type !== 'dashboard_stat') return null;

    return (
        <PgsStatCard
            compact
            label={contentText(block.content, 'label', 'Untitled stat')}
            value={contentText(block.content, 'value', '0')}
            icon={<BarChart3 className="size-5" />}
            status="Configured"
            detail="Roadmap page builder"
            tone={contentTone(block.content)}
        />
    );
}

function RoadmapBlockPreview({ block }: { block: RoadmapBlock }) {
    if (block.block_type === 'dashboard_stat') return <RoadmapStatPreview block={block} />;

    if (block.block_type === 'heading') {
        return (
            <h3 className="font-dot-gothic text-lg">
                {contentText(
                    block.content,
                    'text',
                    contentText(block.content, 'title', 'Untitled section'),
                )}
            </h3>
        );
    }

    if (block.block_type === 'paragraph') {
        return (
            <p className="text-muted-foreground text-sm leading-6 whitespace-pre-wrap">
                {contentText(
                    block.content,
                    'text',
                    contentText(block.content, 'body', 'No paragraph content yet.'),
                )}
            </p>
        );
    }

    if (block.block_type === 'table') {
        const columns = Array.isArray(block.content.columns)
            ? block.content.columns.filter((column): column is string => typeof column === 'string')
            : [];
        const rows = Array.isArray(block.content.rows)
            ? block.content.rows.filter(
                  (row): row is Record<string, unknown> => typeof row === 'object' && row !== null,
              )
            : [];

        return columns.length > 0 ? (
            <div data-slot="table-container" className="relative w-full overflow-x-auto">
                <table data-slot="table" className="w-full text-left text-sm">
                    <thead data-slot="table-header">
                        <tr data-slot="table-row">
                            {columns.map((column) => (
                                <th
                                    key={column}
                                    data-slot="table-head"
                                    className="border-b px-2 py-2 font-semibold"
                                >
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody data-slot="table-body">
                        {rows.map((row, index) => (
                            <tr
                                key={index}
                                data-slot="table-row"
                                className="border-b last:border-0"
                            >
                                {columns.map((column) => (
                                    <td key={column} data-slot="table-cell" className="px-2 py-2">
                                        {typeof row[column] === 'string' ||
                                        typeof row[column] === 'number'
                                            ? String(row[column])
                                            : ''}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        ) : (
            <p className="text-muted-foreground text-sm">
                Table needs a columns array and rows array.
            </p>
        );
    }

    return (
        <p className="text-muted-foreground font-mono text-xs">{JSON.stringify(block.content)}</p>
    );
}

export default function RoadmapsIndex({ titles }: RoadmapsPageProps) {
    const [newTitle, setNewTitle] = useState('');
    const [itemDrafts, setItemDrafts] = useState<Record<number, string>>({});
    const [titleDialogOpen, setTitleDialogOpen] = useState(false);
    const [itemTitleId, setItemTitleId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<RoadmapTitleRow | null>(null);
    const [deleteItemTarget, setDeleteItemTarget] = useState<RoadmapItem | null>(null);
    const [deleteBlockTarget, setDeleteBlockTarget] = useState<RoadmapBlock | null>(null);
    const [builderItem, setBuilderItem] = useState<RoadmapItem | null>(null);
    const [blockType, setBlockType] = useState(blockTypes[0] ?? 'paragraph');
    const [blockContent, setBlockContent] = useState('{}');
    const { isPending, start, finish } = usePendingAction();
    const statBlocks = titles.flatMap((title) =>
        title.items.flatMap((item) =>
            (item.blocks ?? [])
                .filter((block) => block.block_type === 'dashboard_stat')
                .map((block) => ({ block, item })),
        ),
    );

    function addTitle(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (newTitle.trim() === '') return;
        start('add-title');
        router.post(
            '/roadmaps/titles',
            { title: newTitle },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewTitle('');
                    setTitleDialogOpen(false);
                },
                onFinish: () => {
                    finish('add-title');
                },
            },
        );
    }

    function addItem(titleId: number): void {
        const content = (itemDrafts[titleId] ?? '').trim();
        if (content === '') return;
        const action = `add-item:${String(titleId)}`;
        start(action);
        router.post(
            `/roadmaps/titles/${String(titleId)}/items`,
            { sub_label: content },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setItemDrafts((prev) => ({ ...prev, [titleId]: '' }));
                    setItemTitleId(null);
                },
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    function openBuilder(item: RoadmapItem): void {
        setBuilderItem(item);
        setBlockType(blockTypes[0] ?? 'paragraph');
        setBlockContent('{}');
    }

    function addBlock(): void {
        if (builderItem === null) return;

        try {
            const parsed = JSON.parse(blockContent) as Record<string, string>;
            start('add-block');

            router.post(
                `/roadmaps/items/${String(builderItem.id)}/blocks`,
                { block_type: blockType, content: parsed },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setBlockContent('{}');
                    },
                    onFinish: () => {
                        finish('add-block');
                    },
                },
            );
        } catch {
            return;
        }
    }

    function updateBlock(block: RoadmapBlock): void {
        const raw = window.prompt('Block content (JSON):', JSON.stringify(block.content));
        if (raw === null) return;

        try {
            const parsed = JSON.parse(raw) as Record<string, string>;
            const action = `update-block:${String(block.id)}`;
            start(action);
            router.put(
                `/roadmaps/blocks/${String(block.id)}`,
                { content: parsed },
                {
                    preserveScroll: true,
                    onFinish: () => {
                        finish(action);
                    },
                },
            );
        } catch {
            // Invalid JSON: do nothing.
        }
    }

    function reorderItem(id: number, direction: 'up' | 'down'): void {
        const action = `reorder:${String(id)}:${direction}`;
        start(action);
        router.post(
            `/roadmaps/items/${String(id)}/reorder`,
            { direction },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                },
            },
        );
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
                    <Card key={title.id} className="pgs-roadmap-section-card">
                        <CardHeader className="pgs-roadmap-section-header flex flex-row items-center justify-between">
                            <CardTitle>{title.title}</CardTitle>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                onClick={() => {
                                    setDeleteTarget(title);
                                }}
                            >
                                <Trash2 className="size-4" />
                                Delete
                            </Button>
                        </CardHeader>
                        <CardContent className="pgs-roadmap-section-content space-y-3">
                            <ul className="space-y-2">
                                {title.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="pgs-roadmap-item flex items-center gap-2"
                                    >
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
                                                    openBuilder(item);
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
                                                    reorderItem(item.id, 'up');
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
                                                    reorderItem(item.id, 'down');
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
                                                    setDeleteItemTarget(item);
                                                }}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </li>
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
                                        setItemTitleId(title.id);
                                    }}
                                >
                                    <Plus className="size-4" /> Add item
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
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

            <Dialog
                open={titleDialogOpen}
                onOpenChange={(open) => {
                    setTitleDialogOpen(open);
                    if (!open) setNewTitle('');
                }}
            >
                <DialogContent className="pgs-modal-form-dialog">
                    <DialogHeader>
                        <DialogTitle>Add roadmap section</DialogTitle>
                        <DialogDescription>Create a new section for the roadmap.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={addTitle} className="pgs-modal-form pgs-modal-form-scroll">
                        <DialogBody>
                            <div className="pgs-modal-field">
                                <label htmlFor="new-roadmap-section">Section title</label>
                                <Input
                                    id="new-roadmap-section"
                                    value={newTitle}
                                    onChange={(e) => {
                                        setNewTitle(e.target.value);
                                    }}
                                    placeholder="e.g. Collaborative Health Care"
                                    required
                                />
                            </div>
                        </DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setTitleDialogOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                loading={isPending('add-title')}
                                loadingText="Adding"
                            >
                                <Plus className="size-4" /> Add section
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={itemTitleId !== null}
                onOpenChange={(open) => {
                    if (!open) setItemTitleId(null);
                }}
            >
                <DialogContent className="pgs-modal-form-dialog">
                    <DialogHeader>
                        <DialogTitle>Add roadmap item</DialogTitle>
                        <DialogDescription>
                            Add an item under “
                            {titles.find((title) => title.id === itemTitleId)?.title ?? ''}”.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (itemTitleId !== null) addItem(itemTitleId);
                        }}
                        className="pgs-modal-form pgs-modal-form-scroll"
                    >
                        <DialogBody>
                            <div className="pgs-modal-field">
                                <label htmlFor="new-roadmap-item">Item title</label>
                                <Input
                                    id="new-roadmap-item"
                                    value={
                                        itemTitleId === null ? '' : (itemDrafts[itemTitleId] ?? '')
                                    }
                                    onChange={(e) => {
                                        if (itemTitleId !== null) {
                                            setItemDrafts((prev) => ({
                                                ...prev,
                                                [itemTitleId]: e.target.value,
                                            }));
                                        }
                                    }}
                                    placeholder="e.g. Quality of Life Index"
                                    required
                                />
                            </div>
                        </DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setItemTitleId(null);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                loading={
                                    itemTitleId !== null &&
                                    isPending(`add-item:${String(itemTitleId)}`)
                                }
                                loadingText="Adding"
                            >
                                <Plus className="size-4" /> Add item
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={builderItem !== null}
                onOpenChange={(open) => {
                    if (!open) setBuilderItem(null);
                }}
            >
                <DialogContent className="pgs-modal-form-dialog pgs-modal-wide">
                    <DialogHeader>
                        <DialogTitle>Page builder — {builderItem?.sub_label ?? ''}</DialogTitle>
                    </DialogHeader>

                    <DialogBody className="space-y-4">
                        {(builderItem?.blocks ?? []).some(
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
                                    {(builderItem?.blocks ?? [])
                                        .filter((block) => block.block_type === 'dashboard_stat')
                                        .map((block) => (
                                            <RoadmapStatPreview key={block.id} block={block} />
                                        ))}
                                </div>
                            </div>
                        )}

                        <ul className="space-y-2">
                            {(builderItem?.blocks ?? []).map((block) => (
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
                                                updateBlock(block);
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
                                                setDeleteBlockTarget(block);
                                            }}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </li>
                            ))}
                            {(builderItem?.blocks ?? []).length === 0 && (
                                <li className="text-muted-foreground py-2 text-sm">
                                    No blocks yet.
                                </li>
                            )}
                        </ul>

                        <div className="pgs-roadmap-block-form space-y-3">
                            <p className="text-sm font-medium">Add block</p>
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <select
                                    value={blockType}
                                    onChange={(e) => {
                                        setBlockType(e.target.value);
                                    }}
                                    className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                    aria-label="Block type"
                                >
                                    {blockTypes.map((type) => (
                                        <option key={type} value={type}>
                                            {type}
                                        </option>
                                    ))}
                                </select>
                                <Input
                                    value={blockContent}
                                    onChange={(e) => {
                                        setBlockContent(e.target.value);
                                    }}
                                    placeholder='{"label":"...", "value":"..."}'
                                    className="font-sans"
                                    aria-label="Block content JSON"
                                />
                            </div>
                            <Button
                                size="sm"
                                loading={isPending('add-block')}
                                loadingText="Adding"
                                onClick={addBlock}
                            >
                                <Plus className="size-4" />
                                Add block
                            </Button>
                        </div>
                    </DialogBody>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
