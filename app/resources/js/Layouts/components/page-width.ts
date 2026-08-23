export function normalizedPath(url: string): string {
    return url.split('?')[0].replace(/\/+$/, '') || '/';
}

export type PageWidth = 'compact' | 'standard' | 'wide';

export function pageWidthFor(path: string): PageWidth {
    if (
        path === '/deadlines' ||
        path === '/profile' ||
        path === '/notifications' ||
        path === '/confirm-password' ||
        path === '/verify-email' ||
        path === '/mailbox' ||
        path.startsWith('/mailbox/') ||
        path === '/users/create' ||
        (path.startsWith('/users/') && path.endsWith('/edit')) ||
        path === '/deliverables/create' ||
        (path.startsWith('/deliverables/') && path.endsWith('/edit'))
    ) {
        return 'compact';
    }

    if (
        path === '/dashboard' ||
        path === '/roadmaps' ||
        path === '/impact-scorecard' ||
        path === '/sectors' ||
        path.startsWith('/sectors/') ||
        path === '/operations-review' ||
        path === '/strategy-review' ||
        path === '/communication-plan' ||
        path === '/opcr' ||
        path.startsWith('/annex/') ||
        path.endsWith('/upload') ||
        path.startsWith('/uploads/')
    ) {
        return 'wide';
    }

    return 'standard';
}
