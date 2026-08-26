import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { PageProps } from '@/types';
import { urls } from '@/lib/urls';
import { TableRowActions } from '@/components/table-row-actions';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

interface LegacyForm {
    slug: string;
    title: string;
    description: string;
    columns: string[];
    source_note: string;
    editable: boolean;
}

interface AnnexRow {
    id: number;
    values: (string | null)[];
}

interface LegacyFormsProps extends PageProps {
    form: LegacyForm;
    rows: AnnexRow[] | (string | null)[][];
    downloadUrl: string;
    canManage: boolean;
}

function isAnnexRowValue(row: unknown): row is AnnexRow {
    return (
        typeof row === 'object' &&
        row !== null &&
        !Array.isArray(row) &&
        'values' in row &&
        'id' in row
    );
}

export default function LegacyFormShow({ form, rows, downloadUrl, canManage }: LegacyFormsProps) {
    const { data, setData, post, put, processing, errors, reset } = useForm<{
        values: (string | null)[];
    }>({
        values: form.columns.map(() => ''),
    });
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<AnnexRow | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function saveRow(): void {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setEditingId(null);
            },
        };
        if (editingId === null) post(urls.annex.store(form.slug), options);
        else put(urls.annex.update(form.slug, String(editingId)), options);
    }

    function editRow(row: AnnexRow): void {
        setEditingId(row.id);
        setData({ values: row.values });
    }

    function confirmDelete(): void {
        if (deleteTarget === null) return;
        start('delete');
        router.delete(urls.annex.destroy(form.slug, String(deleteTarget.id)), {
            preserveScroll: true,
            onFinish: () => {
                finish('delete');
                setDeleteTarget(null);
            },
        });
    }
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">{form.title}</h2>}
        >
            <Head title={form.title} />
            <div className="mx-auto max-w-7xl space-y-6">
                <Card className="pgs-template-card">
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <p className="pgs-section-kicker">Performance assessment workspace</p>
                            <CardTitle className="mt-1 flex items-center gap-2">
                                <FileSpreadsheet className="size-5" /> {form.title}
                            </CardTitle>
                            <p className="text-muted-foreground mt-2 text-sm">{form.description}</p>
                        </div>
                        <Badge variant="outline">
                            {form.editable ? 'Editable register' : 'Derived register'}
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button asChild variant="outline">
                                <a href={downloadUrl}>
                                    <Download className="size-4" /> Download CSV
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {form.editable && canManage && (
                    <Card className="kinetic-template-card">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Plus className="size-4" />{' '}
                                {editingId === null
                                    ? 'Add register row'
                                    : `Edit row #${String(editingId)}`}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <div className="border-destructive/50 bg-destructive/10 text-destructive rounded-md border p-3 text-sm">
                                    {Object.entries(errors).map(([key, message]) => (
                                        <p key={key}>{message}</p>
                                    ))}
                                </div>
                            )}
                            <div className="grid gap-3 md:grid-cols-2">
                                {form.columns.map((column, index) => (
                                    <div className="space-y-2" key={column}>
                                        <label
                                            className="text-xs font-semibold"
                                            htmlFor={`annex-${String(index)}`}
                                        >
                                            {column}
                                        </label>
                                        <Input
                                            id={`annex-${String(index)}`}
                                            value={data.values[index] ?? ''}
                                            onChange={(event) => {
                                                const next = [...data.values];
                                                next[index] = event.target.value;
                                                setData({ values: next });
                                            }}
                                        />
                                    </div>
                                ))}
                            </div>
                            <div className="flex justify-end gap-2">
                                {editingId !== null && (
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setEditingId(null);
                                            reset();
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                )}
                                <Button onClick={saveRow} disabled={processing}>
                                    <Save className="size-4" />{' '}
                                    {processing ? 'Saving...' : 'Save row'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Register</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div
                            data-slot="table-container"
                            className="relative w-full overflow-x-auto"
                        >
                            <table
                                data-slot="table"
                                className="w-full min-w-[760px] text-left text-sm"
                            >
                                <thead data-slot="table-header">
                                    <tr data-slot="table-row" className="border-b">
                                        {form.columns.map((column) => (
                                            <th
                                                key={column}
                                                data-slot="table-head"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                {column}
                                            </th>
                                        ))}
                                        {form.editable && canManage && (
                                            <th
                                                data-slot="table-head"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                Actions
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody data-slot="table-body">
                                    {rows.map((row, rowIndex) => {
                                        const annex =
                                            form.editable && isAnnexRowValue(row) ? row : null;
                                        const rowValues: (string | null)[] =
                                            annex !== null
                                                ? annex.values
                                                : Array.isArray(row)
                                                  ? row
                                                  : [];
                                        const rowId = annex !== null ? annex.id : rowIndex;

                                        return (
                                            <tr
                                                key={rowId}
                                                data-slot="table-row"
                                                className="border-b last:border-0"
                                            >
                                                {form.columns.map((_, columnIndex) => (
                                                    <td
                                                        key={columnIndex}
                                                        data-slot="table-cell"
                                                        className="px-4 py-3 align-top"
                                                    >
                                                        {rowValues[columnIndex] ?? '—'}
                                                    </td>
                                                ))}
                                                {form.editable && canManage && (
                                                    <td
                                                        data-slot="table-cell"
                                                        className="px-4 py-3"
                                                    >
                                                        <TableRowActions
                                                            label={`Row #${String(rowId)}`}
                                                        >
                                                            <DropdownMenuItem
                                                                onSelect={() => {
                                                                    if (annex !== null)
                                                                        editRow(annex);
                                                                }}
                                                            >
                                                                <Pencil className="size-4" /> Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                disabled={isPending('delete')}
                                                                onSelect={() => {
                                                                    setDeleteTarget(annex);
                                                                }}
                                                            >
                                                                <Trash2 className="size-4" /> Delete
                                                            </DropdownMenuItem>
                                                        </TableRowActions>
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })}
                                    {rows.length === 0 && (
                                        <tr data-slot="table-row">
                                            <td
                                                colSpan={
                                                    form.columns.length +
                                                    (form.editable && canManage ? 1 : 0)
                                                }
                                                data-slot="table-cell"
                                                className="pgs-empty-state text-muted-foreground px-4 py-12 text-center"
                                            >
                                                No records have been added to this Annex yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-dashed">
                    <CardContent className="text-muted-foreground py-5 text-sm">
                        {form.source_note}
                    </CardContent>
                </Card>
            </div>

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete annex row"
                description="This action permanently removes the annex row."
                confirmationTitle="Confirm row deletion"
                confirmationDescription={`Annex row #${String(deleteTarget?.id ?? '')} will be removed.`}
                onConfirm={confirmDelete}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
