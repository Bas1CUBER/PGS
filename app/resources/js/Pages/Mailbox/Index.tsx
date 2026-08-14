import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { type SyntheticEvent, useState } from 'react';
import { Mail, Search } from 'lucide-react';
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

interface MailRow {
    id: number;
    to_email: string;
    subject: string;
    created_at: string;
}

interface MailboxPageProps extends PageProps {
    mails: {
        data: MailRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
}

export default function MailboxIndex({ mails, filters }: MailboxPageProps) {
    const [q, setQ] = useState(filters.q);

    function submit(e: SyntheticEvent<HTMLFormElement>): void {
        e.preventDefault();
        router.get('/mailbox', { q }, { preserveState: true, replace: true });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Mailbox</h2>}
        >
            <Head title="Mailbox" />

            <div className="space-y-6">
                <div className="text-muted-foreground flex items-center gap-2">
                    <Mail className="size-5" />
                    <p className="text-sm">
                        Outgoing mail store (LAN: no SMTP server). Password reset links land here.
                    </p>
                </div>

                <form onSubmit={submit} className="flex w-full max-w-sm items-center gap-2">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={q}
                            onChange={(e) => {
                                setQ(e.target.value);
                            }}
                            placeholder="Search recipient or subject…"
                            className="pl-9"
                            aria-label="Search mail"
                        />
                    </div>
                    <Button type="submit" size="sm">
                        Search
                    </Button>
                </form>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>To</TableHead>
                                    <TableHead>Subject</TableHead>
                                    <TableHead>Sent</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {mails.data.map((mail) => (
                                    <TableRow key={mail.id}>
                                        <TableCell className="font-medium">
                                            {mail.to_email}
                                        </TableCell>
                                        <TableCell className="text-sm">{mail.subject}</TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {new Date(mail.created_at).toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button asChild variant="ghost" size="sm">
                                                <Link href={`/mailbox/${String(mail.id)}`}>
                                                    Open
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {mails.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="text-muted-foreground py-10 text-center"
                                        >
                                            No outgoing mail yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {mails.links.length > 3 && (
                    <div className="flex justify-center gap-2">
                        {mails.links.map((link, index) => (
                            <span key={index}>
                                {link.url ? (
                                    <Button
                                        asChild
                                        variant={link.active ? 'default' : 'ghost'}
                                        size="sm"
                                    >
                                        <Link href={link.url}>
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
