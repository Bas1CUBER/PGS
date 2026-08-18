import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, KeyRound, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PageProps, User } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

interface EditUserPageProps extends PageProps {
    user: User & { page_access: Record<string, boolean> | null };
    roles: string[];
    accessModules: string[];
}

const accessLabels: Record<string, string> = {
    roadmaps: 'Roadmaps',
    scorecard: 'Scorecard',
    performance_assessment: 'Performance Assessment',
    cascading: 'Cascading',
    governance: 'Governance',
};

export default function EditUser({ user, roles, accessModules }: EditUserPageProps) {
    const access = user.page_access ?? {};
    const [accessState, setAccessState] = useState<Record<string, boolean>>(
        Object.fromEntries(accessModules.map((m) => [m, access[m] ?? true])),
    );
    const [confirmingDeletion, setConfirmingDeletion] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        name: user.name ?? '',
        email: user.email,
        password: '',
        password_confirmation: '',
        role: user.role,
        office: user.office ?? '',
        is_active: user.is_active,
    });
    const { isPending, start, finish } = usePendingAction();

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        put(`/users/${String(user.id)}`);
    }

    function saveAccess(): void {
        start('access');
        router.put(
            `/users/${String(user.id)}/access`,
            { ...accessState },
            {
                preserveScroll: true,
                onFinish: () => {
                    finish('access');
                },
            },
        );
    }

    function toggleAccount(): void {
        start('toggle');
        router.post(
            `/users/${String(user.id)}/toggle`,
            {},
            {
                onFinish: () => {
                    finish('toggle');
                },
            },
        );
    }

    function confirmDelete(): void {
        start('delete');
        router.delete(`/users/${String(user.id)}`, {
            onFinish: () => {
                finish('delete');
                setConfirmingDeletion(false);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Edit user</h2>}
        >
            <Head title={`Edit ${user.name ?? user.email}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <Button asChild variant="ghost" size="sm">
                    <Link href="/users">
                        <ArrowLeft className="size-4" />
                        Back to users
                    </Link>
                </Button>

                <Card>
                    <CardHeader>
                        <CardTitle>Account details</CardTitle>
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
                                    <Label htmlFor="role">Role</Label>
                                    <select
                                        id="role"
                                        value={data.role}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            if (roles.includes(val)) {
                                                setData('role', val as User['role']);
                                            }
                                        }}
                                        className="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        {roles.map((role) => (
                                            <option key={role} value={role}>
                                                {role}
                                            </option>
                                        ))}
                                    </select>
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

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="password">
                                        New password{' '}
                                        <span className="text-muted-foreground">(optional)</span>
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => {
                                            setData('password', e.target.value);
                                        }}
                                    />
                                    {errors.password && (
                                        <p className="text-destructive text-sm">
                                            {errors.password}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm new password
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => {
                                            setData('password_confirmation', e.target.value);
                                        }}
                                    />
                                </div>
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => {
                                        setData('is_active', e.target.checked);
                                    }}
                                />
                                Account active
                            </label>

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
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-4" />
                            Page access
                        </CardTitle>
                        <CardDescription>
                            Modules this user can visit (admins bypass the matrix).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {accessModules.map((module) => (
                                <label
                                    key={module}
                                    className={cn(
                                        'flex cursor-pointer items-center gap-2 rounded-md border p-2 text-sm',
                                        accessState[module] && 'border-primary bg-accent',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        checked={accessState[module]}
                                        onChange={(e) => {
                                            setAccessState((prev) => ({
                                                ...prev,
                                                [module]: e.target.checked,
                                            }));
                                        }}
                                    />
                                    {accessLabels[module] ?? module}
                                </label>
                            ))}
                        </div>
                        <div className="flex justify-end">
                            <Button
                                variant="outline"
                                size="sm"
                                loading={isPending('access')}
                                loadingText="Saving"
                                onClick={saveAccess}
                            >
                                Save access
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound className="size-4" />
                            Account status
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            loading={isPending('toggle')}
                            loadingText="Updating"
                            onClick={toggleAccount}
                        >
                            {user.is_active ? 'Deactivate account' : 'Activate account'}
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            loading={isPending('delete')}
                            loadingText="Deleting"
                            onClick={() => {
                                setConfirmingDeletion(true);
                            }}
                        >
                            Delete user
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <PgsConfirmationDialog
                open={confirmingDeletion}
                onOpenChange={setConfirmingDeletion}
                title="Delete user"
                description="This action permanently removes the user account."
                confirmationTitle="Confirm user deletion"
                confirmationDescription={`${user.email} will be removed from the workspace.`}
                onConfirm={confirmDelete}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
