import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, ScrollText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PageProps } from '@/types';
import { relativeInternalUrl } from '@/lib/relative-url';

interface AuditLogRow {
    id: number;
    action: string;
    resource_type: string;
    resource_id: string | null;
    ip_address: string | null;
    created_at: string;
    user: { id: number; name: string | null; email: string } | null;
}

interface AuditLogsPageProps extends PageProps {
    logs: {
        data: AuditLogRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { action: string };
}

export default function AuditLogsIndex({ logs, filters }: AuditLogsPageProps) {
    const [action, setAction] = useState(filters.action);

    function submit(e: { preventDefault(): void }): void {
        e.preventDefault();
        router.get('/audit-logs', { action }, { preserveState: true, replace: true });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Audit Log</h2>}
        >
            <Head title="Audit Log" />

            <div className="space-y-6">
                <div className="text-muted-foreground flex items-center gap-2">
                    <ScrollText className="size-5" />
                    <p className="text-sm">Append-only record of sensitive actions.</p>
                </div>

                <form onSubmit={submit} className="flex w-full max-w-sm items-center gap-2">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={action}
                            onChange={(e) => {
                                setAction(e.target.value);
                            }}
                            placeholder="Filter by action…"
                            className="pl-9"
                            aria-label="Filter audit log"
                        />
                    </div>
                    <Button type="submit" size="sm">
                        Filter
                    </Button>
                </form>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Actor</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Resource</TableHead>
                                    <TableHead>IP</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="text-muted-foreground text-sm whitespace-nowrap">
                                            {new Date(log.created_at).toLocaleString()}
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-sm font-medium">
                                                {log.user?.name ?? log.user?.email ?? '—'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <code className="bg-muted rounded px-1.5 py-0.5 text-xs">
                                                {log.action}
                                            </code>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {log.resource_type}
                                            {log.resource_id !== null ? ` #${log.resource_id}` : ''}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground font-sans text-xs">
                                            {log.ip_address ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {logs.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            No audit entries yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {logs.links.length > 3 && (
                    <div className="flex justify-center gap-2">
                        {logs.links.map((link, index) => (
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
        </AuthenticatedLayout>
    );
}
