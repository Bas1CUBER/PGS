import { CalendarPlus } from 'lucide-react';
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

interface AddYearDialogProps {
    open: boolean;
    year: string;
    onYearChange: (value: string) => void;
    onOpenChange: (open: boolean) => void;
    onCancel: () => void;
    onSubmit: (event: { preventDefault(): void }) => void;
    isPending: (action: string) => boolean;
}

export function AddYearDialog({
    open,
    year,
    onYearChange,
    onOpenChange,
    onCancel,
    onSubmit,
    isPending,
}: AddYearDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Add year</DialogTitle>
                    <DialogDescription>Add a target year to the scorecard.</DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="pgs-modal-field">
                            <label htmlFor="new-year">Year</label>
                            <Input
                                id="new-year"
                                type="number"
                                min={2000}
                                max={2100}
                                value={year}
                                onChange={(e) => {
                                    onYearChange(e.target.value);
                                }}
                                placeholder="e.g. 2029"
                                required
                            />
                        </div>
                    </DialogBody>
                    <DialogFooter className="pgs-modal-footer">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                onCancel();
                            }}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" loading={isPending('add-year')} loadingText="Adding">
                            <CalendarPlus className="size-4" /> Add year
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
