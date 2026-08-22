import type { InertiaFormProps } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { RoadmapTitleRow } from './types';

interface AddItemDialogProps {
    open: boolean;
    form: InertiaFormProps<{ sub_label: string }>;
    titles: RoadmapTitleRow[];
    itemTitleId: number | null;
    draftValue: string;
    isPending: (action: string) => boolean;
    onDraftChange: (value: string) => void;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onSubmit: () => void;
}

export function AddItemDialog({
    open,
    form,
    titles,
    itemTitleId,
    draftValue,
    isPending,
    onDraftChange,
    onOpenChange,
    onClose,
    onSubmit,
}: AddItemDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Add roadmap item</DialogTitle>
                    <DialogDescription>
                        Add an item under "
                        {titles.find((title) => title.id === itemTitleId)?.title ?? ''}".
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSubmit();
                    }}
                    className="pgs-modal-form pgs-modal-form-scroll"
                >
                    <DialogBody>
                        <div className="pgs-modal-field">
                            <label htmlFor="new-roadmap-item">Item title</label>
                            <Input
                                id="new-roadmap-item"
                                value={draftValue}
                                onChange={(e) => {
                                    onDraftChange(e.target.value);
                                }}
                                placeholder="e.g. Quality of Life Index"
                                required
                            />
                            {form.errors.sub_label && (
                                <p className="text-destructive text-sm">
                                    {form.errors.sub_label}
                                </p>
                            )}
                        </div>
                    </DialogBody>
                    <DialogFooter className="pgs-modal-footer">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                onClose();
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
    );
}
