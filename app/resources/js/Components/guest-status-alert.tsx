import { CheckCircle2 } from 'lucide-react';

interface GuestStatusAlertProps {
    title: string;
    message: string;
}

export default function GuestStatusAlert({ title, message }: GuestStatusAlertProps) {
    return (
        <div className="pgs-login-status" role="status">
            <span className="pgs-login-status-icon" aria-hidden="true">
                <CheckCircle2 size={15} />
            </span>
            <span className="pgs-login-status-copy">
                <strong>{title}</strong>
                <small>{message}</small>
            </span>
        </div>
    );
}
