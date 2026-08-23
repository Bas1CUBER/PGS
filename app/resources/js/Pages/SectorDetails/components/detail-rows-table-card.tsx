import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DetailRowActions } from './detail-row-actions';
import type { SectorDetailRow } from './types';

interface DetailRowsTableCardProps {
    label: string;
    columns: string[];
    rows: SectorDetailRow[];
    editableColumns: string[];
    canManage: boolean;
    lockColumn: string | null;
    isPending: (action: string) => boolean;
    onCellBlur: (row: SectorDetailRow, column: string, value: string) => void;
    onCommitRow: (id: number) => void;
    onToggleLock: (id: number) => void;
    onDeleteRequest: (id: number) => void;
}

export function DetailRowsTableCard({
    label,
    columns,
    rows,
    editableColumns,
    canManage,
    lockColumn,
    isPending,
    onCellBlur,
    onCommitRow,
    onToggleLock,
    onDeleteRequest,
}: DetailRowsTableCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{label}</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead key={column} className="whitespace-nowrap">
                                    {column.replace(/_/g, ' ')}
                                </TableHead>
                            ))}
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                {columns.map((column) => (
                                    <TableCell key={column} className="whitespace-nowrap">
                                        {editableColumns.includes(column) ? (
                                            <input
                                                type="text"
                                                defaultValue={row[column] ?? ''}
                                                disabled={
                                                    (Boolean(row.locked) && !canManage) ||
                                                    isPending(`save:${String(row.id)}`)
                                                }
                                                onBlur={(e) => {
                                                    onCellBlur(row, column, e.target.value);
                                                }}
                                                className="hover:border-input focus:border-input focus:bg-background h-8 w-full min-w-24 rounded-md border border-transparent bg-transparent px-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                                            />
                                        ) : (
                                            <span>{row[column] ?? '—'}</span>
                                        )}
                                    </TableCell>
                                ))}
                                <TableCell className="text-right">
                                    <DetailRowActions
                                        row={row}
                                        canManage={canManage}
                                        lockColumn={lockColumn}
                                        isPending={isPending}
                                        onSave={() => {
                                            onCommitRow(Number(row.id));
                                        }}
                                        onToggleLock={() => {
                                            onToggleLock(Number(row.id));
                                        }}
                                        onDelete={() => {
                                            onDeleteRequest(Number(row.id));
                                        }}
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                        {rows.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length + 1}
                                    className="text-muted-foreground py-10 text-center"
                                >
                                    No rows in this table yet.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
