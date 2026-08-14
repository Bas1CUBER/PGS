import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, UserPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import { cn } from '@/lib/utils';
import type { PageProps, User } from '@/types';
import { relativeInternalUrl } from '@/lib/relative-url';
import { usePendingAction } from '@/hooks/use-pending-action';

interface UsersPageProps extends PageProps {
    users: {
        data: (User & { is_active: boolean })[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search: string; role: string };
}

const roleColors: Record<string, string> = {
    admin: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    focal: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    employee: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

export default function UsersIndex({ users, filters }: UsersPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [deleteTarget, setDeleteTarget] = useState<User | null>(null);
    const { isPending, start, finish } = usePendingAction();

    function submitSearch(e: { preventDefault(): void }): void {
        e.preventDefault();
        router.get('/users', { search }, { preserveState: true, replace: true });
    }

    function confirmDelete(): void {
        if (deleteTarget === null) return;
        start('delete');
        router.delete(`/users/${String(deleteTarget.id)}`, {
            onFinish: () => {
                finish('delete');
                setDeleteTarget(null);
            },
        });
    }

    function toggleUser(user: User & { is_active: boolean }): void {
        const action = `toggle:${String(user.id)}`;
        start(action);
        router.post(
            `/users/${String(user.id)}/toggle`,
            {},
            {
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">User Management</h2>}
        >
            <Head title="Users" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <form
                        onSubmit={submitSearch}
                        className="flex w-full max-w-sm items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                }}
                                placeholder="Search name, email, office…"
                                className="pl-9"
                                aria-label="Search users"
                            />
                        </div>
                        <Button type="submit" size="sm">
                            Search
                        </Button>
                    </form>

                    <div className="flex gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href="/users/create">Import CSV</Link>
                        </Button>
                        <Button asChild size="sm">
                            <Link href="/users/create">
                                <UserPlus className="size-4" />
                                Add user
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Office</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <p className="font-medium">{user.name ?? '—'}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {user.email}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={cn(roleColors[user.role])}
                                            >
                                                {user.role}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {user.office ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={user.is_active ? 'success' : 'outline'}>
                                                {user.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button asChild variant="ghost" size="sm">
                                                    <Link href={`/users/${String(user.id)}/edit`}>
                                                        Edit
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    loading={isPending(`toggle:${String(user.id)}`)}
                                                    loadingText="Saving"
                                                    onClick={() => {
                                                        toggleUser(user);
                                                    }}
                                                >
                                                    {user.is_active ? 'Deactivate' : 'Activate'}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        setDeleteTarget(user);
                                                    }}
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {users.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            No users match your filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {users.links.length > 3 && (
                    <div className="flex justify-center gap-2">
                        {users.links.map((link, index) => (
                            <span key={index}>
                                {link.url ? (
                                    <Button
                                        asChild
                                        variant={link.active ? 'default' : 'ghost'}
                                        size="sm"
                                    >
                                        <Link href={relativeInternalUrl(link.url) ?? '#'}>
                                            {link.label.replace(/&laquo;|&raquo;/g, '')}
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button variant="ghost" size="sm" disabled>
                                        {link.label.replace(/&laquo;|&raquo;/g, '')}
                                    </Button>
                                )}
                            </span>
                        ))}
                    </div>
                )}
            </div>

            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete + user"
                description="This action permanently removes the account."
                confirmationTitle="Confirm user deletion"
                confirmationDescription={`${deleteTarget?.email ?? 'This user'} will be removed from the workspace.`}
                onConfirm={confirmDelete}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
