import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface DeliverableDetail {
    id: number;
    title: string | null;
    form_type: string | null;
    focal_person: string | null;
    division: string | null;
    target_date: string | null;
    status: string | null;
    actual_date: string | null;
    mov_file: string | null;
}

interface DeliverableEditPageProps extends PageProps {
    deliverable: DeliverableDetail;
    statuses: string[];
}

const statusOrder = ['Not Yet Started', 'Ongoing', 'Accomplished'];

export default function DeliverableEdit({ deliverable, statuses }: DeliverableEditPageProps) {
    const { data, setData, put, processing, errors } = useForm({
        title: deliverable.title ?? '',
        form_type: deliverable.form_type ?? '',
        focal_person: deliverable.focal_person ?? '',
        division: deliverable.division ?? '',
        target_date: deliverable.target_date ?? '',
        status: deliverable.status ?? 'Not Yet Started',
        actual_date: deliverable.actual_date ?? '',
    });

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        put(`/deliverables/${String(deliverable.id)}`);
    }

    function move(direction: 'up' | 'down'): void {
        const index = statusOrder.indexOf(data.status);
        const target = direction === 'up' ? index - 1 : index + 1;

        if (target < 0 || target >= statusOrder.length) {
            return;
        }

        router.post(
            `/deliverables/${String(deliverable.id)}/status`,
            { to: statusOrder[target] },
            { preserveScroll: true },
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Edit deliverable</h2>}
        >
            <Head title={`Edit ${deliverable.title ?? 'deliverable'}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href="/deliverables">
                        <ArrowLeft className="size-4" />
                        Back to deliverables
                    </Link>
                </Button>

                <Card>
                    <CardHeader>
                        <CardTitle>Deliverable</CardTitle>
                        <CardDescription>
                            Update the record; workflow transitions use the status stepper.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => {
                                        setData('title', e.target.value);
                                    }}
                                    required
                                />
                                {errors.title && (
                                    <p className="text-destructive text-sm">{errors.title}</p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="form_type">Form type</Label>
                                    <Input
                                        id="form_type"
                                        value={data.form_type}
                                        onChange={(e) => {
                                            setData('form_type', e.target.value);
                                        }}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="focal_person">Focal person</Label>
                                    <Input
                                        id="focal_person"
                                        value={data.focal_person}
                                        onChange={(e) => {
                                            setData('focal_person', e.target.value);
                                        }}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="division">Division</Label>
                                    <Input
                                        id="division"
                                        value={data.division}
                                        onChange={(e) => {
                                            setData('division', e.target.value);
                                        }}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="target_date">Target date</Label>
                                    <Input
                                        id="target_date"
                                        type="date"
                                        value={data.target_date}
                                        onChange={(e) => {
                                            setData('target_date', e.target.value);
                                        }}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) => {
                                            setData('status', e.target.value);
                                        }}
                                        className="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        {statuses.map((status) => (
                                            <option key={status} value={status}>
                                                {status}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="actual_date">Actual date</Label>
                                    <Input
                                        id="actual_date"
                                        type="date"
                                        value={data.actual_date}
                                        onChange={(e) => {
                                            setData('actual_date', e.target.value);
                                        }}
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Savingâ€¦' : 'Save changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Workflow</CardTitle>
                        <CardDescription>
                            Move the deliverable through its status lifecycle.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-center gap-2">
                            {statusOrder.map((status, index) => {
                                const currentIndex = statusOrder.indexOf(data.status);
                                const reached = index <= currentIndex;

                                return (
                                    <div key={status} className="flex items-center gap-2">
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                'px-3 py-1',
                                                reached &&
                                                    'bg-primary text-primary-foreground border-primary',
                                            )}
                                        >
                                            <Check
                                                className={cn(
                                                    'mr-1 size-3',
                                                    !reached && 'opacity-0',
                                                )}
                                            />
                                            {status}
                                        </Badge>
                                        {index < statusOrder.length - 1 && (
                                            <ArrowRight className="text-muted-foreground size-4" />
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        <div className="flex justify-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={data.status === statusOrder[0]}
                                onClick={() => {
                                    move('up');
                                }}
                            >
                                <ArrowLeft className="size-4" />
                                Previous status
                            </Button>
                            <Button
                                size="sm"
                                disabled={data.status === statusOrder[statusOrder.length - 1]}
                                onClick={() => {
                                    move('down');
                                }}
                            >
                                Next status
                                <ArrowRight className="size-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
