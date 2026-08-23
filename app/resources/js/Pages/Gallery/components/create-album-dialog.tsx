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
import { Label } from '@/components/ui/label';

interface CreateAlbumDialogProps {
    open: boolean;
    form: InertiaFormProps<{
        name: string;
        description: string;
    }>;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function CreateAlbumDialog({
    open,
    form,
    onOpenChange,
    onClose,
    onSubmit,
}: CreateAlbumDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>New album</DialogTitle>
                    <DialogDescription>Create a new gallery album.</DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="pgs-modal-field">
                            <Label htmlFor="album-name">Album name</Label>
                            <Input
                                id="album-name"
                                value={form.data.name}
                                onChange={(e) => {
                                    form.setData('name', e.target.value);
                                }}
                                required
                            />
                            {form.errors.name && (
                                <p className="text-destructive text-sm">{form.errors.name}</p>
                            )}
                        </div>
                        <div className="pgs-modal-field">
                            <Label htmlFor="album-description">Description</Label>
                            <Input
                                id="album-description"
                                value={form.data.description}
                                onChange={(e) => {
                                    form.setData('description', e.target.value);
                                }}
                                placeholder="Optional"
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
                        <Button
                            type="submit"
                            loading={form.processing}
                            loadingText="Creating"
                        >
                            <Plus className="size-4" /> Create album
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
