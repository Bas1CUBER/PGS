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

interface AddIndicatorDialogProps {
    open: boolean;
    form: InertiaFormProps<{ category: string; year: string; description: string }>;
    canManage: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function AddIndicatorDialog({
    open,
    form,
    canManage,
    onOpenChange,
    onClose,
    onSubmit,
}: AddIndicatorDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader className="pgs-modal-header">
                    <span className="pgs-modal-eyebrow">Responsive overlay</span>
                    <DialogTitle>Add roadmap indicator</DialogTitle>
                    <DialogDescription>
                        Add a category, year, and description to this roadmap.
                        {!canManage && ' New indicators are sent to an admin for approval.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="pgs-modal-field">
                            <label htmlFor="new-category">Category</label>
                            <Input
                                id="new-category"
                                value={form.data.category}
                                onChange={(e) => {
                                    form.setData('category', e.target.value);
                                }}
                                required
                            />
                            {form.errors.category && (
                                <p className="text-destructive text-sm">{form.errors.category}</p>
                            )}
                        </div>
                        <div className="pgs-modal-field">
                            <label htmlFor="new-year">Year</label>
                            <Input
                                id="new-year"
                                type="number"
                                value={form.data.year}
                                onChange={(e) => {
                                    form.setData('year', e.target.value);
                                }}
                                required
                            />
                            {form.errors.year && (
                                <p className="text-destructive text-sm">{form.errors.year}</p>
                            )}
                        </div>
                        <div className="pgs-modal-field">
                            <label htmlFor="new-description">Description</label>
                            <textarea
                                id="new-description"
                                value={form.data.description}
                                onChange={(e) => {
                                    form.setData('description', e.target.value);
                                }}
                                rows={4}
                                className="pgs-modal-textarea"
                                required
                            />
                            {form.errors.description && (
                                <p className="text-destructive text-sm">{form.errors.description}</p>
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
                        <Button type="submit" loading={form.processing} loadingText="Saving">
                            <Plus className="size-4" /> Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
