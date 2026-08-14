import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Download, Plus, Save, Trash2, Target } from 'lucide-react';
import { useState, type ReactElement } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePendingAction } from '@/hooks/use-pending-action';
import type { PageProps } from '@/types';

interface OpcrRow {
    id: number;
    strategic_goal: string | null;
    success_indicator: string;
    division_accountable: string;
    annual_target: string | null;
    quarter1_target: string | null;
    quarter2_target: string | null;
    quarter3_target: string | null;
    quarter4_target: string | null;
    remarks: string | null;
}
interface OpcrProps extends PageProps {
    rows: OpcrRow[];
    exportUrl: string;
}
type FormData = Omit<OpcrRow, 'id'>;

const blank: FormData = {
    strategic_goal: '',
    success_indicator: '',
    division_accountable: '',
    annual_target: '',
    quarter1_target: '',
    quarter2_target: '',
    quarter3_target: '',
    quarter4_target: '',
    remarks: '',
};

export default function Opcr({ rows, exportUrl }: OpcrProps) {
    const [data, setData] = useState<FormData>(blank);
    const [editingId, setEditingId] = useState<number | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function save(): void {
        const action = editingId === null ? 'create' : `save:${String(editingId)}`;
        start(action);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setData(blank);
                setEditingId(null);
            },
            onFinish: () => {
                finish(action);
            },
        };
        if (editingId === null) router.post('/opcr', data, options);
        else router.put(`/opcr/${String(editingId)}`, data, options);
    }

    function edit(row: OpcrRow): void {
        const { id, ...values } = row;
        setEditingId(id);
        setData({ ...blank, ...values });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function remove(id: number): void {
        const action = `delete:${String(id)}`;
        start(action);
        router.delete(`/opcr/${String(id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish(action);
            },
        });
    }

    function field(name: keyof FormData, label: string): ReactElement {
        return (
            <div className="space-y-2">
                <label className="text-xs font-semibold" htmlFor={`opcr-${name}`}>
                    {label}
                </label>
                <Input
                    id={`opcr-${name}`}
                    value={data[name] ?? ''}
                    onChange={(event) => {
                        setData({ ...data, [name]: event.target.value });
                    }}
                />
            </div>
        );
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl leading-tight font-semibold">OPCR</h2>}>
            <Head title="OPCR" />
            <div className="mx-auto max-w-7xl space-y-6">
                <Card className="pgs-template-card">
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <p className="pgs-section-kicker">Performance assessment</p>
                            <CardTitle className="mt-1 flex items-center gap-2">
                                <Target className="size-5" /> Office Performance Commitment and
                                Review
                            </CardTitle>
                            <p className="text-muted-foreground mt-2 text-sm">
                                Maintain annual commitments and quarterly targets for the operating
                                plan.
                            </p>
                        </div>
                        <Button asChild variant="outline">
                            <a href={exportUrl}>
                                <Download className="size-4" /> Export CSV
                            </a>
                        </Button>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="size-4" />{' '}
                            {editingId === null
                                ? 'Add target'
                                : `Edit target #${String(editingId)}`}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 md:grid-cols-2">
                            {field('strategic_goal', 'Strategic goal')}
                            {field('success_indicator', 'Success indicator')}
                            {field('division_accountable', 'Division accountable')}
                            {field('annual_target', 'Annual target')}
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {field('quarter1_target', 'Q1 target')}
                            {field('quarter2_target', 'Q2 target')}
                            {field('quarter3_target', 'Q3 target')}
                            {field('quarter4_target', 'Q4 target')}
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs font-semibold" htmlFor="opcr-remarks">
                                Remarks
                            </label>
                            <textarea
                                id="opcr-remarks"
                                value={data.remarks ?? ''}
                                onChange={(event) => {
                                    setData({ ...data, remarks: event.target.value });
                                }}
                                rows={3}
                                className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            {editingId !== null && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setEditingId(null);
                                        setData(blank);
                                    }}
                                >
                                    Cancel
                                </Button>
                            )}
                            <Button
                                onClick={save}
                                loading={isPending(
                                    editingId === null ? 'create' : `save:${String(editingId)}`,
                                )}
                                loadingText="Saving"
                            >
                                <Save className="size-4" /> Save target
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Target register</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div
                            data-slot="table-container"
                            className="relative w-full overflow-x-auto"
                        >
                            <table
                                data-slot="table"
                                className="w-full min-w-[1200px] text-left text-sm"
                            >
                                <thead data-slot="table-header">
                                    <tr data-slot="table-row" className="border-b">
                                        {[
                                            'Strategic goal',
                                            'Success indicator',
                                            'Division accountable',
                                            'Annual',
                                            'Q1',
                                            'Q2',
                                            'Q3',
                                            'Q4',
                                            'Remarks',
                                            'Actions',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                data-slot="table-head"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody data-slot="table-body">
                                    {rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            data-slot="table-row"
                                            className="border-b last:border-0"
                                        >
                                            {(
                                                [
                                                    'strategic_goal',
                                                    'success_indicator',
                                                    'division_accountable',
                                                    'annual_target',
                                                    'quarter1_target',
                                                    'quarter2_target',
                                                    'quarter3_target',
                                                    'quarter4_target',
                                                    'remarks',
                                                ] as const
                                            ).map((key) => (
                                                <td
                                                    key={key}
                                                    data-slot="table-cell"
                                                    className="max-w-[260px] px-4 py-3 align-top"
                                                >
                                                    {row[key] ?? '—'}
                                                </td>
                                            ))}
                                            <td data-slot="table-cell" className="px-4 py-3">
                                                <div className="flex gap-1">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            edit(row);
                                                        }}
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        aria-label="Delete target"
                                                        loading={isPending(
                                                            `delete:${String(row.id)}`,
                                                        )}
                                                        onClick={() => {
                                                            remove(row.id);
                                                        }}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {rows.length === 0 && (
                                        <tr data-slot="table-row">
                                            <td
                                                colSpan={10}
                                                data-slot="table-cell"
                                                className="pgs-empty-state text-muted-foreground px-4 py-12 text-center"
                                            >
                                                No OPCR targets yet. Add the first target above.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
