/* eslint-disable react-refresh/only-export-components */

import type { LucideIcon } from 'lucide-react';
import { AlertTriangle, CheckCircle2, Info, X } from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';

export type ToastKind = 'success' | 'error' | 'warning' | 'info';

interface ToastItem {
    id: number;
    kind: ToastKind;
    message: string;
}

interface ToastContextValue {
    toasts: ToastItem[];
    showToast: (kind: ToastKind, message: string) => void;
    dismissToast: (id: number) => void;
}

const ToastContext = createContext<ToastContextValue | undefined>(undefined);

const toastIcons: Record<ToastKind, LucideIcon> = {
    success: CheckCircle2,
    error: AlertTriangle,
    warning: AlertTriangle,
    info: Info,
};

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);
    const nextId = useRef(0);

    const showToast = useCallback((kind: ToastKind, message: string) => {
        const id = nextId.current++;
        setToasts((current) => [...current, { id, kind, message }].slice(-4));
    }, []);

    const dismissToast = useCallback((id: number) => {
        setToasts((current) => current.filter((toast) => toast.id !== id));
    }, []);

    useEffect(() => {
        const timers = toasts.map((toast) =>
            window.setTimeout(() => {
                dismissToast(toast.id);
            }, 5000),
        );

        return () => {
            timers.forEach((timer) => {
                window.clearTimeout(timer);
            });
        };
    }, [dismissToast, toasts]);

    const value = useMemo(
        () => ({ toasts, showToast, dismissToast }),
        [dismissToast, showToast, toasts],
    );

    return <ToastContext.Provider value={value}>{children}</ToastContext.Provider>;
}

export function useToast(): ToastContextValue {
    const context = useContext(ToastContext);

    if (context === undefined) {
        throw new Error('useToast must be used within ToastProvider.');
    }

    return context;
}

export function PgsToastViewport() {
    const { toasts, dismissToast } = useToast();

    return (
        <div className="pgs-toast-viewport" aria-live="polite" aria-atomic="false">
            {toasts.map((toast) => {
                const Icon = toastIcons[toast.kind];

                return (
                    <div className="pgs-toast" data-kind={toast.kind} key={toast.id} role="status">
                        <span className="pgs-toast-icon" aria-hidden="true">
                            <Icon size={16} />
                        </span>
                        <span className="pgs-toast-message">{toast.message}</span>
                        <button
                            className="pgs-toast-dismiss"
                            type="button"
                            aria-label="Dismiss notification"
                            onClick={() => {
                                dismissToast(toast.id);
                            }}
                        >
                            <X size={15} />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
