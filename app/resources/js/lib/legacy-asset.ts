export function legacyImageUrl(filename: string): string {
    return `/legacy-img/${encodeURIComponent(filename)}`;
}
