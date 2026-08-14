import type { PropsWithChildren, ReactNode } from 'react';

type GuestLayoutProps = PropsWithChildren<{
    support?: ReactNode;
}>;

export default function Guest({ children, support }: GuestLayoutProps) {
    return (
        <div className="pgs-guest-page">
            <main className="pgs-guest-auth">
                <div className="pgs-guest-card">{children}</div>
            </main>
            {support}
        </div>
    );
}
