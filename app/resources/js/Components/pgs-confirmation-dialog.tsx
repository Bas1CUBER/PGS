import type { SyntheticEvent } from 'react';
import { CheckCircle2, KeyRound, Trash2, XCircle } from 'lucide-react';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ConfirmationPasswordField {
    value: string;
    onChange: (value: string) => void;
    error?: string;
}

type ConfirmationKind = 'delete' | 'approve' | 'reject';

interface PgsConfirmationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmationTitle: string;
    confirmationDescription: string;
    onConfirm: () => void;
    loading?: boolean;
    loadingText?: string;
    confirmText?: string;
    confirmVariant?: 'default' | 'destructive';
    kind?: ConfirmationKind;
    password?: ConfirmationPasswordField;
}

export function PgsConfirmationDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmationTitle,
    confirmationDescription,
    onConfirm,
    loading = false,
    loadingText = 'Deleting',
    confirmText = 'Delete permanently',
    confirmVariant = 'destructive',
    kind = 'delete',
    password,
}: PgsConfirmationDialogProps) {
    function submit(event: SyntheticEvent<HTMLFormElement>): void {
        event.preventDefault();
        onConfirm();
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="pgs-confirmation-dialog" data-confirmation-kind={kind}>
                <DialogHeader className="pgs-confirmation-header">
                    <span className="pgs-confirmation-eyebrow">Responsive overlay</span>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="pgs-confirmation-form">
                    <DialogBody>
                        <div className="pgs-confirmation-summary">
                            <span className="pgs-confirmation-icon" aria-hidden="true">
                                {password ? (
                                    <KeyRound size={21} />
                                ) : kind === 'approve' ? (
                                    <CheckCircle2 size={21} />
                                ) : kind === 'reject' ? (
                                    <XCircle size={21} />
                                ) : (
                                    <Trash2 size={21} />
                                )}
                            </span>
                            <div>
                                <p>{confirmationTitle}</p>
                                <small>{confirmationDescription}</small>
                            </div>
                        </div>

                        {password && (
                            <div className="pgs-confirmation-field">
                                <Label htmlFor="confirmation-password">Password</Label>
                                <Input
                                    id="confirmation-password"
                                    type="password"
                                    name="password"
                                    value={password.value}
                                    onChange={(event) => {
                                        password.onChange(event.target.value);
                                    }}
                                    autoFocus
                                    placeholder="Enter password"
                                    aria-invalid={password.error ? true : undefined}
                                />
                                {password.error && (
                                    <p className="pgs-confirmation-error">{password.error}</p>
                                )}
                            </div>
                        )}
                    </DialogBody>

                    <DialogFooter className="pgs-confirmation-footer">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                onOpenChange(false);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={confirmVariant}
                            className={
                                confirmVariant === 'default'
                                    ? 'pgs-success-action-button'
                                    : undefined
                            }
                            loading={loading}
                            loadingText={loadingText}
                            disabled={loading}
                        >
                            {confirmText}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
