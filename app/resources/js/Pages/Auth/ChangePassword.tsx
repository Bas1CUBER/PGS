import GuestLayout from '@/Layouts/GuestLayout';
import GuestLogoGroup from '@/components/guest-logo-group';
import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, LockKeyhole } from 'lucide-react';
import { useState, type SyntheticEvent } from 'react';

interface ChangePasswordProps {
    email: string;
}

export default function ChangePassword({ email }: ChangePasswordProps) {
    const [passwordVisible, setPasswordVisible] = useState(false);
    const [confirmationVisible, setConfirmationVisible] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route('password.change.store', undefined, false));
    };

    return (
        <GuestLayout>
            <Head title="Change Password" />

            <article className="pgs-recovery-card">
                <header className="pgs-recovery-header">
                    <GuestLogoGroup />
                    <div>
                        <span className="pgs-login-eyebrow">Password recovery - 3 of 3</span>
                        <h1>Choose a new password</h1>
                        <p>Set a new password for {email}.</p>
                    </div>
                </header>

                <form className="pgs-login-form pgs-recovery-form" onSubmit={submit}>
                    <div className="pgs-login-field">
                        <label htmlFor="new-password">New password</label>
                        <span className="pgs-login-input-shell">
                            <LockKeyhole size={16} aria-hidden="true" />
                            <input
                                id="new-password"
                                className="pgs-login-input"
                                type={passwordVisible ? 'text' : 'password'}
                                value={data.password}
                                autoComplete="new-password"
                                autoFocus
                                placeholder="At least 12 characters"
                                onChange={(event) => {
                                    setData('password', event.target.value);
                                }}
                                required
                            />
                            <button
                                className="pgs-login-password-toggle"
                                type="button"
                                aria-label={
                                    passwordVisible ? 'Hide new password' : 'Show new password'
                                }
                                onClick={() => {
                                    setPasswordVisible((visible) => !visible);
                                }}
                            >
                                {passwordVisible ? <EyeOff size={15} /> : <Eye size={15} />}
                            </button>
                        </span>
                        {errors.password && <p className="pgs-login-error">{errors.password}</p>}
                    </div>

                    <div className="pgs-login-field">
                        <label htmlFor="password-confirmation">Confirm new password</label>
                        <span className="pgs-login-input-shell">
                            <LockKeyhole size={16} aria-hidden="true" />
                            <input
                                id="password-confirmation"
                                className="pgs-login-input"
                                type={confirmationVisible ? 'text' : 'password'}
                                value={data.password_confirmation}
                                autoComplete="new-password"
                                placeholder="Repeat your password"
                                onChange={(event) => {
                                    setData('password_confirmation', event.target.value);
                                }}
                                required
                            />
                            <button
                                className="pgs-login-password-toggle"
                                type="button"
                                aria-label={
                                    confirmationVisible
                                        ? 'Hide password confirmation'
                                        : 'Show password confirmation'
                                }
                                onClick={() => {
                                    setConfirmationVisible((visible) => !visible);
                                }}
                            >
                                {confirmationVisible ? <EyeOff size={15} /> : <Eye size={15} />}
                            </button>
                        </span>
                        {errors.password_confirmation && (
                            <p className="pgs-login-error">{errors.password_confirmation}</p>
                        )}
                    </div>

                    <button
                        className="pgs-login-submit loading-button"
                        type="submit"
                        disabled={processing}
                        aria-busy={processing}
                        data-loading={processing || undefined}
                        aria-label={processing ? 'Updating password' : undefined}
                    >
                        <span
                            className="loading-button-content"
                            aria-hidden={processing || undefined}
                        >
                            Change password
                        </span>
                        {processing ? (
                            <span className="loading-button-status" aria-hidden="true">
                                <LoaderCircle className="loading-button-spinner" />
                                Updating password
                            </span>
                        ) : null}
                    </button>
                </form>
            </article>
        </GuestLayout>
    );
}
