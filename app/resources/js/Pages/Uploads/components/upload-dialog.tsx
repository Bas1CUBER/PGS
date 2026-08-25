import type { InertiaFormProps } from '@inertiajs/react';
import { Upload } from 'lucide-react';
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

interface UploadDialogProps {
    open: boolean;
    form: InertiaFormProps<{ title: string; description: string }>;
    file: File | null;
    singular: string;
    hasTitle: boolean;
    hasDescription: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onFileChange: (file: File | null) => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function UploadDialog({
    open,
    form,
    file,
    singular,
    hasTitle,
    hasDescription,
    onOpenChange,
    onClose,
    onFileChange,
    onSubmit,
}: UploadDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Upload {singular}</DialogTitle>
                    <DialogDescription>Add a file to this module.</DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        {hasTitle && (
                            <div className="pgs-modal-field">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) => {
                                        form.setData('title', e.target.value);
                                    }}
                                    required
                                />
                                {form.errors.title && (
                                    <p className="text-destructive text-sm">{form.errors.title}</p>
                                )}
                            </div>
                        )}
                        {hasDescription && (
                            <div className="pgs-modal-field">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) => {
                                        form.setData('description', e.target.value);
                                    }}
                                    rows={3}
                                    className="pgs-modal-textarea"
                                />
                                {form.errors.description && (
                                    <p className="text-destructive text-sm">
                                        {form.errors.description}
                                    </p>
                                )}
                            </div>
                        )}
                        <div className="pgs-modal-field">
                            <Label htmlFor="upload-file">File</Label>
                            <Input
                                id="upload-file"
                                type="file"
                                onChange={(e) => {
                                    onFileChange(e.target.files?.[0] ?? null);
                                }}
                            />
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
                            loadingText="Uploading"
                            disabled={file === null}
                        >
                            <Upload className="size-4" /> Upload
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
