/**
 * Single source of truth for byte formatting (Backups, Uploads, Gallery).
 * Includes the GB tier — backups routinely exceed 4 GB.
 */
export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${String(bytes)} B`;
    const kb = bytes / 1024;
    if (kb < 1024) return `${kb.toFixed(1)} kB`;
    const mb = kb / 1024;
    if (mb < 1024) return `${mb.toFixed(1)} MB`;
    return `${(mb / 1024).toFixed(2)} GB`;
}
