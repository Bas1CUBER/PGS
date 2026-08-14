import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

import { Timer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { PageProps } from '@/types';

interface DeadlineRow {
    role: string;
    enabled: boolean;
    end_time: string | null;
    message: string | null;
}

interface DeadlinesPageProps extends PageProps {
    deadlines: DeadlineRow[];
}

export default function DeadlinesIndex({ deadlines }: DeadlinesPageProps) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Deadlines</h2>}
        >
            <Head title="Deadlines" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="text-muted-foreground flex items-center gap-2">
                    <Timer className="size-5" />
                    <p className="text-sm">
                        When a deadline is enabled, submissions for that role are blocked after the
                        end time.
                    </p>
                </div>

                {deadlines.map((deadline) => (
                    <DeadlineCard key={deadline.role} deadline={deadline} />
                ))}
            </div>
        </AuthenticatedLayout>
    );
}

function DeadlineCard({ deadline }: { deadline: DeadlineRow }) {
    const { data, setData, put, processing, errors } = useForm({
        enabled: deadline.enabled,
        end_time: deadline.end_time?.slice(0, 16) ?? '',
        message: deadline.message ?? '',
    });

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        put(`/deadlines/${deadline.role}`);
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="capitalize">{deadline.role}</CardTitle>
                    <Badge variant={data.enabled ? 'warning' : 'outline'}>
                        {data.enabled ? 'Enabled' : 'Disabled'}
                    </Badge>
                </div>
                <CardDescription>
                    {deadline.end_time !== null
                        ? `Current window ends ${new Date(deadline.end_time).toLocaleString()}`
                        : 'No end time set'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.enabled}
                            onChange={(e) => {
                                setData('enabled', e.target.checked);
                            }}
                        />
                        Enforce submission deadline
                    </label>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor={`${deadline.role}-end`}>End time</Label>
                            <Input
                                id={`${deadline.role}-end`}
                                type="datetime-local"
                                value={data.end_time}
                                onChange={(e) => {
                                    setData('end_time', e.target.value);
                                }}
                            />
                            {errors.end_time && (
                                <p className="text-destructive text-sm">{errors.end_time}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`${deadline.role}-message`}>Banner message</Label>
                            <Input
                                id={`${deadline.role}-message`}
                                value={data.message}
                                onChange={(e) => {
                                    setData('message', e.target.value);
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
                            Save deadline
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
