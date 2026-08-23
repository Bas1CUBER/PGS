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

interface CreateRowDialogProps {
    open: boolean;
    columns: string[];
    form: InertiaFormProps<Record<string, string>>;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onSubmit: () => void;
}

export function CreateRowDialog({
    open,
    columns,
    form,
    onOpenChange,
    onClose,
    onSubmit,
}: CreateRowDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog pgs-modal-wide">
                <DialogHeader>
                    <DialogTitle>Add roadmap row</DialogTitle>
                    <DialogDescription>
                        Add a new record to this detailed roadmap table.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSubmit();
                    }}
                    className="pgs-modal-form pgs-modal-form-scroll"
                >
                    <DialogBody>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {columns.map((column) => (
                                <div key={column} className="pgs-modal-field">
                                    <label htmlFor={`new-${column}`}>
                                        {column.replace(/_/g, ' ')}
                                    </label>
                                    <input
                                        id={`new-${column}`}
                                        value={form.data[column] ?? ''}
                                        onChange={(event) => {
                                            form.setData(column, event.target.value);
                                        }}
                                    />
                                    {form.errors[column] && (
                                        <p className="text-destructive text-sm">
                                            {form.errors[column]}
                                        </p>
                                    )}
                                </div>
                            ))}
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
                            <Plus className="size-4" /> Add row
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
