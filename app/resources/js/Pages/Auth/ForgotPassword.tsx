import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e: { preventDefault(): void }) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <h2 className="text-xl font-bold text-gray-900">Reset your password</h2>
            <p className="mt-1 mb-6 text-sm text-gray-500">
                Enter your email address and we will send you a reset link.
            </p>

            {status && (
                <div className="mb-4 rounded-md bg-green-50 p-3 text-sm font-medium text-green-700">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoFocus
                        placeholder="you@trcdoh.ph"
                        onChange={(e) => {
                            setData('email', e.target.value);
                        }}
                        required
                    />
                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    <Mail className="size-4" />
                    {processing ? 'Sending…' : 'Email password reset link'}
                </Button>

                <p className="text-center text-sm text-gray-500">
                    Remembered it?{' '}
                    <Link href={route('login')} className="text-primary hover:underline">
                        Back to sign in
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
