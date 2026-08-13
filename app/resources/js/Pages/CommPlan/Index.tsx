import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';

interface CommPlanRow {
    id: number;
    objective: string;
    target_audience: string | null;
    message: string | null;
    channel: string | null;
    timeframe: string | null;
    requirements: string | null;
    responsible_person: string | null;
    status: string;
}

interface CommPlanPageProps extends PageProps {
    rows: CommPlanRow[];
}

const statusStyles: Record<string, string> = {
    Completed: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    Ongoing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    'Not Accomplished/Started': 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

export default function CommPlanIndex({ rows }: CommPlanPageProps) {
    const [objective, setObjective] = useState('');
    const [channel, setChannel] = useState('');
    const [timeframe, setTimeframe] = useState('');
    const [responsible, setResponsible] = useState('');
    const [message, setMessage] = useState('');
    const [audience, setAudience] = useState('');
    const [requirements, setRequirements] = useState('');
    const [editing, setEditing] = useState<CommPlanRow | null>(null);

    function create(e: { preventDefault(): void }): void {
        e.preventDefault();
        router.post(
            '/communication-plan',
            {
                objective,
                channel,
                timeframe,
                responsible_person: responsible,
                message,
                target_audience: audience,
                requirements,
            },
            { preserveScroll: true },
        );
        setObjective('');
        setChannel('');
        setTimeframe('');
        setResponsible('');
        setMessage('');
        setAudience('');
        setRequirements('');
    }

    function openEdit(row: CommPlanRow): void {
        setEditing(row);
        setObjective(row.objective);
        setChannel(row.channel ?? '');
        setTimeframe(row.timeframe ?? '');
        setResponsible(row.responsible_person ?? '');
        setMessage(row.message ?? '');
        setAudience(row.target_audience ?? '');
        setRequirements(row.requirements ?? '');
    }

    function saveEdit(e: { preventDefault(): void }): void {
        e.preventDefault();
        if (editing === null) return;
        router.put(
            `/communication-plan/${String(editing.id)}`,
            {
                objective,
                channel,
                timeframe,
                responsible_person: responsible,
                message,
                target_audience: audience,
                requirements,
            },
            { preserveScroll: true },
        );
        setEditing(null);
    }

    function setStatus(id: number, status: string): void {
        const row = rows.find((r) => r.id === id);
        if (row === undefined) return;
        router.put(
            `/communication-plan/${String(id)}`,
            {
                objective: row.objective,
                target_audience: row.target_audience,
                message: row.message,
                channel: row.channel,
                timeframe: row.timeframe,
                requirements: row.requirements,
                responsible_person: row.responsible_person,
                status,
            },
            { preserveScroll: true },
        );
    }

    const formFields = (
        <>
            <div className="space-y-2">
                <Label htmlFor="objective">Objective</Label>
                <textarea
                    id="objective"
                    value={objective}
                    onChange={(e) => {
                        setObjective(e.target.value);
                    }}
                    rows={2}
                    className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                    required
                />
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-2">
                    <Label htmlFor="channel">Channel</Label>
                    <Input
                        id="channel"
                        value={channel}
                        onChange={(e) => {
                            setChannel(e.target.value);
                        }}
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="timeframe">Timeframe</Label>
                    <Input
                        id="timeframe"
                        value={timeframe}
                        onChange={(e) => {
                            setTimeframe(e.target.value);
                        }}
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="responsible">Responsible person</Label>
                    <Input
                        id="responsible"
                        value={responsible}
                        onChange={(e) => {
                            setResponsible(e.target.value);
                        }}
                    />
                </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="audience">Target audience</Label>
                    <Input
                        id="audience"
                        value={audience}
                        onChange={(e) => {
                            setAudience(e.target.value);
                        }}
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="requirements">Requirements</Label>
                    <Input
                        id="requirements"
                        value={requirements}
                        onChange={(e) => {
                            setRequirements(e.target.value);
                        }}
                    />
                </div>
            </div>
            <div className="space-y-2">
                <Label htmlFor="message">Message</Label>
                <textarea
                    id="message"
                    value={message}
                    onChange={(e) => {
                        setMessage(e.target.value);
                    }}
                    rows={2}
                    className="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                />
            </div>
        </>
    );

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Communication Plan</h2>}
        >
            <Head title="Communication Plan" />

            <div className="mx-auto max-w-4xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{editing === null ? 'Add a row' : 'Edit row'}</CardTitle>
                        <CardDescription>
                            {editing === null
                                ? 'Define communication objectives, channels and responsibilities.'
                                : `Editing row #${String(editing.id)}.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={editing === null ? create : saveEdit} className="space-y-4">
                            {formFields}
                            <div className="flex justify-end gap-2">
                                {editing !== null && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setEditing(null);
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                )}
                                <Button type="submit">
                                    <Plus className="size-4" />
                                    {editing === null ? 'Add row' : 'Save changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <ul className="divide-y">
                            {rows.length === 0 && (
                                <li className="text-muted-foreground py-10 text-center">
                                    No communication plan rows yet.
                                </li>
                            )}
                            {rows.map((row) => (
                                <li key={row.id} className="px-6 py-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">{row.objective}</p>
                                            {row.target_audience !== null && (
                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    Audience: {row.target_audience}
                                                </p>
                                            )}
                                            {row.message !== null && (
                                                <p className="text-muted-foreground mt-0.5 text-sm">
                                                    {row.message}
                                                </p>
                                            )}
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {[
                                                    row.channel,
                                                    row.timeframe,
                                                    row.responsible_person,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' Ã‚Â· ') || 'Ã¢â‚¬â€'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <select
                                                value={row.status}
                                                onChange={(e) => {
                                                    setStatus(row.id, e.target.value);
                                                }}
                                                className="border-input bg-background h-9 rounded-md border px-2 text-xs"
                                                aria-label="Row status"
                                            >
                                                <option>Not Accomplished/Started</option>
                                                <option>Ongoing</option>
                                                <option>Completed</option>
                                            </select>
                                            <Badge
                                                variant="outline"
                                                className={statusStyles[row.status] ?? ''}
                                            >
                                                {row.status}
                                            </Badge>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    openEdit(row);
                                                }}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => {
                                                    router.delete(
                                                        `/communication-plan/${String(row.id)}`,
                                                    );
                                                }}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
