import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: { preventDefault(): void }) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => {
                reset('password', 'password_confirmation');
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset Password" />

            <h2 className="text-xl font-bold text-gray-900">Choose a new password</h2>
            <p className="mt-1 mb-6 text-sm text-gray-500">
                Your password must be at least 12 characters.
            </p>

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        readOnly
                        onChange={(e) => {
                            setData('email', e.target.value);
                        }}
                    />
                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password">New password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        onChange={(e) => {
                            setData('password', e.target.value);
                        }}
                        required
                    />
                    {errors.password && (
                        <p className="text-destructive text-sm">{errors.password}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password_confirmation">Confirm new password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(e) => {
                            setData('password_confirmation', e.target.value);
                        }}
                        required
                    />
                    {errors.password_confirmation && (
                        <p className="text-destructive text-sm">{errors.password_confirmation}</p>
                    )}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    <KeyRound className="size-4" />
                    {processing ? 'Resetting…' : 'Reset password'}
                </Button>
            </form>
        </GuestLayout>
    );
}
