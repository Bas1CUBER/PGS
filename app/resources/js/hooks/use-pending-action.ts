import { useCallback, useRef, useState } from 'react';

const minimumLoadingTime = 650;

export function usePendingAction() {
    const [pendingAction, setPendingAction] = useState<string | null>(null);
    const startedAt = useRef<Record<string, number | undefined>>({});
    const timers = useRef<Record<string, number | undefined>>({});

    const start = useCallback((action: string) => {
        const timer = timers.current[action];
        if (timer !== undefined) {
            window.clearTimeout(timer);
        }
        startedAt.current[action] = Date.now();
        setPendingAction(action);
    }, []);

    const finish = useCallback((action: string) => {
        const started = startedAt.current[action];
        const elapsed = Date.now() - (started ?? Date.now());
        const clear = () => {
            startedAt.current[action] = undefined;
            timers.current[action] = undefined;
            setPendingAction((current) => (current === action ? null : current));
        };

        if (elapsed < minimumLoadingTime) {
            timers.current[action] = window.setTimeout(clear, minimumLoadingTime - elapsed);
            return;
        }

        clear();
    }, []);

    const isPending = useCallback((action: string) => pendingAction === action, [pendingAction]);

    return { isPending, start, finish };
}
