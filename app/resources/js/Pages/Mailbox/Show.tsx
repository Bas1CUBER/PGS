import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { PageProps } from '@/types';

interface MailDetail {
    id: number;
    to_email: string;
    subject: string;
    body: string | null;
    created_at: string;
}

interface MailboxShowPageProps extends PageProps {
    mail: MailDetail;
}

export default function MailboxShow({ mail }: MailboxShowPageProps) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl leading-tight font-semibold">Mailbox — {mail.subject}</h2>
            }
        >
            <Head title={`Mailbox — ${mail.subject}`} />

            <div className="space-y-4">
                <Button asChild variant="ghost" size="sm">
                    <Link href="/mailbox">
                        <ArrowLeft className="size-4" />
                        Back to mailbox
                    </Link>
                </Button>

                <Card>
                    <CardContent className="space-y-3 p-6 pt-0">
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <Mail className="size-4" />
                            <span>
                                To:{' '}
                                <span className="text-foreground font-medium">{mail.to_email}</span>{' '}
                                · {new Date(mail.created_at).toLocaleString()}
                            </span>
                        </div>
                        {mail.body ? (
                            <div className="prose prose-sm dark:prose-invert max-w-none border-t pt-4 whitespace-pre-wrap">
                                {mail.body}
                            </div>
                        ) : (
                            <p className="text-muted-foreground border-t pt-4 text-sm">
                                No body content.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
