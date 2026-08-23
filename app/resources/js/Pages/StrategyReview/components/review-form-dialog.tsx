import type { InertiaFormProps } from '@inertiajs/react';
import { Save, Send } from 'lucide-react';
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
import { ReviewFormFields } from './review-form-fields';

interface ReviewFormDialogProps {
    open: boolean;
    form: InertiaFormProps<Record<string, string>>;
    editingId: number | null;
    isPending: (action: string) => boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onSave: (status: 'Draft' | 'Submitted') => void;
}

export function ReviewFormDialog({
    open,
    form,
    editingId,
    isPending,
    onOpenChange,
    onClose,
    onSave,
}: ReviewFormDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog pgs-modal-wide">
                <DialogHeader>
                    <DialogTitle>
                        {editingId === null
                            ? 'New strategy review'
                            : `Edit review #${String(editingId)}`}
                    </DialogTitle>
                    <DialogDescription>
                        Complete the review, save it as a draft, or submit it for approval.
                    </DialogDescription>
                </DialogHeader>
                <div className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <ReviewFormFields form={form} />
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
                            type="button"
                            variant="outline"
                            onClick={() => {
                                onSave('Draft');
                            }}
                            loading={isPending('draft')}
                            loadingText="Saving"
                        >
                            <Save className="size-4" /> Save draft
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                onSave('Submitted');
                            }}
                            loading={isPending('submit')}
                            loadingText="Submitting"
                        >
                            <Send className="size-4" />{' '}
                            {editingId === null ? 'Submit review' : 'Update and submit'}
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}
