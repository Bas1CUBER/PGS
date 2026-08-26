import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { urls } from '@/lib/urls';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';

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

export default function DeliverableEdit({ deliverable }: DeliverableEditPageProps) {
    const { data, setData, put, processing, errors } = useForm({
        title: deliverable.title ?? '',
        form_type: deliverable.form_type ?? '',
        focal_person: deliverable.focal_person ?? '',
        division: deliverable.division ?? '',
        target_date: deliverable.target_date ?? '',
        status: deliverable.status ?? 'Not Yet Started',
        actual_date: deliverable.actual_date ?? '',
    });
    const { isPending, start, finish } = usePendingAction();

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        put(urls.deliverables.update(String(deliverable.id)));
    }

    function move(direction: 'up' | 'down'): void {
        const index = statusOrder.indexOf(data.status);
        const target = direction === 'up' ? index - 1 : index + 1;

        if (target < 0 || target >= statusOrder.length) {
            return;
        }

        const action = `move:${direction}`;
        start(action);
        router.post(
            urls.deliverables.status(String(deliverable.id)),
            { to: statusOrder[target] },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Edit deliverable</h2>}
        >
            <Head title={`Edit ${deliverable.title ?? 'deliverable'}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href={urls.deliverables.index}>
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
                                    <div className="border-border bg-muted flex min-h-10 items-center rounded-[var(--kinetic-radius-control)] border px-3 text-sm shadow-[var(--shadow-inset)]">
                                        {data.status}
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Use the workflow controls below to change status.
                                    </p>
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
                                <Button
                                    type="submit"
                                    loading={processing}
                                    loadingText="Saving"
                                    disabled={processing}
                                >
                                    Save changes
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
                                loading={isPending('move:up')}
                                loadingText="Moving"
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
                                loading={isPending('move:down')}
                                loadingText="Moving"
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
