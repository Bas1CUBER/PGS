import { Head, Link, usePage } from '@inertiajs/react';
import { HeartPulse, LogIn, Megaphone, UserPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { PageProps } from '@/types';

interface Notice {
    notice_id: number;
    title: string | null;
    description: string | null;
    created_at: string;
}

interface WelcomePageProps extends PageProps {
    notices: Notice[];
    canLogin: boolean;
    canRegister: boolean;
}

export default function Welcome({ notices, canLogin, canRegister }: WelcomePageProps) {
    const { auth } = usePage().props;
    const user = auth.user;

    return (
        <>
            <Head title="Welcome" />

            <div className="ui-kit pgs-welcome-page relative min-h-screen overflow-hidden text-white">
                <div className="pointer-events-none absolute -top-40 -right-32 size-[32rem] rounded-full bg-white/10 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-48 -left-32 size-[32rem] rounded-full bg-white/10 blur-3xl" />

                <div className="relative mx-auto flex min-h-screen max-w-4xl flex-col items-center justify-center px-4 py-16">
                    <div className="mb-6 flex items-center gap-3">
                        <div className="pgs-welcome-mark flex size-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                            <HeartPulse className="size-8" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">PGS</h1>
                            <p className="text-sm text-blue-100">
                                Performance Governance System — TRC DOH
                            </p>
                        </div>
                    </div>

                    <p className="mb-8 max-w-xl text-center text-blue-50">
                        The Performance Governance System tracks institutional roadmaps,
                        deliverables, reviews and sector performance for the Treatment and
                        Rehabilitation Center — Department of Health.
                    </p>

                    <div className="mb-10 flex flex-wrap items-center justify-center gap-3">
                        {user === null ? (
                            <>
                                {canLogin && (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="text-primary bg-white hover:bg-blue-50"
                                    >
                                        <Link href={route('login', undefined, false)}>
                                            <LogIn className="size-4" />
                                            Sign in
                                        </Link>
                                    </Button>
                                )}
                                {canRegister && (
                                    <Button
                                        asChild
                                        size="lg"
                                        variant="outline"
                                        className="border-white/40 bg-white/10 text-white hover:bg-white/20"
                                    >
                                        <Link href={route('register', undefined, false)}>
                                            <UserPlus className="size-4" />
                                            Register
                                        </Link>
                                    </Button>
                                )}
                            </>
                        ) : (
                            <Button
                                asChild
                                size="lg"
                                className="text-primary bg-white hover:bg-blue-50"
                            >
                                <Link href={route('dashboard', undefined, false)}>Go to dashboard</Link>
                            </Button>
                        )}
                    </div>

                    {notices.length > 0 && (
                        <div className="w-full max-w-xl space-y-3">
                            <p className="flex items-center gap-2 text-sm font-medium text-blue-100">
                                <Megaphone className="size-4" />
                                Announcements
                            </p>
                            {notices.map((notice) => (
                                <Card key={notice.notice_id} className="bg-white/95">
                                    <CardContent className="p-4 pt-0">
                                        <p className="font-semibold text-slate-900">
                                            {notice.title ?? 'Announcement'}
                                        </p>
                                        {notice.description !== null && (
                                            <p className="mt-1 text-sm text-slate-600">
                                                {notice.description}
                                            </p>
                                        )}
                                        <p className="mt-1 text-xs text-slate-400">
                                            {new Date(notice.created_at).toLocaleDateString()}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}

                    <p className="mt-10 text-xs text-blue-100/80">
                        © {new Date().getFullYear()} Treatment and Rehabilitation Center —
                        Department of Health
                    </p>
                </div>
            </div>
        </>
    );
}
