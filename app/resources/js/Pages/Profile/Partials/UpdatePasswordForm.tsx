import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e: { preventDefault(): void }) => {
        e.preventDefault();

        put(route('password.update', undefined, false), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
            },
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section>
            <header className="mb-6">
                <h2 className="text-lg font-semibold">Update Password</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Ensure your account is using a long, random password to stay secure.
                </p>
            </header>

            <form onSubmit={updatePassword} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="current_password">Current password</Label>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) => {
                            setData('current_password', e.target.value);
                        }}
                        type="password"
                        autoComplete="current-password"
                    />
                    {errors.current_password && (
                        <p className="text-destructive text-sm">{errors.current_password}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password">New password</Label>
                    <Input
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => {
                            setData('password', e.target.value);
                        }}
                        type="password"
                        autoComplete="new-password"
                    />
                    <p className="text-muted-foreground text-xs">At least 12 characters.</p>
                    {errors.password && (
                        <p className="text-destructive text-sm">{errors.password}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password_confirmation">Confirm new password</Label>
                    <Input
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => {
                            setData('password_confirmation', e.target.value);
                        }}
                        type="password"
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && (
                        <p className="text-destructive text-sm">{errors.password_confirmation}</p>
                    )}
                </div>

                <div className="flex items-center gap-3">
                    <Button loading={processing} loadingText="Saving" disabled={processing}>
                        Save
                    </Button>

                    {recentlySuccessful && (
                        <p className="text-muted-foreground flex items-center gap-1 text-sm">
                            <Check className="size-4 text-green-600" />
                            Saved.
                        </p>
                    )}
                </div>
            </form>
        </section>
    );
}
