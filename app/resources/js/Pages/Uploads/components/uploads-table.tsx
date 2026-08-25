import { Check, Download, RotateCcw, Trash2 } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { User } from '@/types';
import { TableRowActions } from '@/components/table-row-actions';
import { formatBytes } from './format-bytes';
import { StatusBadge } from './status-badge';
import type { UploadRow, UploadStatusTarget, UploadsShowPageProps } from './types';

interface UploadsTableProps {
    rows: UploadRow[];
    module: UploadsShowPageProps['module'];
    user: User | null;
    canReview: boolean;
    isPending: (action: string) => boolean;
    onSelectStatus: (target: UploadStatusTarget) => void;
    onDeleteRow: (row: UploadRow) => void;
}

export function UploadsTable({
    rows,
    module,
    user,
    canReview,
    isPending,
    onSelectStatus,
    onDeleteRow,
}: UploadsTableProps) {
    return (
        <Card>
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>File</TableHead>
                            {module.has_title && <TableHead>Title</TableHead>}
                            <TableHead>Size</TableHead>
                            <TableHead>Uploaded</TableHead>
                            {module.has_status && <TableHead>Status</TableHead>}
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell className="max-w-64">
                                    <p className="truncate font-medium">{row.original_name}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {row.uploader ?? '—'}
                                    </p>
                                </TableCell>
                                {module.has_title && (
                                    <TableCell className="text-sm">{row.title ?? '—'}</TableCell>
                                )}
                                <TableCell className="text-muted-foreground text-sm">
                                    {formatBytes(row.file_size)}
                                </TableCell>
                                <TableCell className="text-muted-foreground text-sm">
                                    {new Date(row.uploaded_at).toLocaleString()}
                                </TableCell>
                                {module.has_status && (
                                    <TableCell>
                                        <StatusBadge status={row.status} />
                                    </TableCell>
                                )}
                                <TableCell className="text-right">
                                    <TableRowActions label={row.original_name}>
                                        <DropdownMenuItem asChild>
                                            <a
                                                href={`${module.upload_base_url}/${String(row.id)}/download`}
                                            >
                                                <Download className="size-4" /> Download
                                            </a>
                                        </DropdownMenuItem>
                                        {module.has_status && canReview && (
                                            <>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    disabled={isPending(`status:${String(row.id)}`)}
                                                    onSelect={() => {
                                                        onSelectStatus({
                                                            row,
                                                            status: 'Approved',
                                                        });
                                                    }}
                                                >
                                                    <Check className="size-4" /> Approve
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    disabled={isPending(`status:${String(row.id)}`)}
                                                    onSelect={() => {
                                                        onSelectStatus({
                                                            row,
                                                            status: 'Returned',
                                                        });
                                                    }}
                                                >
                                                    <RotateCcw className="size-4" /> Return
                                                </DropdownMenuItem>
                                            </>
                                        )}
                                        {user !== null &&
                                            (user.role === 'admin' ||
                                                user.role === 'focal' ||
                                                row.uploader_id === user.id) && (
                                                <>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        disabled={isPending('delete')}
                                                        onSelect={() => {
                                                            onDeleteRow(row);
                                                        }}
                                                    >
                                                        <Trash2 className="size-4" /> Delete
                                                    </DropdownMenuItem>
                                                </>
                                            )}
                                    </TableRowActions>
                                </TableCell>
                            </TableRow>
                        ))}
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={module.has_status ? 6 : 5}
                                    className="text-muted-foreground py-10 text-center"
                                >
                                    Nothing here yet — upload the first {module.singular}.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
