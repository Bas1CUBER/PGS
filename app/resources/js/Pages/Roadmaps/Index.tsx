import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowDown, ArrowUp, FileText, LayoutList, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PageProps } from '@/types';

interface RoadmapItem {
    id: number;
    content: string | null;
    sort_order: number;
    blocks?: { id: number; block_type: string; content: Record<string, unknown> }[];
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

export default function RoadmapsIndex({ titles }: RoadmapsPageProps) {
    const [newTitle, setNewTitle] = useState('');
    const [itemDrafts, setItemDrafts] = useState<Record<number, string>>({});
    const [deleteTarget, setDeleteTarget] = useState<RoadmapTitleRow | null>(null);

    function addTitle(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (newTitle.trim() === '') return;
        router.post('/roadmaps/titles', { title: newTitle }, { preserveScroll: true });
        setNewTitle('');
    }

    function addItem(titleId: number): void {
        const content = (itemDrafts[titleId] ?? '').trim();
        if (content === '') return;
        router.post(
            `/roadmaps/titles/${String(titleId)}/items`,
            { content },
            { preserveScroll: true },
        );
        setItemDrafts((prev) => ({ ...prev, [titleId]: '' }));
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Roadmaps</h2>}
        >
            <Head title="Roadmaps" />

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Roadmap sections</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={addTitle} className="flex max-w-md items-center gap-2">
                            <Input
                                value={newTitle}
                                onChange={(e) => {
                                    setNewTitle(e.target.value);
                                }}
                                placeholder="New section titleÃ¢â‚¬Â¦"
                                aria-label="New roadmap section"
                            />
                            <Button type="submit" size="sm">
                                <Plus className="size-4" />
                                Add
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {titles.length === 0 && (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center">
                            <LayoutList className="mx-auto mb-2 size-8" />
                            No roadmap sections yet Ã¢â‚¬â€ add the first one above.
                        </CardContent>
                    </Card>
                )}

                {titles.map((title) => (
                    <Card key={title.id}>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>{title.title}</CardTitle>
                            <div className="flex gap-1">
                                <Button
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
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <ul className="space-y-2">
                                {title.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center gap-2 rounded-md border p-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">
                                                {item.content ?? 'Untitled item'}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                <FileText className="mr-1 inline size-3" />
                                                {(item.blocks ?? []).length} block(s)
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Move up"
                                                onClick={() => {
                                                    router.post(
                                                        `/roadmaps/items/${String(item.id)}/reorder`,
                                                        { direction: 'up' },
                                                        { preserveScroll: true },
                                                    );
                                                }}
                                            >
                                                <ArrowUp className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Move down"
                                                onClick={() => {
                                                    router.post(
                                                        `/roadmaps/items/${String(item.id)}/reorder`,
                                                        { direction: 'down' },
                                                        { preserveScroll: true },
                                                    );
                                                }}
                                            >
                                                <ArrowDown className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Delete item"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => {
                                                    router.delete(
                                                        `/roadmaps/items/${String(item.id)}`,
                                                    );
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

                            <div className="flex max-w-md items-center gap-2">
                                <Input
                                    value={itemDrafts[title.id] ?? ''}
                                    onChange={(e) => {
                                        setItemDrafts((prev) => ({
                                            ...prev,
                                            [title.id]: e.target.value,
                                        }));
                                    }}
                                    placeholder="New item under this sectionÃ¢â‚¬Â¦"
                                    aria-label={`New item for ${title.title}`}
                                />
                                <Button
                                    size="sm"
                                    onClick={() => {
                                        addItem(title.id);
                                    }}
                                >
                                    <Plus className="size-4" />
                                    Add item
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete section</DialogTitle>
                        <p className="text-muted-foreground text-sm">
                            Delete "{deleteTarget?.title ?? ''}" and all its items? This cannot be
                            undone.
                        </p>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setDeleteTarget(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (deleteTarget !== null) {
                                    router.delete(`/roadmaps/titles/${String(deleteTarget.id)}`);
                                }
                                setDeleteTarget(null);
                            }}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
