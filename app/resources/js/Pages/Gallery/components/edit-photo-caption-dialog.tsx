import type { InertiaFormProps } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface EditPhotoCaptionDialogProps {
    open: boolean;
    form: InertiaFormProps<{ caption: string }>;
    onOpenChange: (open: boolean) => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function EditPhotoCaptionDialog({
    open,
    form,
    onOpenChange,
    onSubmit,
}: EditPhotoCaptionDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Edit photo caption</DialogTitle>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="space-y-2">
                            <Label htmlFor="edit-photo-caption">Caption</Label>
                            <Input
                                id="edit-photo-caption"
                                value={form.data.caption}
                                onChange={(e) => {
                                    form.setData('caption', e.target.value);
                                }}
                            />
                            {form.errors.caption && (
                                <p className="text-destructive text-sm">{form.errors.caption}</p>
                            )}
                        </div>
                    </DialogBody>
                    <DialogFooter>
                        <Button
                            type="submit"
                            loading={form.processing}
                            loadingText="Saving"
                        >
                            <Save className="size-4" /> Save caption
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
