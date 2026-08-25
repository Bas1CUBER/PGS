import type { InertiaFormProps } from '@inertiajs/react';
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

interface UploadPhotosDialogProps {
    open: boolean;
    targetName: string;
    form: InertiaFormProps<{ caption: string; photos: File[] }>;
    photoFiles: File[];
    onPhotoFilesChange: (files: File[]) => void;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function UploadPhotosDialog({
    open,
    targetName,
    form,
    photoFiles,
    onPhotoFilesChange,
    onOpenChange,
    onClose,
    onSubmit,
}: UploadPhotosDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Add photos to "{targetName}"</DialogTitle>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="space-y-2">
                            <Label htmlFor="caption">Caption (optional)</Label>
                            <Input
                                id="caption"
                                value={form.data.caption}
                                onChange={(e) => {
                                    form.setData('caption', e.target.value);
                                }}
                            />
                            {form.errors.caption && (
                                <p className="text-destructive text-sm">{form.errors.caption}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="photo">Photos (JPG/PNG/WebP, max 10 MB each)</Label>
                            <Input
                                id="photo"
                                type="file"
                                accept="image/*"
                                multiple
                                onChange={(e) => {
                                    onPhotoFilesChange(Array.from(e.target.files ?? []));
                                }}
                            />
                            {form.errors.photos && (
                                <p className="text-destructive text-sm">{form.errors.photos}</p>
                            )}
                        </div>
                    </DialogBody>
                    <DialogFooter>
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
                            loadingText="Uploading"
                            disabled={photoFiles.length === 0}
                        >
                            Upload
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
