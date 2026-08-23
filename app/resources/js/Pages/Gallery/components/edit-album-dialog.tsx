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

interface EditAlbumDialogProps {
    open: boolean;
    form: InertiaFormProps<{
        name: string;
        description: string;
    }>;
    onOpenChange: (open: boolean) => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function EditAlbumDialog({
    open,
    form,
    onOpenChange,
    onSubmit,
}: EditAlbumDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Edit album</DialogTitle>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="space-y-2">
                            <Label htmlFor="edit-album-name">Name</Label>
                            <Input
                                id="edit-album-name"
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
                        <div className="space-y-2">
                            <Label htmlFor="edit-album-description">Description</Label>
                            <Input
                                id="edit-album-description"
                                value={form.data.description}
                                onChange={(e) => {
                                    form.setData('description', e.target.value);
                                }}
                            />
                            {form.errors.description && (
                                <p className="text-destructive text-sm">{form.errors.description}</p>
                            )}
                        </div>
                    </DialogBody>
                    <DialogFooter>
                        <Button
                            type="submit"
                            loading={form.processing}
                            loadingText="Saving"
                        >
                            <Save className="size-4" /> Save album
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
