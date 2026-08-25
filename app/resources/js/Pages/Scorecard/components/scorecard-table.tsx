import { LoaderCircle, Pencil, Trash2 } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
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
import { valueKey } from './lib';
import type { Measure, Year } from './types';

interface ScorecardTableProps {
    measures: Measure[];
    years: Year[];
    values: Partial<Record<string, { value: string | null }>>;
    drafts: Partial<Record<string, string>>;
    canManage: boolean;
    isPending: (action: string) => boolean;
    onDraftChange: (key: string, value: string) => void;
    onCommitValue: (measureId: number, yearId: number) => void;
    onEditMeasure: (measure: Measure) => void;
    onDeleteYear: (year: Year) => void;
}

export function ScorecardTable({
    measures,
    years,
    values,
    drafts,
    canManage,
    isPending,
    onDraftChange,
    onCommitValue,
    onEditMeasure,
    onDeleteYear,
}: ScorecardTableProps) {
    return (
        <Card className="pgs-scorecard-table-card">
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Impact</TableHead>
                            <TableHead>Measure</TableHead>
                            <TableHead>Baseline</TableHead>
                            {years.map((year) => (
                                <TableHead key={year.id} className="text-center">
                                    <div className="flex items-center justify-center gap-1">
                                        {year.year}
                                        {canManage && (
                                            <button
                                                type="button"
                                                aria-label={`Remove ${String(year.year)}`}
                                                disabled={isPending('delete-year')}
                                                aria-busy={isPending('delete-year') || undefined}
                                                onClick={() => {
                                                    onDeleteYear(year);
                                                }}
                                                className="text-destructive hover:text-destructive"
                                            >
                                                {isPending('delete-year') ? (
                                                    <LoaderCircle className="loading-button-spinner size-3" />
                                                ) : (
                                                    <Trash2 className="size-3" />
                                                )}
                                            </button>
                                        )}
                                    </div>
                                </TableHead>
                            ))}
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {measures.map((m) => (
                            <TableRow key={m.id}>
                                <TableCell>{m.impact}</TableCell>
                                <TableCell className="text-muted-foreground text-sm">
                                    {m.measure}
                                </TableCell>
                                <TableCell className="text-sm">{m.bl ?? '-'}</TableCell>
                                {years.map((year) => {
                                    const key = valueKey(m.id, year.id);
                                    const current = values[key]?.value ?? '';

                                    return (
                                        <TableCell key={key} className="text-center">
                                            {canManage ? (
                                                <Input
                                                    value={drafts[key] ?? current}
                                                    onChange={(e) => {
                                                        onDraftChange(key, e.target.value);
                                                    }}
                                                    onBlur={() => {
                                                        onCommitValue(m.id, year.id);
                                                    }}
                                                    disabled={isPending(`value:${key}`)}
                                                    className="pgs-scorecard-value-input h-8 w-24 text-center text-sm"
                                                />
                                            ) : (
                                                <span>{current || '-'}</span>
                                            )}
                                        </TableCell>
                                    );
                                })}
                                <TableCell className="text-right">
                                    {canManage && (
                                        <TableRowActions label={m.measure}>
                                            <DropdownMenuItem
                                                onSelect={() => {
                                                    onEditMeasure(m);
                                                }}
                                            >
                                                <Pencil className="size-4" /> Edit
                                            </DropdownMenuItem>
                                        </TableRowActions>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {measures.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={3 + years.length + 1}
                                    className="text-muted-foreground py-10 text-center"
                                >
                                    No measures yet - add the first one.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
