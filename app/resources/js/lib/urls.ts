/**
 * Centralised frontend route helpers.
 *
 * Ziggy `route()` is only used in auth pages; the remaining ~100 call sites
 * hard-code path strings. This module provides a single source of truth so
 * renames break loudly at build time instead of silently at runtime.
 *
 * Prefer these helpers over raw strings. For routes that take params, the
 * helper builds the path; for simple GETs it returns the constant string.
 */

export const urls = {
    users: {
        index: '/users',
        create: '/users/create',
        store: '/users',
        edit: (id: number | string) => `/users/${String(id)}/edit`,
        update: (id: number | string) => `/users/${String(id)}`,
        updateAccess: (id: number | string) => `/users/${String(id)}/access`,
        toggle: (id: number | string) => `/users/${String(id)}/toggle`,
        destroy: (id: number | string) => `/users/${String(id)}`,
        import: '/users/import',
    },
    gallery: {
        index: '/gallery',
        albums: '/gallery/albums',
        album: (id: number | string) => `/gallery/albums/${String(id)}`,
        photos: (albumId: number | string) => `/gallery/albums/${String(albumId)}/photos`,
        photo: (id: number | string) => `/gallery/photos/${String(id)}`,
        file: (id: number | string) => `/gallery/photos/${String(id)}/file`,
    },
    notices: {
        index: '/notices',
        store: '/notices',
        update: (id: number | string) => `/notices/${String(id)}`,
        destroy: (id: number | string) => `/notices/${String(id)}`,
        media: (id: number | string, kind: string) => `/notices/${String(id)}/${kind}`,
    },
    uploads: {
        show: (slug: string) => `/${slug}/upload`,
        store: (slug: string) => `/${slug}/upload`,
        status: (slug: string, id: number | string) => `/${slug}/upload/${String(id)}/status`,
        destroy: (slug: string, id: number | string) => `/${slug}/upload/${String(id)}`,
        templates: {
            store: (slug: string) => `/${slug}/upload/templates`,
            download: (slug: string, id: number | string) => `/${slug}/upload/templates/${String(id)}`,
            destroy: (slug: string, id: number | string) => `/${slug}/upload/templates/${String(id)}`,
        },
    },
    sectors: {
        index: '/sectors',
        show: (slug: string) => `/sectors/${String(slug)}`,
        rows: (slug: string) => `/sectors/${String(slug)}/rows`,
        row: (slug: string, id: number | string) => `/sectors/${String(slug)}/rows/${String(id)}`,
        progress: (slug: string, id: number | string) => `/sectors/${String(slug)}/progress/${String(id)}`,
    },
    sectorDetails: (pillar: string, slug: string) => `/sectors/${pillar}/${slug}`,
    scorecard: {
        index: '/impact-scorecard',
        measures: '/impact-scorecard/measures',
        measure: (id: number | string) => `/impact-scorecard/measures/${String(id)}`,
        years: '/impact-scorecard/years',
        year: (id: number | string) => `/impact-scorecard/years/${String(id)}`,
        value: (measureId: number | string, yearId: number | string) =>
            `/impact-scorecard/values/${String(measureId)}/${String(yearId)}`,
    },
    surveys: {
        index: '/surveys',
        archive: (id: number | string) => `/surveys/${String(id)}/archive`,
        destroy: (id: number | string) => `/surveys/${String(id)}`,
        done: (id: number | string) => `/surveys/${String(id)}/done`,
    },
    roadmaps: {
        index: '/roadmaps',
        titles: '/roadmaps/titles',
        title: (id: number | string) => `/roadmaps/titles/${String(id)}`,
        items: (titleId: number | string) => `/roadmaps/titles/${String(titleId)}/items`,
        item: (id: number | string) => `/roadmaps/items/${String(id)}`,
        blocks: (itemId: number | string) => `/roadmaps/items/${String(itemId)}/blocks`,
        block: (id: number | string) => `/roadmaps/blocks/${String(id)}`,
    },
    deliverables: {
        index: '/deliverables',
        store: '/deliverables',
        update: (id: number | string) => `/deliverables/${String(id)}`,
        status: (id: number | string) => `/deliverables/${String(id)}/status`,
        destroy: (id: number | string) => `/deliverables/${String(id)}`,
        download: (id: number | string) => `/deliverables/${String(id)}/download`,
        pdf: (id: number | string) => `/deliverables/${String(id)}/pdf`,
    },
    backups: {
        index: '/backups',
        create: '/backups',
        restore: (disk: string, path: string) => `/backups/${disk}/${path}/restore`,
        download: (disk: string, path: string) => `/backups/${disk}/${path}`,
        destroy: (disk: string, path: string) => `/backups/${disk}/${path}`,
    },
    dashboard: '/dashboard',
    profile: { edit: '/profile', update: '/profile', destroy: '/profile' },
} as const;
