import { LockKeyhole, LockOpen, Save, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { TableRowActions } from '@/components/table-row-actions';
import type { SectorDetailRow } from './types';

interface DetailRowActionsProps {
    row: SectorDetailRow;
    canManage: boolean;
    lockColumn: string | null;
    isPending: (action: string) => boolean;
    onSave: () => void;
    onToggleLock: () => void;
    onDelete: () => void;
}

export function DetailRowActions({
    row,
    canManage,
    lockColumn,
    isPending,
    onSave,
    onToggleLock,
    onDelete,
}: DetailRowActionsProps) {
    return (
        <div className="flex justify-end gap-1">
            {row.locked && (
                <Badge variant="outline" className="mr-1 gap-1">
                    <LockKeyhole className="size-3" /> Locked
                </Badge>
            )}
            <TableRowActions label={`Row #${String(row.id)}`}>
                <DropdownMenuItem
                    disabled={
                        (Boolean(row.locked) && !canManage) || isPending(`save:${String(row.id)}`)
                    }
                    onSelect={() => {
                        onSave();
                    }}
                >
                    <Save className="size-4" /> Save
                </DropdownMenuItem>
                {lockColumn !== null && canManage && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled={isPending(`lock:${String(row.id)}`)}
                            onSelect={() => {
                                onToggleLock();
                            }}
                        >
                            {row.locked ? (
                                <LockOpen className="size-4" />
                            ) : (
                                <LockKeyhole className="size-4" />
                            )}
                            {row.locked ? 'Unlock' : 'Lock'} row
                        </DropdownMenuItem>
                    </>
                )}
                {canManage && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            disabled={isPending('delete')}
                            onSelect={() => {
                                onDelete();
                            }}
                        >
                            <Trash2 className="size-4" /> Delete
                        </DropdownMenuItem>
                    </>
                )}
            </TableRowActions>
        </div>
    );
}
