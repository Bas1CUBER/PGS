import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { MailCheck, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit = (e: { preventDefault(): void }) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <h2 className="text-xl font-bold text-gray-900">Verify your email</h2>
            <p className="mt-1 mb-6 text-sm text-gray-500">
                Thanks for signing up! Before getting started, verify your email address by clicking
                the link we just emailed you. If you didn't receive it, we will gladly send another.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mb-4 flex items-center gap-2 rounded-md bg-green-50 p-3 text-sm font-medium text-green-700">
                    <MailCheck className="size-4 shrink-0" />A new verification link has been sent
                    to your email address.
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Button type="submit" className="w-full" disabled={processing}>
                    <Send className="size-4" />
                    {processing ? 'Sending…' : 'Resend verification email'}
                </Button>

                <p className="text-center text-sm text-gray-500">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-primary hover:underline"
                    >
                        Log out
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
