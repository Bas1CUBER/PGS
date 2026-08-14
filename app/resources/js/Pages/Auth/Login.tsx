import { Head, Link, useForm } from '@inertiajs/react';
import {
    AtSign,
    Eye,
    EyeOff,
    Headset,
    LockKeyhole,
    LoaderCircle,
    Mail,
    MessageCircle,
    Phone,
    X,
} from 'lucide-react';
import { useState, type SyntheticEvent } from 'react';

import GuestLayout from '@/Layouts/GuestLayout';
import { Label } from '@/components/ui/label';
import GuestLogoGroup from '@/components/guest-logo-group';
import GuestStatusAlert from '@/components/guest-status-alert';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const [passwordVisible, setPasswordVisible] = useState(false);
    const [supportOpen, setSupportOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();

        post(route('login', undefined, false), {
            onFinish: () => {
                reset('password');
            },
        });
    };

    return (
        <GuestLayout
            support={
                <aside
                    className={supportOpen ? 'pgs-login-support is-open' : 'pgs-login-support'}
                    aria-label="Customer support"
                >
                    <div
                        className="pgs-login-support-panel"
                        id="pgs-login-support-panel"
                        role="dialog"
                        aria-label="Customer support contact information"
                    >
                        <div className="pgs-login-support-header">
                            <div>
                                <h2>
                                    <Headset size={16} aria-hidden="true" />
                                    Customer Support
                                </h2>
                                <p>
                                    <span aria-hidden="true" />
                                    Available
                                </p>
                            </div>
                            <button
                                className="pgs-login-support-close"
                                type="button"
                                aria-label="Close customer support"
                                onClick={() => {
                                    setSupportOpen(false);
                                }}
                            >
                                <X size={16} aria-hidden="true" />
                            </button>
                        </div>
                        <div className="pgs-login-support-divider" />
                        <a className="pgs-login-support-item" href="tel:+639171143562">
                            <Phone size={15} aria-hidden="true" />
                            <span>+63 917 114 3562</span>
                        </a>
                        <a
                            className="pgs-login-support-item"
                            href="https://m.me/doh.sflutrc"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <MessageCircle size={15} aria-hidden="true" />
                            <span>Via Messenger</span>
                        </a>
                        <a className="pgs-login-support-item" href="mailto:doh.sflutrc@gmail.com">
                            <AtSign size={15} aria-hidden="true" />
                            <span>doh.sflutrc@gmail.com</span>
                        </a>
                    </div>
                    <button
                        className="pgs-login-support-trigger"
                        type="button"
                        aria-label="Open customer support"
                        aria-controls="pgs-login-support-panel"
                        aria-expanded={supportOpen}
                        onClick={() => {
                            setSupportOpen((open) => !open);
                        }}
                    >
                        <MessageCircle size={21} aria-hidden="true" />
                    </button>
                </aside>
            }
        >
            <Head title="Log in" />

            <div className="pgs-login-card-content">
                <header className="pgs-login-header">
                    <GuestLogoGroup />
                    <h1>Welcome back.</h1>
                    <p>Continue to the Performance Governance System.</p>
                </header>

                {status && <GuestStatusAlert title="Password updated" message={status} />}

                <form className="pgs-login-form" onSubmit={submit}>
                    <div className="pgs-login-field">
                        <Label htmlFor="email">Email</Label>
                        <div className="pgs-login-input-shell">
                            <Mail size={16} aria-hidden="true" />
                            <input
                                id="email"
                                className="pgs-login-input"
                                type="email"
                                name="email"
                                value={data.email}
                                autoComplete="email"
                                autoFocus
                                placeholder="Enter your email"
                                required
                                onChange={(event) => {
                                    setData('email', event.target.value);
                                }}
                            />
                        </div>
                        {errors.email && <p className="pgs-login-error">{errors.email}</p>}
                    </div>

                    <div className="pgs-login-field">
                        <div className="pgs-login-label-row">
                            <Label htmlFor="password">Password</Label>
                            {canResetPassword && (
                                <Link
                                    href={route('password.request', undefined, false)}
                                    className="pgs-login-text-link"
                                >
                                    Forgot password?
                                </Link>
                            )}
                        </div>
                        <div className="pgs-login-input-shell">
                            <LockKeyhole size={16} aria-hidden="true" />
                            <input
                                id="password"
                                className="pgs-login-input"
                                type={passwordVisible ? 'text' : 'password'}
                                name="password"
                                value={data.password}
                                autoComplete="current-password"
                                placeholder="Enter your password"
                                required
                                onChange={(event) => {
                                    setData('password', event.target.value);
                                }}
                            />
                            <button
                                className="pgs-login-password-toggle"
                                type="button"
                                aria-label={passwordVisible ? 'Hide password' : 'Show password'}
                                onClick={() => {
                                    setPasswordVisible((visible) => !visible);
                                }}
                            >
                                {passwordVisible ? (
                                    <EyeOff size={16} aria-hidden="true" />
                                ) : (
                                    <Eye size={16} aria-hidden="true" />
                                )}
                            </button>
                        </div>
                        {errors.password && <p className="pgs-login-error">{errors.password}</p>}
                    </div>

                    <label className="pgs-login-check">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={(event) => {
                                setData('remember', event.target.checked);
                            }}
                        />
                        <span aria-hidden="true" />
                        Remember me
                    </label>

                    <button
                        className="pgs-login-submit loading-button"
                        type="submit"
                        disabled={processing}
                        aria-busy={processing}
                        data-loading={processing || undefined}
                        aria-label={processing ? 'Signing in' : undefined}
                    >
                        <span
                            className="loading-button-content"
                            aria-hidden={processing || undefined}
                        >
                            Sign In
                        </span>
                        {processing ? (
                            <span className="loading-button-status" aria-hidden="true">
                                <LoaderCircle className="loading-button-spinner" />
                                Signing in
                            </span>
                        ) : null}
                    </button>
                </form>

                <p className="pgs-login-privacy">
                    By signing in, you agree that your professional data will be handled in
                    accordance with the{' '}
                    <a
                        href="https://privacy.gov.ph/data-privacy-act/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Data Privacy Act of 2012 (RA 10173)
                    </a>{' '}
                    and applicable government regulations.
                </p>
            </div>
        </GuestLayout>
    );
}
