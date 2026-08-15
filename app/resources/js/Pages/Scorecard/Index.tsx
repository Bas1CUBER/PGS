import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarPlus, LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';
import { TableRowActions } from '@/components/table-row-actions';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

interface Measure {
    id: number;
    impact: string;
    measure: string;
    bl: string | null;
}

interface Year {
    id: number;
    year: number;
}

interface ScorecardPageProps extends PageProps {
    measures: Measure[];
    years: Year[];
    values: Partial<Record<string, { value: string | null }>>;
}

export default function ScorecardIndex({ measures, years, values }: ScorecardPageProps) {
    const { auth } = usePage().props;
    const user = auth.user;
    const canManage = user !== null && (user.role === 'admin' || user.role === 'focal');

    const [impact, setImpact] = useState('');
    const [measure, setMeasure] = useState('');
    const [bl, setBl] = useState('');
    const [newYear, setNewYear] = useState('');
    const [measureDialogOpen, setMeasureDialogOpen] = useState(false);
    const [yearDialogOpen, setYearDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Measure | null>(null);
    const [drafts, setDrafts] = useState<Partial<Record<string, string>>>({});
    const [deleteTarget, setDeleteTarget] = useState<Year | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function createMeasure(e: { preventDefault(): void }): void {
        e.preventDefault();
        start('create-measure');
        router.post(
            '/impact-scorecard/measures',
            { impact, measure, bl },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setImpact('');
                    setMeasure('');
                    setBl('');
                    setMeasureDialogOpen(false);
                },
                onFinish: () => {
                    finish('create-measure');
                },
            },
        );
    }

    function addYear(e: { preventDefault(): void }): void {
        e.preventDefault();
        start('add-year');
        router.post(
            '/impact-scorecard/years',
            { year: newYear },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewYear('');
                    setYearDialogOpen(false);
                },
                onFinish: () => {
                    finish('add-year');
                },
            },
        );
    }

    function saveEdit(): void {
        if (editing === null) return;
        start('save-measure');
        router.put(
            `/impact-scorecard/measures/${String(editing.id)}`,
            { impact, measure, bl },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditing(null);
                },
                onFinish: () => {
                    finish('save-measure');
                },
            },
        );
    }

    function commitValue(measureId: number, yearId: number): void {
        const key = `${String(measureId)}:${String(yearId)}`;
        const draft = drafts[key];

        // Blurring an untouched input should not overwrite its saved value.
        // An empty string is still a valid draft when the user cleared the cell.
        if (draft === undefined) return;

        const action = `value:${key}`;
        start(action);
        router.put(
            `/impact-scorecard/values/${String(measureId)}/${String(yearId)}`,
            { value: draft },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDrafts((prev) => {
                        const next: Partial<Record<string, string>> = {};
                        Object.entries(prev).forEach(([draftKey, value]) => {
                            if (draftKey !== key) next[draftKey] = value;
                        });
                        return next;
                    });
                },
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    function confirmDelete(): void {
        if (deleteTarget === null) return;
        start('delete-year');
        router.delete(`/impact-scorecard/years/${String(deleteTarget.id)}`, {
            onFinish: () => {
                finish('delete-year');
                setDeleteTarget(null);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Impact Scorecard</h2>}
        >
            <Head title="Impact Scorecard" />

            <div className="space-y-6">
                {canManage && (
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            onClick={() => {
                                setMeasureDialogOpen(true);
                            }}
                        >
                            <Plus className="size-4" /> Add measure
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setYearDialogOpen(true);
                            }}
                        >
                            <CalendarPlus className="size-4" /> Add year
                        </Button>
                    </div>
                )}

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
                                                        aria-busy={
                                                            isPending('delete-year') || undefined
                                                        }
                                                        onClick={() => {
                                                            setDeleteTarget(year);
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
                                            const key = `${String(m.id)}:${String(year.id)}`;
                                            const current = values[key]?.value ?? '';

                                            return (
                                                <TableCell key={key} className="text-center">
                                                    {canManage ? (
                                                        <Input
                                                            value={drafts[key] ?? current}
                                                            onChange={(e) => {
                                                                setDrafts((prev) => ({
                                                                    ...prev,
                                                                    [key]: e.target.value,
                                                                }));
                                                            }}
                                                            onBlur={() => {
                                                                commitValue(m.id, year.id);
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
                                                            setEditing(m);
                                                            setImpact(m.impact);
                                                            setMeasure(m.measure);
                                                            setBl(m.bl ?? '');
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
            </div>

            <Dialog
                open={measureDialogOpen}
                onOpenChange={(open) => {
                    setMeasureDialogOpen(open);
                    if (!open) {
                        setImpact('');
                        setMeasure('');
                        setBl('');
                    }
                }}
            >
                <DialogContent className="pgs-modal-form-dialog">
                    <DialogHeader>
                        <DialogTitle>Add measure</DialogTitle>
                        <DialogDescription>
                            Add a measure to the impact scorecard.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={createMeasure} className="pgs-modal-form pgs-modal-form-scroll">
                        <DialogBody>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="pgs-modal-field">
                                    <label htmlFor="impact">Impact</label>
                                    <Input
                                        id="impact"
                                        value={impact}
                                        onChange={(e) => {
                                            setImpact(e.target.value);
                                        }}
                                        required
                                    />
                                </div>
                                <div className="pgs-modal-field">
                                    <label htmlFor="measure">Measure</label>
                                    <Input
                                        id="measure"
                                        value={measure}
                                        onChange={(e) => {
                                            setMeasure(e.target.value);
                                        }}
                                        required
                                    />
                                </div>
                            </div>
                            <div className="pgs-modal-field">
                                <label htmlFor="bl">Baseline</label>
                                <Input
                                    id="bl"
                                    value={bl}
                                    onChange={(e) => {
                                        setBl(e.target.value);
                                    }}
                                />
                            </div>
                        </DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setMeasureDialogOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                loading={isPending('create-measure')}
                                loadingText="Adding"
                            >
                                <Plus className="size-4" /> Add measure
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={yearDialogOpen}
                onOpenChange={(open) => {
                    setYearDialogOpen(open);
                    if (!open) setNewYear('');
                }}
            >
                <DialogContent className="pgs-modal-form-dialog">
                    <DialogHeader>
                        <DialogTitle>Add year</DialogTitle>
                        <DialogDescription>Add a target year to the scorecard.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={addYear} className="pgs-modal-form pgs-modal-form-scroll">
                        <DialogBody>
                            <div className="pgs-modal-field">
                                <label htmlFor="new-year">Year</label>
                                <Input
                                    id="new-year"
                                    type="number"
                                    min={2000}
                                    max={2100}
                                    value={newYear}
                                    onChange={(e) => {
                                        setNewYear(e.target.value);
                                    }}
                                    placeholder="e.g. 2029"
                                    required
                                />
                            </div>
                        </DialogBody>
                        <DialogFooter className="pgs-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setYearDialogOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                loading={isPending('add-year')}
                                loadingText="Adding"
                            >
                                <CalendarPlus className="size-4" /> Add year
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) setEditing(null);
                }}
            >
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
                                    setImpact(e.target.value);
                                }}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-measure">Measure</Label>
                            <Input
                                id="edit-measure"
                                value={measure}
                                onChange={(e) => {
                                    setMeasure(e.target.value);
                                }}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-bl">Baseline</Label>
                            <Input
                                id="edit-bl"
                                value={bl}
                                onChange={(e) => {
                                    setBl(e.target.value);
                                }}
                            />
                        </div>
                    </DialogBody>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setEditing(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={saveEdit}
                            loading={isPending('save-measure')}
                            loadingText="Saving"
                        >
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete scorecard year"
                description="This action permanently removes the scorecard year and its values."
                confirmationTitle="Confirm year deletion"
                confirmationDescription={`Year ${String(deleteTarget?.year ?? '')} and its values will be removed.`}
                onConfirm={confirmDelete}
                loading={isPending('delete-year')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
