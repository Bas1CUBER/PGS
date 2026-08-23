import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import type { Album } from './types';

interface DeleteAlbumDialogProps {
    target: Album | null;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    loading: boolean;
}

export function DeleteAlbumDialog({
    target,
    onOpenChange,
    onConfirm,
    loading,
}: DeleteAlbumDialogProps) {
    return (
        <PgsConfirmationDialog
            open={target !== null}
            onOpenChange={onOpenChange}
            title="Delete album"
            description="This action permanently removes the album and its photos."
            confirmationTitle="Confirm album deletion"
            confirmationDescription={`"${target?.name ?? 'This album'}" and its photos will be removed.`}
            onConfirm={onConfirm}
            loading={loading}
            loadingText="Deleting"
        />
    );
}
