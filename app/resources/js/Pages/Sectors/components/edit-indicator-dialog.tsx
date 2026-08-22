import type { InertiaFormProps } from '@inertiajs/react';
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

interface EditIndicatorDialogProps {
    open: boolean;
    form: InertiaFormProps<{ category: string; year: string; description: string }>;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function EditIndicatorDialog({
    open,
    form,
    onOpenChange,
    onClose,
    onSubmit,
}: EditIndicatorDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader className="pgs-modal-header">
                    <span className="pgs-modal-eyebrow">Responsive overlay</span>
                    <DialogTitle>Edit indicator</DialogTitle>
                    <DialogDescription>
                        Update the selected roadmap indicator.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="pgs-modal-field">
                            <label htmlFor="cat">Category</label>
                            <Input
                                id="cat"
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
                            <label htmlFor="yr">Year</label>
                            <Input
                                id="yr"
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
                            <label htmlFor="desc">Description</label>
                            <textarea
                                id="desc"
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
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
