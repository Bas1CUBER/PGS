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

interface AddSectionDialogProps {
    open: boolean;
    form: InertiaFormProps<{ title: string }>;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function AddSectionDialog({
    open,
    form,
    onOpenChange,
    onClose,
    onSubmit,
}: AddSectionDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Add roadmap section</DialogTitle>
                    <DialogDescription>Create a new section for the roadmap.</DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="pgs-modal-field">
                            <label htmlFor="new-roadmap-section">Section title</label>
                            <Input
                                id="new-roadmap-section"
                                value={form.data.title}
                                onChange={(e) => {
                                    form.setData('title', e.target.value);
                                }}
                                placeholder="e.g. Collaborative Health Care"
                                required
                            />
                            {form.errors.title && (
                                <p className="text-destructive text-sm">{form.errors.title}</p>
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
                        <Button type="submit" loading={form.processing} loadingText="Adding">
                            <Plus className="size-4" /> Add section
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
