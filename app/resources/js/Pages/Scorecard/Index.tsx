import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarPlus, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

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
    const [editing, setEditing] = useState<Measure | null>(null);
    const [drafts, setDrafts] = useState<Record<string, string>>({});

    function createMeasure(e: { preventDefault(): void }): void {
        e.preventDefault();
        router.post(
            '/impact-scorecard/measures',
            { impact, measure, bl },
            { preserveScroll: true },
        );
        setImpact('');
        setMeasure('');
        setBl('');
    }

    function addYear(e: { preventDefault(): void }): void {
        e.preventDefault();
        router.post('/impact-scorecard/years', { year: newYear }, { preserveScroll: true });
        setNewYear('');
    }

    function saveEdit(): void {
        if (editing === null) return;
        router.put(
            `/impact-scorecard/measures/${String(editing.id)}`,
            { impact, measure, bl },
            { preserveScroll: true },
        );
        setEditing(null);
    }

    function commitValue(measureId: number, yearId: number): void {
        const key = `${String(measureId)}:${String(yearId)}`;
        router.put(
            `/impact-scorecard/values/${String(measureId)}/${String(yearId)}`,
            { value: drafts[key] ?? '' },
            { preserveScroll: true },
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Impact Scorecard</h2>}
        >
            <Head title="Impact Scorecard" />

            <div className="space-y-6">
                {canManage && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Add measure</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={createMeasure} className="space-y-3">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="impact">Impact</Label>
                                            <Input
                                                id="impact"
                                                value={impact}
                                                onChange={(e) => {
                                                    setImpact(e.target.value);
                                                }}
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="measure">Measure</Label>
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
                                    <div className="space-y-2">
                                        <Label htmlFor="bl">Baseline</Label>
                                        <Input
                                            id="bl"
                                            value={bl}
                                            onChange={(e) => {
                                                setBl(e.target.value);
                                            }}
                                        />
                                    </div>
                                    <div className="flex justify-end">
                                        <Button type="submit">
                                            <Plus className="size-4" />
                                            Add measure
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Add year</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={addYear}
                                    className="flex max-w-xs items-center gap-2"
                                >
                                    <Input
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
                                    <Button type="submit">
                                        <CalendarPlus className="size-4" />
                                        Add
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
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
                                                            onClick={() => {
                                                                router.delete(
                                                                    `/impact-scorecard/years/${String(year.id)}`,
                                                                );
                                                            }}
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="size-3" />
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
                                                                className="h-8 w-24 text-center text-sm"
                                                            />
                                                        ) : (
                                                            <span>{current || '-'}</span>
                                                        )}
                                                    </TableCell>
                                                );
                                            })}
                                            <TableCell className="text-right">
                                                {canManage && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setEditing(m);
                                                            setImpact(m.impact);
                                                            setMeasure(m.measure);
                                                            setBl(m.bl ?? '');
                                                        }}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
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
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) setEditing(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit measure</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
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
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setEditing(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button onClick={saveEdit}>Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
