import GuestLayout from '@/Layouts/GuestLayout';
import GuestLogoGroup from '@/components/guest-logo-group';
import GuestStatusAlert from '@/components/guest-status-alert';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Mail } from 'lucide-react';
import type { SyntheticEvent } from 'react';

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route('password.email', undefined, false));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <article className="pgs-recovery-card">
                <header className="pgs-recovery-header">
                    <GuestLogoGroup />
                    <div>
                        <span className="pgs-login-eyebrow">Password recovery - 1 of 3</span>
                        <h1>Forgot password</h1>
                        <p>We will send a one-time reset code to your email.</p>
                    </div>
                </header>

                {status && <GuestStatusAlert title="Reset code sent" message={status} />}

                <form className="pgs-login-form pgs-recovery-form" onSubmit={submit}>
                    <div className="pgs-login-field">
                        <label htmlFor="reset-email">Email address</label>
                        <span className="pgs-login-input-shell">
                            <Mail size={16} aria-hidden="true" />
                            <input
                                id="reset-email"
                                className="pgs-login-input"
                                type="email"
                                name="email"
                                value={data.email}
                                autoComplete="email"
                                autoFocus
                                placeholder="you@trcdoh.ph"
                                onChange={(event) => {
                                    setData('email', event.target.value);
                                }}
                                required
                            />
                        </span>
                        {errors.email && <p className="pgs-login-error">{errors.email}</p>}
                    </div>

                    <button
                        className="pgs-login-submit loading-button"
                        type="submit"
                        disabled={processing}
                        aria-busy={processing}
                        data-loading={processing || undefined}
                        aria-label={processing ? 'Sending code' : undefined}
                    >
                        <span
                            className="loading-button-content"
                            aria-hidden={processing || undefined}
                        >
                            Send reset code
                        </span>
                        {processing ? (
                            <span className="loading-button-status" aria-hidden="true">
                                <LoaderCircle className="loading-button-spinner" />
                                Sending code
                            </span>
                        ) : null}
                    </button>
                </form>

                <footer className="pgs-recovery-footer">
                    <span>Remembered your password?</span>{' '}
                    <Link className="pgs-login-text-link" href={route('login', undefined, false)}>
                        Back to sign in
                    </Link>
                </footer>
            </article>
        </GuestLayout>
    );
}
