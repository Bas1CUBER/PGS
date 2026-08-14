/**
 * Convert an internal absolute URL from Laravel pagination or shared props to
 * a same-origin relative path. External URLs are returned unchanged.
 */
export function relativeInternalUrl(value: string | null): string | null {
    if (value === null || value === '') return value;

    try {
        const parsed = new URL(value, 'http://pgs.internal');
        const currentOrigin = typeof window !== 'undefined' ? window.location.origin : null;

        if (
            parsed.origin === 'http://pgs.internal' ||
            parsed.origin === currentOrigin ||
            parsed.hostname === '127.0.0.1' ||
            parsed.hostname === 'localhost'
        ) {
            return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        }
    } catch {
        // Keep malformed or non-URL values unchanged for the caller to handle.
    }

    return value;
}
