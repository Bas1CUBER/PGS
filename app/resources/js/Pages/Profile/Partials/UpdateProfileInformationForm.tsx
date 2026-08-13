import { Link, useForm, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;

    if (user === null) {
        throw new Error('Profile form rendered without an authenticated user');
    }

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name ?? '',
        email: user.email,
    });

    const submit = (e: { preventDefault(): void }) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section>
            <header className="mb-6">
                <h2 className="text-lg font-semibold">Profile Information</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Update your account's profile information and email address.
                </p>
            </header>

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => {
                            setData('name', e.target.value);
                        }}
                        required
                        autoFocus
                        autoComplete="name"
                    />
                    {errors.name && <p className="text-destructive text-sm">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => {
                            setData('email', e.target.value);
                        }}
                        required
                        autoComplete="username"
                    />
                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                </div>

                {mustVerifyEmail && !user.email_verified_at && (
                    <div>
                        <p className="text-muted-foreground text-sm">
                            Your email address is unverified.{' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="text-primary hover:underline"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <p className="mt-2 text-sm font-medium text-green-600">
                                A new verification link has been sent to your email address.
                            </p>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-3">
                    <Button disabled={processing}>{processing ? 'Saving…' : 'Save'}</Button>

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
