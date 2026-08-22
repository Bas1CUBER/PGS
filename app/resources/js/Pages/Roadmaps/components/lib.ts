import type { PgsStatTone } from '@/components/pgs-stat-card';

export const blockTypes = ['heading', 'paragraph', 'table', 'dashboard_stat'];

export function contentText(content: Record<string, unknown>, key: string, fallback: string): string {
    const value = content[key];

    return typeof value === 'string' || typeof value === 'number' ? String(value) : fallback;
}

export function contentTone(content: Record<string, unknown>): PgsStatTone {
    const tone = content.tone;

    return tone === 'green' || tone === 'violet' || tone === 'amber' || tone === 'red'
        ? tone
        : 'blue';
}

export function isRecordOfStrings(val: unknown): val is Record<string, string> {
    return (
        typeof val === 'object' &&
        val !== null &&
        !Array.isArray(val) &&
        Object.values(val).every((v) => typeof v === 'string')
    );
}
