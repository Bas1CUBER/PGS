import GuestLayout from '@/Layouts/GuestLayout';
import GuestLogoGroup from '@/components/guest-logo-group';
import GuestStatusAlert from '@/components/guest-status-alert';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useEffect, useRef, type SyntheticEvent } from 'react';

interface VerifyResetCodeProps {
    email: string;
    status?: string;
}

export default function VerifyResetCode({ email, status }: VerifyResetCodeProps) {
    const inputs = useRef<(HTMLInputElement | null)[]>([]);
    const { data, setData, post, processing, errors } = useForm({ code: '' });
    const resend = useForm({});

    useEffect(() => {
        inputs.current[0]?.focus();
    }, []);

    const setCodeAt = (index: number, value: string) => {
        const digits = Array.from({ length: 6 }, (_, slot) => data.code[slot] ?? '');
        digits[index] = value.replace(/\D/g, '').slice(-1);
        setData('code', digits.join(''));

        if (digits[index] && index < 5) {
            inputs.current[index + 1]?.focus();
        }
    };

    const submit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route('password.code.verify', undefined, false));
    };

    const resendCode = () => {
        resend.post(route('password.code.resend', undefined, false), { preserveScroll: true });
    };

    return (
        <GuestLayout>
            <Head title="Enter Reset Code" />

            <article className="pgs-recovery-card">
                <header className="pgs-recovery-header">
                    <GuestLogoGroup />
                    <div>
                        <span className="pgs-login-eyebrow">Password recovery - 2 of 3</span>
                        <h1>Enter reset code</h1>
                        <p>Enter the 6-digit code sent to {email}.</p>
                    </div>
                </header>

                {status && <GuestStatusAlert title="Reset code sent" message={status} />}

                <form className="pgs-login-form pgs-recovery-form" onSubmit={submit}>
                    <div className="pgs-login-field">
                        <label htmlFor="reset-code-0">Verification code</label>
                        <div
                            className="pgs-recovery-otp"
                            role="group"
                            aria-label="6-digit reset code"
                        >
                            {Array.from({ length: 6 }, (_, index) => (
                                <input
                                    key={index}
                                    ref={(element) => {
                                        inputs.current[index] = element;
                                    }}
                                    id={`reset-code-${String(index)}`}
                                    className="pgs-recovery-otp-input"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete={index === 0 ? 'one-time-code' : 'off'}
                                    maxLength={1}
                                    value={data.code[index] ?? ''}
                                    aria-label={`Reset code digit ${String(index + 1)}`}
                                    onChange={(event) => {
                                        setCodeAt(index, event.target.value);
                                    }}
                                    onKeyDown={(event) => {
                                        if (
                                            event.key === 'Backspace' &&
                                            !data.code[index] &&
                                            index > 0
                                        ) {
                                            inputs.current[index - 1]?.focus();
                                        }
                                    }}
                                    onPaste={(event) => {
                                        event.preventDefault();
                                        const pasted = event.clipboardData
                                            .getData('text')
                                            .replace(/\D/g, '')
                                            .slice(0, 6);
                                        setData('code', pasted);
                                        inputs.current[Math.min(pasted.length, 5)]?.focus();
                                    }}
                                    required
                                />
                            ))}
                        </div>
                        {errors.code && <p className="pgs-login-error">{errors.code}</p>}
                    </div>

                    <button
                        className="pgs-login-submit loading-button"
                        type="submit"
                        disabled={processing}
                        aria-busy={processing}
                        data-loading={processing || undefined}
                        aria-label={processing ? 'Checking code' : undefined}
                    >
                        <span
                            className="loading-button-content"
                            aria-hidden={processing || undefined}
                        >
                            Verify code
                        </span>
                        {processing ? (
                            <span className="loading-button-status" aria-hidden="true">
                                <LoaderCircle className="loading-button-spinner" />
                                Checking code
                            </span>
                        ) : null}
                    </button>
                </form>

                <footer className="pgs-recovery-footer pgs-recovery-footer-stack">
                    <span>Did not receive the email?</span>
                    <button
                        className="pgs-login-text-link pgs-recovery-link-button loading-button"
                        type="button"
                        onClick={resendCode}
                        disabled={resend.processing}
                        aria-busy={resend.processing}
                        data-loading={resend.processing || undefined}
                        aria-label={resend.processing ? 'Sending new code' : undefined}
                    >
                        <span
                            className="loading-button-content"
                            aria-hidden={resend.processing || undefined}
                        >
                            Resend code
                        </span>
                        {resend.processing ? (
                            <span className="loading-button-status" aria-hidden="true">
                                <LoaderCircle className="loading-button-spinner" />
                                Sending new code
                            </span>
                        ) : null}
                    </button>
                    <Link
                        className="pgs-login-text-link"
                        href={route('password.request', undefined, false)}
                    >
                        Use a different email
                    </Link>
                </footer>
            </article>
        </GuestLayout>
    );
}
