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

interface EditMeasureDialogProps {
    open: boolean;
    impact: string;
    measure: string;
    bl: string;
    onImpactChange: (value: string) => void;
    onMeasureChange: (value: string) => void;
    onBaselineChange: (value: string) => void;
    onOpenChange: (open: boolean) => void;
    onCancel: () => void;
    onSave: () => void;
    isPending: (action: string) => boolean;
}

export function EditMeasureDialog({
    open,
    impact,
    measure,
    bl,
    onImpactChange,
    onMeasureChange,
    onBaselineChange,
    onOpenChange,
    onCancel,
    onSave,
    isPending,
}: EditMeasureDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-modal-form-dialog">
                <DialogHeader>
                    <DialogTitle>Edit measure</DialogTitle>
                </DialogHeader>
                <DialogBody className="space-y-3">
                    <div className="space-y-2">
                        <Label htmlFor="edit-impact">Impact</Label>
                        <Input
                            id="edit-impact"
                            value={impact}
                            onChange={(e) => {
                                onImpactChange(e.target.value);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="edit-measure">Measure</Label>
                        <Input
                            id="edit-measure"
                            value={measure}
                            onChange={(e) => {
                                onMeasureChange(e.target.value);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="edit-bl">Baseline</Label>
                        <Input
                            id="edit-bl"
                            value={bl}
                            onChange={(e) => {
                                onBaselineChange(e.target.value);
                            }}
                        />
                    </div>
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => {
                            onCancel();
                        }}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={onSave}
                        loading={isPending('save-measure')}
                        loadingText="Saving"
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
