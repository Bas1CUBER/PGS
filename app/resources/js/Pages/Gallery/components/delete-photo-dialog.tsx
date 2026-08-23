import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import type { Photo } from './types';

interface DeletePhotoDialogProps {
    target: Photo | null;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    loading: boolean;
}

export function DeletePhotoDialog({
    target,
    onOpenChange,
    onConfirm,
    loading,
}: DeletePhotoDialogProps) {
    return (
        <PgsConfirmationDialog
            open={target !== null}
            onOpenChange={onOpenChange}
            title="Delete photo"
            description="This action permanently removes the photo."
            confirmationTitle="Confirm photo deletion"
            confirmationDescription={`"${target?.caption ?? 'This photo'}" will be removed from the gallery.`}
            onConfirm={onConfirm}
            loading={loading}
            loadingText="Deleting"
        />
    );
}
