import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { TableRowActions } from '@/components/table-row-actions';
import type { SectorRow, SectorShowPageProps } from './types';

interface IndicatorsTableCardProps {
    rows: SectorShowPageProps['rows'];
    filteredRows: SectorRow[];
    filter: string;
    onFilterChange: (value: string) => void;
    canManage: boolean;
    isPending: (action: string) => boolean;
    onOpenAdd: () => void;
    onOpenEdit: (row: SectorRow) => void;
    onDeleteRow: (row: SectorRow) => void;
}

export function IndicatorsTableCard({
    rows,
    filteredRows,
    filter,
    onFilterChange,
    canManage,
    isPending,
    onOpenAdd,
    onOpenEdit,
    onDeleteRow,
}: IndicatorsTableCardProps) {
    return (
        <Card>
            <CardHeader className="table-toolbar">
                <div className="table-toolbar-heading">
                    <CardTitle>Indicators</CardTitle>
                    <p className="table-toolbar-meta">
                        {rows.data.length} {rows.data.length === 1 ? 'indicator' : 'indicators'}
                    </p>
                </div>
                <div className="table-toolbar-actions">
                    <div className="table-search-shell">
                        <Search className="table-search-icon" aria-hidden="true" />
                        <Input
                            className="table-search"
                            value={filter}
                            onChange={(event) => {
                                onFilterChange(event.target.value);
                            }}
                            placeholder="Filter indicators..."
                            aria-label="Filter indicators"
                        />
                    </div>
                    <Button
                        type="button"
                        onClick={() => {
                            onOpenAdd();
                        }}
                    >
                        <Plus className="size-4" /> Add indicator
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Category</TableHead>
                            <TableHead>Year</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filteredRows.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell className="font-medium">{row.category}</TableCell>
                                <TableCell className="text-muted-foreground text-sm">
                                    {row.year}
                                </TableCell>
                                <TableCell className="text-sm">{row.description}</TableCell>
                                <TableCell className="text-right">
                                    <TableRowActions label={row.description}>
                                        <DropdownMenuItem
                                            onSelect={() => {
                                                onOpenEdit(row);
                                            }}
                                        >
                                            <Pencil className="size-4" /> Edit
                                        </DropdownMenuItem>
                                        {canManage && (
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
                        {filteredRows.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="text-muted-foreground py-10 text-center"
                                >
                                    {rows.data.length === 0
                                        ? 'No indicators yet.'
                                        : 'No indicators match your filter.'}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
