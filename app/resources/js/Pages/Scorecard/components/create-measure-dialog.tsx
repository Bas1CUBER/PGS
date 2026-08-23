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

interface CreateMeasureDialogProps {
    open: boolean;
    impact: string;
    measure: string;
    bl: string;
    onImpactChange: (value: string) => void;
    onMeasureChange: (value: string) => void;
    onBaselineChange: (value: string) => void;
    onOpenChange: (open: boolean) => void;
    onCancel: () => void;
    onSubmit: (event: { preventDefault(): void }) => void;
    isPending: (action: string) => boolean;
}

export function CreateMeasureDialog({
    open,
    impact,
    measure,
    bl,
    onImpactChange,
    onMeasureChange,
    onBaselineChange,
    onOpenChange,
    onCancel,
    onSubmit,
    isPending,
}: CreateMeasureDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Add measure</DialogTitle>
                    <DialogDescription>
                        Add a measure to the impact scorecard.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={onSubmit} className="pgs-modal-form pgs-modal-form-scroll">
                    <DialogBody>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="pgs-modal-field">
                                <label htmlFor="impact">Impact</label>
                                <Input
                                    id="impact"
                                    value={impact}
                                    onChange={(e) => {
                                        onImpactChange(e.target.value);
                                    }}
                                    required
                                />
                            </div>
                            <div className="pgs-modal-field">
                                <label htmlFor="measure">Measure</label>
                                <Input
                                    id="measure"
                                    value={measure}
                                    onChange={(e) => {
                                        onMeasureChange(e.target.value);
                                    }}
                                    required
                                />
                            </div>
                        </div>
                        <div className="pgs-modal-field">
                            <label htmlFor="bl">Baseline</label>
                            <Input
                                id="bl"
                                value={bl}
                                onChange={(e) => {
                                    onBaselineChange(e.target.value);
                                }}
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
                        <Button
                            type="submit"
                            loading={isPending('create-measure')}
                            loadingText="Adding"
                        >
                            <Plus className="size-4" /> Add measure
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
