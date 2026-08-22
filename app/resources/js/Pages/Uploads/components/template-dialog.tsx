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

interface TemplateDialogProps {
    open: boolean;
    form: InertiaFormProps<{ label: string }>;
    file: File | null;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onFileChange: (file: File | null) => void;
    onSubmit: (event: { preventDefault(): void }) => void;
}

export function TemplateDialog({
    open,
    form,
    file,
    onOpenChange,
    onClose,
    onFileChange,
    onSubmit,
}: TemplateDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Manage module templates</DialogTitle>
                    <DialogDescription>
                        Add a replacement or supplemental guide for this module.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="pgs-modal-field">
                            <Label htmlFor="template-label">Template label</Label>
                            <Input
                                id="template-label"
                                value={form.data.label}
                                onChange={(e) => {
                                    form.setData('label', e.target.value);
                                }}
                                placeholder="e.g. 2026 review form"
                                required
                            />
                            {form.errors.label && (
                                <p className="text-destructive text-sm">
                                    {form.errors.label}
                                </p>
                            )}
                        </div>
                        <div className="pgs-modal-field">
                            <Label htmlFor="template-file">Template file</Label>
                            <Input
                                id="template-file"
                                type="file"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                onChange={(e) => {
                                    onFileChange(e.target.files?.[0] ?? null);
                                }}
                                required
                            />
                        </div>
                    </DialogBody>
                    <DialogFooter className="pgs-modal-footer">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            loading={form.processing}
                            loadingText="Saving"
                            disabled={file === null || form.data.label.trim() === ''}
                        >
                            <Upload className="size-4" /> Save template
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
