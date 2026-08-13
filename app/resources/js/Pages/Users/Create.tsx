import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface CreateUserPageProps extends PageProps {
    roles: string[];
    accessModules: string[];
}

interface ImportReport {
    total: number;
    created: number;
    errors: { line: number; message: string }[];
}

const accessLabels: Record<string, string> = {
    roadmaps: 'Roadmaps',
    scorecard: 'Scorecard',
    performance_assessment: 'Performance Assessment',
    cascading: 'Cascading',
    governance: 'Governance',
};

export default function CreateUser({ roles, accessModules }: CreateUserPageProps) {
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importing, setImporting] = useState(false);
    const [importReport, setImportReport] = useState<ImportReport | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'employee',
        office: '',
        roadmaps: true,
        scorecard: true,
        performance_assessment: true,
        cascading: true,
        governance: true,
    });

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        post('/users');
    }

    async function runImport(dryRun: boolean): Promise<void> {
        if (importFile === null) return;

        const form = new FormData();
        form.append('file', importFile);
        form.append('dry_run', dryRun ? '1' : '0');

        setImporting(true);
        try {
            const response = await fetch('/users/import', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: form,
            });
            const payload = (await response.json()) as ImportReport;
            setImportReport(payload);
        } catch {
            setImportReport(null);
        } finally {
            setImporting(false);
        }
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Add user</h2>}
        >
            <Head title="Add user" />

            <div className="mx-auto max-w-2xl space-y-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href="/users">
                        <ArrowLeft className="size-4" />
                        Back to users
                    </Link>
                </Button>

                <Card>
                    <CardHeader>
                        <CardTitle>New user</CardTitle>
                        <CardDescription>
                            Create an account with role-based page access.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Full name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => {
                                            setData('name', e.target.value);
                                        }}
                                        required
                                    />
                                    {errors.name && (
                                        <p className="text-destructive text-sm">{errors.name}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => {
                                            setData('email', e.target.value);
                                        }}
                                        required
                                    />
                                    {errors.email && (
                                        <p className="text-destructive text-sm">{errors.email}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => {
                                            setData('password', e.target.value);
                                        }}
                                        required
                                    />
                                    {errors.password && (
                                        <p className="text-destructive text-sm">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="password_confirmation">Confirm password</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => {
                                            setData('password_confirmation', e.target.value);
                                        }}
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="role">Role</Label>
                                    <select
                                        id="role"
                                        value={data.role}
                                        onChange={(e) => {
                                            setData('role', e.target.value);
                                        }}
                                        className="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        {roles.map((role) => (
                                            <option key={role} value={role}>
                                                {role}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.role && (
                                        <p className="text-destructive text-sm">{errors.role}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="office">Office / Division</Label>
                                    <Input
                                        id="office"
                                        value={data.office}
                                        onChange={(e) => {
                                            setData('office', e.target.value);
                                        }}
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Page access</Label>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {accessModules.map((module) => (
                                        <label
                                            key={module}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-2 rounded-md border p-2 text-sm',
                                                data[module as keyof typeof data] &&
                                                    'border-primary bg-accent',
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={Boolean(data[module as keyof typeof data])}
                                                onChange={(e) => {
                                                    setData(
                                                        module as keyof typeof data,
                                                        e.target.checked,
                                                    );
                                                }}
                                            />
                                            {accessLabels[module] ?? module}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'CreatingÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦'
                                        : 'Create user'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Import from CSV</CardTitle>
                        <CardDescription>
                            Columns: email, password, role (admin|focal|employee), name, office.
                            Password must be at least 12 characters.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Input
                                type="file"
                                accept=".csv"
                                onChange={(e) => {
                                    setImportFile(e.target.files?.[0] ?? null);
                                }}
                                className="max-w-sm"
                            />
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={importFile === null || importing}
                                    onClick={() => void runImport(true)}
                                >
                                    Dry run
                                </Button>
                                <Button
                                    size="sm"
                                    disabled={importFile === null || importing}
                                    onClick={() => void runImport(false)}
                                >
                                    <Upload className="size-4" />
                                    Import
                                </Button>
                            </div>
                        </div>

                        {importReport !== null && (
                            <div className="space-y-2 rounded-md border p-4 text-sm">
                                <p className="font-medium">
                                    {importReport.total} rows ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â·{' '}
                                    {importReport.created} created
                                </p>
                                {importReport.errors.length > 0 && (
                                    <ul className="space-y-1">
                                        {importReport.errors.map((error, i) => (
                                            <li key={i} className="text-destructive flex gap-2">
                                                <span className="shrink-0">Line {error.line}:</span>
                                                <span>{error.message}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
