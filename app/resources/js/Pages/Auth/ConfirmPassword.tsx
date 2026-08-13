import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e: { preventDefault(): void }) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => {
                reset('password');
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirm Password" />

            <h2 className="text-xl font-bold text-gray-900">Confirm your password</h2>
            <p className="mt-1 mb-6 text-sm text-gray-500">
                This is a secure area of the application. Please confirm your password before
                continuing.
            </p>

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
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

                <Button type="submit" className="w-full" disabled={processing}>
                    <ShieldCheck className="size-4" />
                    {processing ? 'Confirming…' : 'Confirm'}
                </Button>
            </form>
        </GuestLayout>
    );
}
