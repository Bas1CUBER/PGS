import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

import { ArrowLeft, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { PageProps } from '@/types';
import { urls } from '@/lib/urls';

interface DeliverableFormPageProps extends PageProps {
    statuses: string[];
}

export default function DeliverableCreate({ statuses }: DeliverableFormPageProps) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        form_type: '',
        focal_person: '',
        division: '',
        target_date: '',
        status: statuses[0] ?? 'Not Yet Started',
        actual_date: '',
        mov_file: null as File | null,
    });

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        post(urls.deliverables.store);
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Add deliverable</h2>}
        >
            <Head title="Add deliverable" />

            <div className="mx-auto max-w-2xl space-y-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href={urls.deliverables.index}>
                        <ArrowLeft className="size-4" />
                        Back to deliverables
                    </Link>
                </Button>

                <Card>
                    <CardHeader>
                        <CardTitle>Deliverable details</CardTitle>
                        <CardDescription>Record a deliverable and attach its MOV.</CardDescription>
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
                                    {errors.status && (
                                        <p className="text-destructive text-sm">{errors.status}</p>
                                    )}
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

                            <div className="space-y-2">
                                <Label htmlFor="mov_file">MOV file</Label>
                                <Input
                                    id="mov_file"
                                    type="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                    onChange={(e) => {
                                        setData('mov_file', e.target.files?.[0] ?? null);
                                    }}
                                />
                                {errors.mov_file && (
                                    <p className="text-destructive text-sm">{errors.mov_file}</p>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    loading={processing}
                                    loadingText="Saving"
                                    disabled={processing}
                                >
                                    <Upload className="size-4" />
                                    Save deliverable
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
