import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit = (e: { preventDefault(): void }) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => {
                reset('password');
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <h2 className="text-xl font-bold text-gray-900">Welcome back</h2>
            <p className="mt-1 mb-6 text-sm text-gray-500">Sign in to continue to the system.</p>

            {status && <div className="mb-4 text-sm font-medium text-green-600">{status}</div>}

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        placeholder="you@trcdoh.ph"
                        onChange={(e) => {
                            setData('email', e.target.value);
                        }}
                    />
                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password">Password</Label>
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-primary text-sm hover:underline"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        onChange={(e) => {
                            setData('password', e.target.value);
                        }}
                    />
                    {errors.password && (
                        <p className="text-destructive text-sm">{errors.password}</p>
                    )}
                </div>

                <label className="flex items-center gap-2 text-sm text-gray-600">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => {
                            setData('remember', e.target.checked);
                        }}
                        className="border-input accent-primary size-4 rounded"
                    />
                    Remember me
                </label>

                <Button type="submit" className="w-full" disabled={processing}>
                    <LogIn className="size-4" />
                    {processing ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>
        </GuestLayout>
    );
}
