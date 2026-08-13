import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { HeartPulse } from 'lucide-react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-[#0b4aa2] via-[#0d5bd1] to-[#1e88e5] px-4 py-8">
            <div className="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-white/10 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-40 -left-24 size-96 rounded-full bg-white/10 blur-3xl" />

            <div className="relative w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center gap-3 text-white">
                    <div className="flex size-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                        <HeartPulse className="size-8" />
                    </div>
                    <div className="text-center">
                        <h1 className="text-2xl font-bold tracking-tight">PGS</h1>
                        <p className="text-sm text-blue-100">
                            Performance Governance System
                            <span className="mx-1.5">·</span>
                            TRC DOH
                        </p>
                    </div>
                </div>

                <div className="rounded-2xl bg-white p-6 shadow-2xl sm:p-8">{children}</div>

                <p className="mt-6 text-center text-xs text-blue-100/80">
                    © {new Date().getFullYear()} Treatment and Rehabilitation Center — Department of
                    Health
                </p>
            </div>
        </div>
    );
}
