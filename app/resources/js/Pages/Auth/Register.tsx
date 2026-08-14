import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: { preventDefault(): void }) => {
        e.preventDefault();

        post(route('register', undefined, false), {
            onFinish: () => {
                reset('password', 'password_confirmation');
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <h2 className="text-xl font-bold text-gray-900">Create an account</h2>
            <p className="mt-1 mb-6 text-sm text-gray-500">Register to access the system.</p>

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        autoComplete="name"
                        autoFocus
                        onChange={(e) => {
                            setData('name', e.target.value);
                        }}
                        required
                    />
                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        placeholder="you@trcdoh.ph"
                        onChange={(e) => {
                            setData('email', e.target.value);
                        }}
                        required
                    />
                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        onChange={(e) => {
                            setData('password', e.target.value);
                        }}
                        required
                    />
                    <p className="text-muted-foreground text-xs">At least 12 characters.</p>
                    {errors.password && (
                        <p className="text-destructive text-sm">{errors.password}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
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

                <Button
                    type="submit"
                    className="w-full"
                    loading={processing}
                    loadingText="Creating account"
                    disabled={processing}
                >
                    <UserPlus className="size-4" />
                    Register
                </Button>

                <p className="text-center text-sm text-gray-500">
                    Already registered?{' '}
                    <Link href={route('login', undefined, false)} className="text-primary hover:underline">
                        Sign in
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
