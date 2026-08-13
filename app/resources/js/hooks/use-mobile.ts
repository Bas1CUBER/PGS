import { useSyncExternalStore } from 'react';

const MOBILE_BREAKPOINT = 768;

function subscribe(onChange: () => void): () => void {
    const mql = window.matchMedia(`(max-width: ${String(MOBILE_BREAKPOINT - 1)}px)`);
    mql.addEventListener('change', onChange);

    return () => {
        mql.removeEventListener('change', onChange);
    };
}

function getSnapshot(): boolean {
    return window.innerWidth < MOBILE_BREAKPOINT;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
